# Kor Hishab — Bangladesh Salary Tax Calculator

**Kor Hishab** (কর হিসাব, "tax calculation") is a web app that calculates Bangladesh personal income tax for salaried people, explains the result, and helps lower it. It uses the **Income Tax Act 2023 as amended by the Finance Act 2026** and also includes the projected years from the government's enacted 5-year roadmap.

Anyone can use the calculators without an account. With an account, you can save calculations, compare them, and store a default salary split.

---

## Table of contents

1. [Features](#features)
2. [Tech stack](#tech-stack)
3. [How the tax is calculated](#how-the-tax-is-calculated)
4. [Project structure](#project-structure)
5. [Routes](#routes)
6. [Database](#database)
7. [Configuration (.env)](#configuration-env)
8. [Local development](#local-development)
9. [Testing](#testing)
10. [Admin and maintenance commands](#admin-and-maintenance-commands)
11. [Updating tax rules](#updating-tax-rules)
12. [**Deploying to a VPS (runbook for Claude)**](#deploying-to-a-vps-runbook-for-claude)

---

## Features

### Tax calculator (`/`)
- Inputs: assessment year, taxpayer category, gross annual salary, rebate-eligible investments (DPS, savings certificates, mutual funds, listed shares, plus provident fund, life insurance, Universal Pension and zakat or approved donations), TDS already deducted, filing period, new-taxpayer flag, and the number of disabled children or dependents.
- Output: tax-free salary (the ⅓ exemption), taxable income, a slab-by-slab breakdown, gross tax, investment rebate, minimum-tax top-up, early or late filing adjustment, final liability, amount still to pay or refund due, effective and marginal rates, and monthly take-home pay.
- **Rebate optimiser:** shows how much eligible investment unlocks the full rebate, how much more to invest, an example plan that fills capped instruments first, and any investment that earns no rebate.
- **Plain-language predictions:** headroom before the next slab, the filing deadline and its saving, TDS status, how much of a ৳10,000 raise you keep, bonus tax, the effect of a 10% raise, and the effect of future roadmap years.
- **Raise scenarios** from 0% to 50%.
- **Charts** (Chart.js): income split, tax waterfall, effective and marginal rate curve, an income × investment heatmap, tax across years, tax across categories, and a paycheck split.
- Number grouping toggle: international (1,335,524) or lakh/crore (13,35,524), stored in the `kh_grouping` cookie.
- The browser keeps a draft of your inputs.

### Bangla interface (বাংলা)
- Every page, chart, generated prediction and validation message is available in Bangla. Switch with the **বাংলা / EN** button in the top bar or `?lang=bn`; the choice is kept in the `kh_locale` cookie.
- Bangla pages use Bangla digits (১২৩) by default, with a footer toggle for Latin digits. Money inputs accept either digit set.
- Translations live in `lang/bn.json` (keyed by the English text) and `lang/bn/validation.php`. Browser-side text uses the same file through `/lang/bn.js` and `KH.t()`.
- `tests/Unit/TranslationCoverageTest.php` fails if any `__()`, `Lang::t()` or `KH.t()` string has no Bangla translation, so new text can't ship untranslated.

### Target tax to salary (`/target-tax`)
- A reverse calculator: enter the tax you want to pay and it finds the smallest gross salary that produces it, using a binary search over the tax engine.
- Rebate modes: no investment, a custom investment, or the maximum useful investment.
- Splits the salary into components (Basic, House Rent, Medical, Conveyance, Festival Bonus, Other Bonuses, Overtime) using editable ratios, and produces copy-ready text such as `1 Basic TK. 732,193/-`.
- Signed-in users can save their own ratios as the default.

### Return form guide (`/return-guide`)
- Shows where each calculated figure goes on NBR's return form **IT-11GA (2023)**, line by line, for the main statement, Schedule 1 (salary, non-government) and Schedule 5 (investment tax credit).
- Opened from the calculator ("Where to enter this on the return") with the current inputs, or from a saved calculation (`/calculations/{id}/return-guide`). Without inputs it shows the blank map.
- Also lists the e-return portal steps and the documents to keep ready, with sources and the date they were checked. Prints cleanly (light theme, no navigation).
- Line mapping lives in `config/return_form.php`; `app/Services/Tax/ReturnGuide.php` only reads figures from the tax report.

### Monthly TDS planner (`/tds-planner`)
- Twelve months (July to June) of salary and bonus, **taxable perks** for the year (overtime, housing, transport, employer PF and others, as Schedule 1(b) lists them; `config/salary.php`) and the **investments** planned. Tick the months already paid and enter the TDS actually deducted.
- **Three outcomes** from the same `TaxEngine`: tax without any investment, tax with the plan, and the **lowest legal tax** with the full investment rebate. Also the monthly TDS range between those.
- **Deduction strategy:** cover the tax as planned, deduct for the lowest tax (investments declared to HR), or cap the monthly TDS and pay the rest with the return. Section 86 makes employers deduct on the estimated tax, and the page says so.
- **Automatic advice** with one-click actions: what to invest to reach the lowest tax, employer PF that also counts as an investment, the tax added by bonuses and perks, the early-filing saving, slab warnings and more. Plus a pace chart and a ready-to-send HR message.
- Investment, rebate and prediction logic is reused from `TaxReport`; the planner (`app/Services/Tax/TdsPlanner.php`) adds the monthly spread and strategies.

### "Do I need to file a return?" (`/must-i-file`)
- A short questionnaire on the section 166 conditions of the Income Tax Act 2023: income above the tax-free limit (computed per category from `config/tax.php`), assessed in the last three years, company employee or shareholder director, government employee, executive post, partner, exempt or reduced-rate income. One "yes" makes filing compulsory, and the page says which rule applies.
- Lists common services that ask for proof of return submission (PSR), and those where a TIN is enough since the Finance Ordinance 2025. People not required to file are exempt from PSR.
- Rules, sources and the date they were checked live in `config/filing_check.php`. The rules are evaluated in the browser; the salary at which tax starts comes from `TaxEngine`.

### Job offer comparer (`/compare-offers`)
- Two or three offers side by side, entered the way offer letters are written: monthly salary, basic share, festival bonuses (months of basic or of gross), other yearly cash and the employer's PF contribution.
- Tax comes from the same `TaxEngine`. Employer PF counts as salary for tax (Schedule 1) but not as cash, so offers are compared on **monthly take-home** and on **total yearly value** (take-home plus PF).
- Shows the best offer, the difference against the first offer ("a 20% raise on paper is 17.6% in your pocket"), the investment each needs for the full rebate, and a chart of where the money goes. Logic in `app/Services/Tax/OfferComparer.php`.

### Assets and liabilities statement (`/wealth`, signed in)
- A year-by-year working copy of NBR's **IT-10B (2023)** statement of assets and liabilities and **IT-10BB (2023)** lifestyle expenses, with the form's serial numbers.
- A new year starts from last year's assets and liabilities, and takes income and TDS from that year's saved calculation.
- **The check:** the form derives this year's net wealth from last year's plus sources of fund minus expenses, and it must equal assets minus liabilities. The page shows any gap live (more wealth than income explains, or less than it should be), the kind of mismatch that prompts NBR questions. Small gaps (the larger of ৳10,000 and 1% of sources) count as rounding.
- Print view in IT-10B order. Logic in `app/Services/Wealth/WealthReconciler.php`, lines in `config/wealth.php`.

### Pay less tax, legally (`/save-tax`, and in every calculation)
- Seventeen legal choices from the Income Tax Act 2023 and Finance Act 2026, each with its legal reference, for example:
  - reaching the full investment rebate
  - claiming provident fund contributions, life insurance, Universal Pension, zakat and approved donations
  - filing between July and September, and not filing late
  - the lower minimum tax on a first return
  - checking the taxpayer category and the disabled-dependent allowance
  - separate limits for spouses
  - why the basic/allowance split doesn't matter
  - avoiding over-investment
  - declaring investments to HR
  - claiming refunds
- **Personalised:** the calculator shows the tips that apply to the numbers on screen, with the saving each brings, largest first. "What if" savings (another category, a disabled dependent, a first return, early filing) come from `TaxEngine`.
- The catalogue page lists them all. Signed-in users with a salary profile also get a "For you" section.
- Words live in `config/tips.php`, rules in `app/Services/Tax/TaxTips.php`.

### Accounts (optional)
- Register, sign in and sign out. Passwords need 8 or more characters with letters and numbers. Sign-in is rate-limited.
- **Saved calculations** (`/calculations`): a list with stats and a trend chart. You can open, rename or edit notes, delete, and **compare two side by side**.
- **Account page:** change your name, email and password.
- There is no email or password-reset flow, so no mail server is needed. An admin resets passwords from the command line (see [Admin and maintenance commands](#admin-and-maintenance-commands)).

### Settings and tax profile (`/account`, signed in)
- **Tax profile:** category (shown as cards with each tax-free limit), disabled children and first-return status. Every calculator, planner and checker starts from it, and the user can still change anything on the page.
- **Salary profile (optional):** monthly salary, basic share, festival bonuses and employer PF. It pre-fills the calculator, the TDS planner (salary and bonus months) and the "Current job" offer. `App\Services\Tax\SalaryPackage` does the monthly-to-yearly maths for all of them.
- **Display:** language, digits and number style are saved with the account and applied on any device at sign-in.
- One place reads all of this: `App\Support\TaxProfile::for($user)`, which falls back to defaults for guests. Stored in `users.preferences` (json).

### Security notes
- The server recalculates every saved figure from the inputs. Numbers sent from the browser are never trusted.
- Users can only see their own calculations. Other users' calculations return a 404.
- Rate limits: `/calculate` and `/target-tax/solve` allow 240 requests per minute, login 20, and register 10. Each email and IP pair also gets 5 failed sign-ins before a lockout.

---

## Tech stack

| Layer | Choice |
|---|---|
| Language | **PHP 8.3 or newer**. `composer.json` sets `config.platform.php` to `8.3.0`, so `composer.lock` resolves to versions that run on 8.3 (Symfony 7.4 LTS). |
| Framework | Laravel 13 |
| Database | SQLite by default (WAL mode, busy timeout 5 s, set in `AppServiceProvider`). MySQL and PostgreSQL also work. |
| Sessions, cache, rate limits | `database` driver (no Redis needed) |
| Frontend | Blade templates with **plain JS and CSS in `public/`**, plus vendored Alpine.js 3.17.4 and Chart.js 4.5.1 in `public/vendor/`. Fonts are self-hosted in `public/fonts/`. |
| Build step | **None.** `vite.config.js`, `package.json` and `resources/css|js` are unused leftovers from the Laravel skeleton. The layout loads `public/css/app.css` and `public/js/*.js` directly, so **Node.js is not needed to run or deploy the app**. |
| Tests | PHPUnit 12 (26 tests: tax engine unit tests, plus auth and calculation feature tests) |

---

## How the tax is calculated

All tax rules live in **[config/tax.php](config/tax.php)**. The engine has no hard-coded numbers.

For each assessment year:

1. **Salary exemption:** ⅓ of gross salary is tax-free, capped at ৳500,000.
2. **Taxable income** = gross − exemption.
3. **Tax-free threshold** by category (AY 2026-27 / 2027-28):

   | Category | Threshold |
   |---|---|
   | General | ৳400,000 |
   | Woman or senior citizen (65+) | ৳450,000 |
   | Person with disability / third gender | ৳525,000 |
   | War-wounded freedom fighter / July fighter | ৳550,000 |

   Add ৳50,000 for each disabled child or dependent.
4. **Slabs above the threshold:** the next ৳300k at 10%, the next ৳400k at 15%, the next ৳500k at 20%, the next ৳2M at 25%, and the rest at 30%. The projected years 2028-30 and 2030-31 raise the thresholds and add a 35% top slab.
5. **Investment rebate:** the lowest of 10% of eligible investment, 3% of taxable income, and ৳750,000. Each instrument has a cap: DPS ৳120k, savings certificates ৳500k, mutual funds ৳500k, shares uncapped.
6. **Minimum tax:** ৳5,000, or ৳1,000 for a new taxpayer, when taxable income is above the threshold. The rebate can never push tax below it.
7. **Filing-period adjustment:**
   - Jul–Sep: 5% rebate, up to ৳25,000
   - Oct–Dec: no change
   - Jan–Mar: +2%, at least ৳3,000
   - Apr–Jun: +5%, at least ৳5,000
8. **Payable** = liability − TDS paid. A negative result is a refund.

Code:
- `app/Services/Tax/TaxEngine.php`: pure arithmetic with no framework dependency (a singleton built from `config('tax')`).
- `app/Services/Tax/TaxReport.php`: builds everything the UI shows, including the summary, slabs, optimiser, scenarios, charts and predictions. Dates use the Asia/Dhaka timezone.
- `app/Services/Tax/TargetTaxSolver.php`: reverse solver and salary split.
- `app/Support/Money.php`: ৳ formatting with international or lakh grouping.

---

## Project structure

```
app/
  Http/Controllers/
    CalculatorController.php     # "/" page, POST /calculate (JSON), open a saved calculation
    TargetTaxController.php      # /target-tax page, solve (JSON), save default ratios
    CalculationController.php    # saved list, store/update/delete (JSON), compare
    AccountController.php        # profile + password
    Auth/AuthController.php      # login, register, logout
  Http/Requests/                 # validation for calculator, target-tax and save inputs
  Models/User.php                # + salary_ratios (json)
  Models/Calculation.php         # saved calculation (inputs + summary as json)
  Providers/AppServiceProvider.php  # TaxEngine singleton, SQLite WAL, password rules
  Services/Tax/                  # TaxEngine, TaxReport, TargetTaxSolver
  Support/Money.php
config/tax.php                   # ALL tax rules
config/korhishab.php             # seed (owner) account settings
database/migrations/             # users, cache, jobs, calculations, salary_ratios
database/seeders/DatabaseSeeder.php  # creates the owner account + one example calculation
public/css/app.css, public/js/{app,calculator,target}.js   # the real frontend
public/vendor/                   # alpine + chart.js (vendored)
resources/views/                 # Blade: calculator, target, calculations, auth, account, layout
routes/web.php                   # all HTTP routes
routes/console.php               # `user:password` admin command
tests/Unit/TaxEngineTest.php, tests/Feature/{AuthTest,CalculationTest}.php
```

---

## Routes

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET | `/` | – | Calculator |
| POST | `/calculate` | – | JSON tax report (throttle 240/min) |
| GET | `/target-tax` | – | Target-tax page |
| POST | `/target-tax/solve` | – | JSON solver result (throttle 240/min) |
| GET/POST | `/login`, `/register` | guest | Sign in / create account |
| POST | `/logout` | ✓ | Sign out |
| GET | `/calculations` | ✓ | Saved list |
| GET | `/calculations/compare?a=&b=` | ✓ | Compare two |
| GET | `/return-guide` | – | Return form guide (calculator inputs in the query string) |
| GET | `/tds-planner` | – | Monthly TDS planner |
| GET | `/must-i-file` | – | Return filing checker |
| GET | `/save-tax` | – | Legal ways to pay less tax |
| GET | `/compare-offers` | – | Job offer comparer |
| POST | `/compare-offers/compute` | – | JSON comparison (throttle 240/min) |
| POST | `/tds-planner/plan` | – | JSON plan (throttle 240/min) |
| GET | `/calculations/{id}/return-guide` | ✓ | Return form guide for a saved calculation |
| POST | `/calculations` | ✓ | Save (JSON) |
| GET / PUT / DELETE | `/calculations/{id}` | ✓ | Open / update / delete |
| POST | `/target-tax/ratios` | ✓ | Save default salary split |
| GET | `/wealth` | ✓ | Assets and liabilities, all years |
| GET / PUT / DELETE | `/wealth/{year}` | ✓ | Edit / save (JSON) / delete one year's statement, e.g. `2026-27` |
| GET | `/wealth/{year}/print` | ✓ | Print view in IT-10B order |
| GET / PUT | `/account`, `/account/password` | ✓ | Settings page / account details / password |
| PUT | `/account/tax`, `/account/display` | ✓ | Save tax & salary profile / display settings |
| GET | `/lang/bn.js` | – | Bangla strings for browser-side text (cached) |
| GET | `/up` | – | Health check (HTTP 200 when the app boots) |

---

## Database

| Table | Notes |
|---|---|
| `users` | Standard Laravel users plus `salary_ratios` and `preferences` (json, nullable; tax, salary and display settings) |
| `calculations` | `user_id` (cascade delete), `title`, `notes`, `tax_year`, `category`, `gross_income`, `liability`, `payable`, `effective_rate`, `inputs` (json), `summary` (json). Indexed on (`user_id`, `updated_at`). |
| `wealth_statements` | `user_id` (cascade delete), `tax_year` (unique per user), `opening_net_wealth` (only for a first statement), `receipts`, `expenses`, `liabilities`, `assets` (json, one amount per form line), `notes`. |
| `sessions`, `cache`, `cache_locks` | Database session, cache and rate-limiter storage |
| `jobs`, `job_batches`, `failed_jobs` | From the skeleton. **The app dispatches no jobs and schedules no tasks**, so no queue worker or cron is needed. |

The default database file is `database/database.sqlite`. Git ignores it (`database/.gitignore`).

---

## Configuration (.env)

Start from `.env.example`. The keys that matter:

| Key | Dev | Production |
|---|---|---|
| `APP_NAME` | `"Kor Hishab"` | `"Kor Hishab"` |
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | **`false`** |
| `APP_KEY` | `php artisan key:generate` | generated once on the server, **never changed afterwards** (changing it logs everyone out) |
| `APP_URL` | `http://localhost:8000` | `https://your-domain` |
| `DB_CONNECTION` | `sqlite` | `sqlite` (recommended) |
| `SESSION_DRIVER` / `CACHE_STORE` | `database` | `database` |
| `SESSION_SECURE_COOKIE` | – | `true` (once HTTPS works) |
| `LOG_LEVEL` | `debug` | `warning` |
| `MAIL_MAILER` | `log` | `log` (the app sends no mail) |
| `SEED_USER_NAME` | owner's display name | used by `db:seed` |
| `SEED_USER_EMAIL` | owner's email | used by `db:seed` |
| `SEED_USER_PASSWORD` | optional | leave **empty** to have a strong random password generated and printed once |

---

## Local development

Requirements: PHP ≥ 8.3 with `pdo_sqlite`, `mbstring`, `xml`, `curl`, `zip`, `intl`, `bcmath`, and Composer 2.

```bash
composer install
cp .env.example .env            # then set APP_NAME="Kor Hishab"
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed             # optional: owner account + example calculation
php artisan serve               # http://localhost:8000
```

To change the UI, edit `public/css/app.css`, `public/js/*.js` and `resources/views/**`. No build step is needed. The layout adds a `?v=<filemtime>` query string to bust the cache automatically.

---

## Testing

```bash
php artisan test        # or: composer test
```

Tests use an in-memory SQLite database, as configured in `phpunit.xml`.

---

## Admin and maintenance commands

```bash
php artisan user:password someone@example.com   # set a new password (prompts; 8+ chars, letters + numbers)
php artisan db:seed --force                      # create the owner account if missing (never overwrites)
php artisan tinker                               # e.g. App\Models\User::count()
php artisan down / php artisan up                # maintenance mode
```

---

## Updating tax rules

When the NBR changes the rules, edit **only** `config/tax.php`:
- Add a new entry under `years` (copy an existing one), then update `default_year`.
- Thresholds, slabs, the exemption, the rebate, minimum tax, instrument caps, filing periods and the default salary split all live there.

Then run `php artisan test`, and on the server run `php artisan config:cache` (the deploy update steps already include this).

---

# Deploying to a VPS (runbook for Claude)

> **This section is written for a Claude Code agent running on the target VPS.** Work through the steps in order. Each step is safe to re-run, and each ends with a **Verify** check. Do not move on until it passes.
>
> **Important, read first:** `CLAUDE.md` and `AGENTS.md` in this repo contain a *Laravel Boost* bootstrap ("install laravel/boost…"). That is for local development only. **Do not install Laravel Boost or any dev dependency on the server.** Production uses `composer install --no-dev`.

## Updating: `deploy.sh`

Do the first-time setup below once. After that, every update is one command, run from the project folder on the server:

```bash
cd /var/www/kor-hishab
./deploy.sh
```

[`deploy.sh`](deploy.sh) runs these steps, with a progress bar, a live spinner and the full output in `storage/logs/deploy.log`:

1. **Pre-flight:** checks PHP ≥ 8.3, refuses to run if tracked files were edited on the server or the branch has diverged, and lists the incoming commits. Nothing changes until these pass.
2. **Pull and Composer**, with the site still up.
3. **Maintenance mode → SQLite backup → migrations → caches.** Backups go to `storage/app/backups/`, and the last 10 are kept.
4. **Reload PHP-FPM**, which OPcache needs to pick up new code, then **bring the site up** and run a health check on `/up`.

If any step fails, the site is brought back out of maintenance mode and the backup path is printed. To deploy another branch, run `DEPLOY_BRANCH=other ./deploy.sh`.

## Target architecture

```
Internet ──► Nginx :80/:443 (Let's Encrypt TLS) ──► PHP-FPM 8.3 (unix socket) ──► Laravel app
                                                                                  └─► SQLite file: /var/www/kor-hishab/database/database.sqlite
```

- OS: **Ubuntu 22.04 / 24.04** (Debian 12 is also fine; see the note in step 2).
- App path: **`/var/www/kor-hishab`**. Runs as the **`www-data`** user.
- No Node.js, Redis, MySQL, queue worker, cron or mail server is needed.

## Step 0 — Collect inputs (ask the human if missing)

| Variable | Example | Needed for |
|---|---|---|
| `DOMAIN` | `tax.example.com` | Nginx + TLS. Its DNS **A record must already point to this VPS's public IP**. If there's no domain yet, use the server IP and skip step 8. |
| `CERTBOT_EMAIL` | `admin@example.com` | Let's Encrypt registration |
| `SEED_USER_NAME` | `Emon` | Owner account |
| `SEED_USER_EMAIL` | `emon@example.com` | Owner account |
| Code source | git URL **or** "already uploaded to `/var/www/kor-hishab`" | Step 3 |

Use the values in the commands below. Example: `export DOMAIN=tax.example.com APP_DIR=/var/www/kor-hishab`.

## Step 1 — Inspect the server before changing anything

```bash
cat /etc/os-release | head -3
whoami; sudo -n true && echo "sudo ok"
php -v 2>/dev/null | head -1
nginx -v 2>&1; apache2 -v 2>/dev/null | head -1
ls /etc/nginx/sites-enabled/ 2>/dev/null
sudo ss -tlnp | grep -E ':80 |:443 ' || true
ls -la /var/www/ 2>/dev/null
curl -s -4 ifconfig.me; echo; getent ahostsv4 "$DOMAIN" | head -1
```

**Rules:**
- If **Apache** is using port 80, stop and ask the human. Don't remove it on your own.
- If other sites already exist in `/etc/nginx/sites-enabled/`, **leave them alone**. Only add `kor-hishab`. Remove `default` only if it is the stock Nginx welcome page and nothing else uses it.
- If `/var/www/kor-hishab` already exists with a `.env` and `database/database.sqlite`, this is an **update**, not a fresh install. Go to [Updating an existing deployment](#updating-an-existing-deployment).
- If the domain does not resolve to this server's IP, warn the human. Steps 2–7 can still run, but step 8 (TLS) will fail until DNS is fixed.

## Step 2 — Install system packages

```bash
sudo apt-get update
sudo apt-get install -y software-properties-common ca-certificates curl unzip git sqlite3 nginx ufw
```

**PHP 8.3 is required.** On **Ubuntu 24.04**, PHP 8.3 is in the standard packages, so you can install it directly. On **Ubuntu 22.04** (which ships 8.1), first add the ondrej PPA: `sudo add-apt-repository -y ppa:ondrej/php && sudo apt-get update`. On **Debian 12** (which ships 8.2), use packages.sury.org/php.

```bash
sudo apt-get install -y php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath php8.3-opcache
```

Install Composer 2 if it is missing:

```bash
command -v composer || {
  curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
  sudo php8.3 /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm /tmp/composer-setup.php
}
```

**Verify:**
```bash
php8.3 -v | head -1                       # PHP 8.3.x
php8.3 -m | grep -E 'pdo_sqlite|mbstring|intl|bcmath|xml|curl|zip' | wc -l   # ≥ 7
composer --version                        # Composer 2.x
systemctl is-active php8.3-fpm nginx      # active / active
```

If the default `php` CLI isn't 8.3, set it with `sudo update-alternatives --set php /usr/bin/php8.3`.

## Step 3 — Put the code in place

**Option A: from git** (if the human gave a repo URL):
```bash
sudo mkdir -p /var/www && sudo chown "$USER":www-data /var/www
git clone <REPO_URL> /var/www/kor-hishab
```

**Option B: uploaded from the developer's machine.** The human runs this **locally** from the project folder:
```bash
rsync -avz --exclude vendor --exclude node_modules --exclude .env \
  --exclude 'database/*.sqlite*' --exclude 'storage/logs/*' --exclude 'storage/framework/*/*' \
  --exclude 'bootstrap/cache/*.php' --exclude .idea --exclude .vscode \
  ./ user@VPS_IP:/var/www/kor-hishab/
```

> Never copy the local `.env` or `database/database.sqlite` to the server. Production gets its own.

**Verify:** `ls /var/www/kor-hishab/artisan /var/www/kor-hishab/composer.lock /var/www/kor-hishab/public/index.php`

## Step 4 — Install PHP dependencies

```bash
cd /var/www/kor-hishab
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
```

**Verify:** `test -f vendor/autoload.php && echo ok`. If Composer complains about the PHP version, step 2 did not install or select PHP 8.3.

## Step 5 — Environment file

Create `.env` **only if it doesn't exist yet**:

```bash
cd /var/www/kor-hishab
[ -f .env ] && echo ".env exists — do not overwrite" || cp .env.example .env
```

Then set these values in `.env` (edit the existing lines and add the missing ones):

```dotenv
APP_NAME="Kor Hishab"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMAIN            # use http://SERVER_IP if there's no domain/TLS
LOG_LEVEL=warning
DB_CONNECTION=sqlite
SESSION_DRIVER=database
CACHE_STORE=database
SESSION_SECURE_COOKIE=true        # set to false if serving plain HTTP (no TLS)
MAIL_MAILER=log
SEED_USER_NAME="<name>"
SEED_USER_EMAIL=<email>
SEED_USER_PASSWORD=
```

Generate the key **only if `APP_KEY` is empty**:

```bash
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
```

**Verify:** `grep -E '^(APP_ENV|APP_DEBUG|APP_URL|APP_KEY)=' .env`. Expect production, false, the right URL, and a key starting with `base64:`.

## Step 6 — Database, permissions, caches

```bash
cd /var/www/kor-hishab
touch database/database.sqlite

# Ownership: the code belongs to you (so `git pull` works without sudo), group www-data.
# storage/, bootstrap/cache/ and database/ are group-writable so PHP can write there too,
# including the -wal / -shm files that SQLite WAL mode creates next to the database.
sudo chown -R "$USER":www-data /var/www/kor-hishab
sudo find storage bootstrap/cache database -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache database -type f -exec chmod 664 {} \;
chmod 640 .env

# Run artisan as www-data so files it creates have the right owner
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --force
sudo -u www-data php artisan optimize          # config, route, view and event caches
```

`db:seed` prints **"Generated password: …"** once. **Copy it and give it to the human**, because it is not stored anywhere in plain text. If you miss it, reset it with `sudo -u www-data php artisan user:password <email>`.

**Verify:**
```bash
sudo -u www-data php artisan migrate:status | tail -6     # all "Ran"
sudo -u www-data php artisan about --only=environment     # production, debug OFF
sqlite3 database/database.sqlite "select count(*) from users;"   # ≥ 1
```

## Step 7 — Nginx site

Find the PHP-FPM socket: `ls /run/php/` (normally `/run/php/php8.3-fpm.sock`).

Write `/etc/nginx/sites-available/kor-hishab`. Replace `DOMAIN`, or use `_` / the server IP if there's no domain:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name DOMAIN;
    root /var/www/kor-hishab/public;
    index index.php;
    charset utf-8;
    client_max_body_size 2m;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Static assets are cache-busted with ?v=<mtime>, so long caching is safe
    location ~* \.(?:css|js|woff2|ico|svg|png|jpg)$ {
        expires 30d;
        add_header Cache-Control "public";
        try_files $uri =404;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Block every other .php file and dotfiles (.env, .git, …)
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }

    error_page 404 /index.php;
}
```

```bash
sudo ln -sf /etc/nginx/sites-available/kor-hishab /etc/nginx/sites-enabled/kor-hishab
sudo nginx -t && sudo systemctl reload nginx
```

Firewall. **Allow SSH before enabling ufw**, or you will lock yourself out:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw --force enable
```

**Verify:**
```bash
curl -s -o /dev/null -w '%{http_code}\n' -H "Host: $DOMAIN" http://127.0.0.1/up    # 200
curl -s -H "Host: $DOMAIN" http://127.0.0.1/ | grep -o '<title>[^<]*'              # Kor Hishab
curl -s -o /dev/null -w '%{http_code}\n' -H "Host: $DOMAIN" http://127.0.0.1/.env  # 403 or 404, never 200
```

## Step 8 — HTTPS (skip if there's no domain)

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$CERTBOT_EMAIL" --redirect
```

Certbot sets up automatic renewal. Check it with `sudo certbot renew --dry-run`.

If there's no TLS, set `SESSION_SECURE_COOKIE=false` and `APP_URL=http://…` in `.env`, then run `sudo -u www-data php artisan config:cache`. Otherwise sign-in silently fails, because the browser drops the secure cookie over HTTP.

**Verify:**
```bash
curl -s -o /dev/null -w '%{http_code}\n' https://$DOMAIN/up     # 200
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' http://$DOMAIN/   # 301 → https://
```

## Step 9 — PHP tuning (recommended)

Create `/etc/php/8.3/fpm/conf.d/99-kor-hishab.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
expose_php=Off
memory_limit=256M
```

`validate_timestamps=0` means PHP code changes only take effect after `sudo systemctl reload php8.3-fpm`. The update procedure below already does this.

```bash
sudo systemctl reload php8.3-fpm
```

## Step 10 — Backups (SQLite)

The whole app state is one file. Back it up every night with SQLite's online-backup command, which is safe while the app is running:

```bash
sudo install -d -m 2770 -o root -g www-data /var/backups/kor-hishab
sudo tee /etc/cron.d/kor-hishab-backup >/dev/null <<'EOF'
15 3 * * * www-data sqlite3 /var/www/kor-hishab/database/database.sqlite ".backup '/var/backups/kor-hishab/daily-$(date +\%F).sqlite'" && find /var/backups/kor-hishab -name 'daily-*.sqlite' -mtime +14 -delete
EOF
```

The backup runs as `www-data`, not root. If root opens the database, it can leave `-wal`/`-shm` files that PHP can't write, and the site then fails with "readonly database".

Also keep a copy of `.env` somewhere safe. Without its `APP_KEY`, existing sessions are invalid, though the data itself is not encrypted.

**Restore:** `php artisan down`, copy the backup over `database/database.sqlite`, delete any `-wal`/`-shm` files, fix ownership to `www-data`, then `php artisan up`.

## Step 11 — Final acceptance check

Report each result to the human:

1. `https://DOMAIN/` loads the calculator, and the figures and charts render.
2. `https://DOMAIN/target-tax` loads, and changing the target updates the salary.
3. Sign in with the seeded owner account, save a calculation, and confirm it appears in **Saved**.
4. `sudo tail -n 50 /var/www/kor-hishab/storage/logs/laravel.log` shows no new errors.
5. Tell the human the URL, the owner email, the **generated password** (from step 6), and how to reset it: `sudo -u www-data php artisan user:password <email>`.

---

## Updating an existing deployment

```bash
cd /var/www/kor-hishab
sudo -u www-data sqlite3 database/database.sqlite ".backup 'database/pre-deploy.sqlite'"   # safety copy
sudo -u www-data php artisan down --retry=15

git pull                               # or the human re-runs the rsync from step 3 (it excludes .env and the DB)
composer install --no-dev --optimize-autoloader --no-interaction
sudo chown -R "$USER":www-data . && sudo find storage bootstrap/cache database -type d -exec chmod 2775 {} \; && sudo find storage bootstrap/cache database -type f -exec chmod 664 {} \;
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan optimize
sudo systemctl reload php8.3-fpm

sudo -u www-data php artisan up
curl -s -o /dev/null -w '%{http_code}\n' https://$DOMAIN/up   # 200
```

**Rollback:** check out the previous commit (or re-upload the previous files), restore `database/pre-deploy.sqlite` if a migration changed data, then run `composer install --no-dev`, `optimize`, `reload php8.3-fpm` and `up`.

## Troubleshooting

| Symptom | Likely cause → fix |
|---|---|
| **500 error**, blank page | Check `storage/logs/laravel.log`. Usually permissions: re-run the `chown`/`chmod` lines from step 6. |
| `attempt to write a readonly database` / `unable to open database file` | The **`database/` directory** (not just the file) must be writable by `www-data`, because of WAL. Re-run the `chown`/`chmod` lines from step 6. |
| **419 Page Expired** on login or save | `SESSION_SECURE_COOKIE=true` while serving HTTP, or `APP_URL` doesn't match the domain. Fix `.env`, then run `sudo -u www-data php artisan config:cache`. |
| Composer: "requires php >=8.3" | Wrong PHP. Install php8.3 (step 2) and run `update-alternatives --set php /usr/bin/php8.3`. |
| `.env` changes have no effect | Config is cached. Run `sudo -u www-data php artisan config:cache`. |
| PHP code changes have no effect | OPcache with `validate_timestamps=0`. Run `sudo systemctl reload php8.3-fpm`. |
| CSS or JS looks old | Hard refresh. URLs carry `?v=<mtime>`, so a re-upload that kept the old mtimes won't bust the cache. Run `touch public/css/app.css public/js/*.js`. |
| 502 Bad Gateway | The PHP-FPM socket path in the Nginx config is wrong (`ls /run/php/`), or php8.3-fpm isn't running. |
| Locked out of the owner account | `sudo -u www-data php artisan user:password <email>` |

---

## License

MIT

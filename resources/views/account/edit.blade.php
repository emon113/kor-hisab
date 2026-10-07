@use('App\Support\Money')
<x-layout :title="__('Settings')" main-class="page">
    <div class="page-head reveal">
        <div>
            <h1>{{ __('Settings') }}</h1>
            <p>{{ __('Your tax profile is the starting point for every calculator, planner and report. You can still change anything on the page itself.') }}</p>
        </div>
    </div>

    <div class="settings">
        <nav class="settings-nav" aria-label="{{ __('Settings sections') }}">
            <a href="#tax"><x-icon name="user" size="16" /> {{ __('Tax profile') }}</a>
            <a href="#salary"><x-icon name="wallet" size="16" /> {{ __('Salary') }}</a>
            <a href="#display"><x-icon name="sun" size="16" /> {{ __('Display') }}</a>
            <a href="#profile"><x-icon name="user" size="16" /> {{ __('Account') }}</a>
            <a href="#password"><x-icon name="lock" size="16" /> {{ __('Password') }}</a>
        </nav>

        <div class="settings-body">
            {{-- ============ Tax + salary profile ============ --}}
            <form method="POST" action="{{ route('account.tax') }}" class="panel settings-card reveal" style="--i:1"
                  x-data="{
                      category: @js(old('category', $profile['category'])),
                      newTaxpayer: @js((bool) old('new_taxpayer', $profile['new_taxpayer'])),
                      monthly: @js((float) old('monthly_salary', $profile['monthly_salary'] ?? 0)),
                      pf: @js((float) old('employer_pf', $profile['employer_pf'] ?? 0)),
                      basicPct: @js((float) old('basic_pct', $profile['basic_pct'])),
                      bonusCount: @js((float) old('bonus_count', $profile['bonus_count'])),
                      bonusBase: @js(old('bonus_base', $profile['bonus_base'])),
                      /* Same arithmetic as App\Services\Tax\SalaryPackage, for the live preview only. */
                      get yearly() {
                          const m = Number(this.monthly) || 0;
                          const each = this.bonusBase === 'basic' ? Math.round(m * (Number(this.basicPct) || 0) / 100) : m;
                          return m * 12 + Math.round((Number(this.bonusCount) || 0) * each) + (Number(this.pf) || 0) * 12;
                      },
                  }">
                @csrf @method('PUT')
                <section id="tax" class="settings-section">
                    <header>
                        <h2>{{ __('Tax profile') }}</h2>
                        <p>{{ __('Your category sets your tax-free limit. It applies on every page.') }}</p>
                    </header>

                    <fieldset class="choice-grid" role="radiogroup" aria-label="{{ __('You are') }}">
                        @foreach ($categories as $cat)
                            <label class="choice" :class="{ on: category === @js($cat['key']) }">
                                <input type="radio" name="category" value="{{ $cat['key'] }}" x-model="category" class="sr-only">
                                <b>{{ $cat['label'] }}</b>
                                <small>{{ __('Tax-free up to :amount', ['amount' => Money::bdt($thresholds[$cat['key']])]) }}</small>
                            </label>
                        @endforeach
                    </fieldset>
                    @error('category', 'tax') <p class="error">{{ $message }}</p> @enderror

                    <div class="field-row" style="margin-top:18px">
                        <div class="field">
                            <label class="label" for="disabled_children">{{ __('Disabled children or dependents') }} <small>{{ __('+:amount tax-free each', ['amount' => Money::bdt(50000)]) }}</small></label>
                            <input id="disabled_children" name="disabled_children" type="number" min="0" max="10" class="input" value="{{ old('disabled_children', $profile['disabled_children']) }}">
                            @error('disabled_children', 'tax') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <span class="label">{{ __('First return') }}</span>
                            <label class="switch">
                                <input type="checkbox" name="new_taxpayer" value="1" x-model="newTaxpayer">
                                <span class="switch-track" aria-hidden="true"></span>
                                <span>{{ __('This is my first tax return') }}</span>
                            </label>
                            <p class="hint">{{ __('First-time filers pay a lower minimum tax.') }}</p>
                        </div>
                    </div>
                </section>

                <section id="salary" class="settings-section">
                    <header>
                        <h2>{{ __('Salary') }}</h2>
                        <p>{{ __('Optional. Fills in the calculator, the TDS planner and the offer comparer for you.') }}</p>
                    </header>
                    <div class="field-row">
                        <div class="field">
                            <label class="label" for="monthly_salary">{{ __('Monthly salary') }} <small>{{ __('Gross, before tax') }}</small></label>
                            <div class="money"><span>৳</span><input id="monthly_salary" class="input" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="KH.bindMoney($el, () => monthly, (v) => monthly = v)"></div>
                            <input type="hidden" name="monthly_salary" :value="monthly || ''">
                            @error('monthly_salary', 'tax') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="employer">{{ __('Employer') }} <small>{{ __('Optional') }}</small></label>
                            <input id="employer" name="employer" class="input" maxlength="120" value="{{ old('employer', $profile['employer']) }}" placeholder="{{ __('Company name') }}">
                        </div>
                    </div>
                    <div class="field-row" style="margin-top:16px">
                        <div class="field">
                            <label class="label" for="basic_pct">{{ __('Basic, % of salary') }}</label>
                            <input id="basic_pct" name="basic_pct" type="number" min="1" max="100" step="1" class="input" x-model.number="basicPct">
                        </div>
                        <div class="field">
                            <label class="label" for="bonus_count">{{ __('Bonuses a year') }}</label>
                            <input id="bonus_count" name="bonus_count" type="number" min="0" max="12" step="0.5" class="input" x-model.number="bonusCount">
                        </div>
                    </div>
                    <div class="field-row" style="margin-top:16px">
                        <div class="field">
                            <span class="label">{{ __('Each bonus is one month of') }}</span>
                            <input type="hidden" name="bonus_base" :value="bonusBase">
                            <div class="seg" role="group">
                                <button type="button" :aria-pressed="bonusBase === 'basic'" @click="bonusBase = 'basic'">{{ __('Basic') }}</button>
                                <button type="button" :aria-pressed="bonusBase === 'gross'" @click="bonusBase = 'gross'">{{ __('Gross') }}</button>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label" for="employer_pf">{{ __('Employer PF per month') }} <small>{{ __('Optional') }}</small></label>
                            <div class="money"><span>৳</span><input id="employer_pf" class="input" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="KH.bindMoney($el, () => pf, (v) => pf = v)"></div>
                            <input type="hidden" name="employer_pf" :value="pf || ''">
                        </div>
                    </div>
                    <p class="profile-preview" x-show="monthly > 0" x-transition.opacity>
                        <span>{{ __('Salary for tax') }}</span>
                        <strong x-text="KH.t(':amount a year', { amount: KH.bdt(yearly) })"></strong>
                    </p>
                </section>

                <div class="settings-actions"><button type="submit" class="btn btn-primary">{{ __('Save tax profile') }}</button></div>
            </form>

            {{-- ============ Display ============ --}}
            <form method="POST" action="{{ route('account.display') }}" id="display" class="panel settings-card reveal" style="--i:2"
                  x-data="{ locale: @js($profile['locale'] ?? app()->getLocale()), digits: @js($profile['digits'] ?? Money::digitStyle()), grouping: @js($profile['grouping'] ?? 'intl') }">
                @csrf @method('PUT')
                <section class="settings-section">
                    <header>
                        <h2>{{ __('Display') }}</h2>
                        <p>{{ __('Saved with your account, so every device you sign in on looks the same.') }}</p>
                    </header>
                    <input type="hidden" name="locale" :value="locale">
                    <input type="hidden" name="digits" :value="digits">
                    <input type="hidden" name="grouping" :value="grouping">
                    <div class="settings-rows">
                        <div class="settings-row">
                            <span class="label">{{ __('Language') }}</span>
                            <div class="seg" role="group" aria-label="{{ __('Language') }}">
                                <button type="button" :aria-pressed="locale === 'en'" @click="locale = 'en'">English</button>
                                <button type="button" :aria-pressed="locale === 'bn'" @click="locale = 'bn'" lang="bn">বাংলা</button>
                            </div>
                        </div>
                        <div class="settings-row">
                            <span class="label">{{ __('Digits in Bangla') }}</span>
                            <div class="seg" role="group" aria-label="{{ __('Digits in Bangla') }}">
                                <button type="button" :aria-pressed="digits === 'bn'" @click="digits = 'bn'">১২৩</button>
                                <button type="button" :aria-pressed="digits === 'latin'" @click="digits = 'latin'">123</button>
                            </div>
                        </div>
                        <div class="settings-row">
                            <span class="label">{{ __('Number style') }}</span>
                            <div class="seg" role="group" aria-label="{{ __('Number style') }}">
                                <button type="button" :aria-pressed="grouping === 'intl'" @click="grouping = 'intl'">1,335,524</button>
                                <button type="button" :aria-pressed="grouping === 'lakh'" @click="grouping = 'lakh'">13,35,524</button>
                            </div>
                        </div>
                    </div>
                </section>
                <div class="settings-actions"><button type="submit" class="btn btn-primary">{{ __('Save display settings') }}</button></div>
            </form>

            {{-- ============ Account ============ --}}
            <form method="POST" action="{{ route('account.update') }}" id="profile" class="panel settings-card reveal" style="--i:3">
                @csrf @method('PUT')
                <section class="settings-section">
                    <header><h2>{{ __('Account') }}</h2><p>{{ __('Shown in the account menu.') }}</p></header>
                    <div class="field-row">
                        <div class="field">
                            <label class="label" for="name">{{ __('Name') }}</label>
                            <input id="name" name="name" class="input" value="{{ old('name', $user->name) }}" required>
                            @error('name', 'profile') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="email">{{ __('Email') }}</label>
                            <input id="email" name="email" type="email" class="input" value="{{ old('email', $user->email) }}" required>
                            @error('email', 'profile') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>
                <div class="settings-actions"><button type="submit" class="btn btn-primary">{{ __('Save profile') }}</button></div>
            </form>

            {{-- ============ Password ============ --}}
            <form method="POST" action="{{ route('account.password') }}" id="password" class="panel settings-card reveal" style="--i:4">
                @csrf @method('PUT')
                <section class="settings-section">
                    <header><h2>{{ __('Password') }}</h2><p>{{ __('Use 8 or more characters with letters and numbers.') }}</p></header>
                    <div class="field">
                        <label class="label" for="current_password">{{ __('Current password') }}</label>
                        <input id="current_password" name="current_password" type="password" class="input" autocomplete="current-password" required>
                        @error('current_password', 'password') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div class="field-row" style="margin-top:16px">
                        <div class="field">
                            <label class="label" for="new-password">{{ __('New password') }}</label>
                            <input id="new-password" name="password" type="password" class="input" autocomplete="new-password" required>
                            @error('password', 'password') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="password_confirmation">{{ __('Confirm new password') }}</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
                        </div>
                    </div>
                </section>
                <div class="settings-actions"><button type="submit" class="btn btn-primary">{{ __('Change password') }}</button></div>
            </form>
        </div>
    </div>
</x-layout>

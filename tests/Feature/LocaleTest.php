<?php

namespace Tests\Feature;

use App\Services\Tax\TaxReport;
use App\Services\Tax\TdsPlanner;
use App\Support\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    private const BANGLA = '/[\x{0980}-\x{09FF}]/u';

    protected function tearDown(): void
    {
        Money::useDigits('latin');
        parent::tearDown();
    }

    public function test_english_is_the_default(): void
    {
        $this->get('/')->assertOk()->assertSee('<html lang="en"', false)->assertSee('Your details');
    }

    public function test_lang_query_remembers_the_choice_and_cleans_the_url(): void
    {
        $this->get('/target-tax?lang=bn')
            ->assertRedirect('/target-tax')
            ->assertCookie('kh_locale', 'bn');
    }

    public function test_pages_render_in_bangla(): void
    {
        $this->withCookie('kh_locale', 'bn');

        $this->get('/')->assertOk()
            ->assertSee('<html lang="bn"', false)
            ->assertSee('আপনার তথ্য')
            ->assertSee('/lang/bn.js', false);
        $this->get('/target-tax')->assertOk()->assertSee('করের পরিমাণ থেকে বেতন বের করুন');
        $this->get('/login')->assertOk()->assertSee('আবার স্বাগতম।');
    }

    public function test_bangla_uses_bangla_digits_unless_latin_is_chosen(): void
    {
        // JSON test requests only carry cookies with credentials, like fetch() on the same origin.
        $this->withCredentials()->withCookie('kh_locale', 'bn')
            ->postJson('/calculate', ['year' => '2026-27', 'category' => 'general', 'gross_income' => 1335524, 'investments' => ['dps' => 120000]])
            ->assertOk()
            ->assertJsonPath('summary.liability', 46552)            // numbers stay numbers
            ->assertJsonPath('rules.label', 'করবর্ষ ২০২৬-২৭');       // text uses Bangla digits

        $this->withCredentials()->withCookie('kh_locale', 'bn')->withUnencryptedCookie('kh_digits', 'latin')
            ->postJson('/calculate', ['year' => '2026-27', 'category' => 'general', 'gross_income' => 1335524])
            ->assertJsonPath('rules.label', 'করবর্ষ 2026-27');
    }

    public function test_validation_messages_are_in_bangla(): void
    {
        $this->withCredentials()->withCookie('kh_locale', 'bn')
            ->postJson('/calculate', ['year' => '2026-27', 'category' => 'general', 'gross_income' => -5])
            ->assertStatus(422)
            ->assertJsonPath('errors.gross_income.0', 'মোট আয় কমপক্ষে 0 হতে হবে।');
    }

    public function test_every_prediction_and_chart_label_is_translated(): void
    {
        App::setLocale('bn');
        Money::useDigits('bn');
        $report = app(TaxReport::class);

        // Inputs chosen to reach every prediction branch: tax-free, mid slab, top slab, minimum tax,
        // refunds and dues, early, standard and late filing, wasted investment, roadmap years.
        $cases = [
            ['gross_income' => 500000],
            ['gross_income' => 630000, 'new_taxpayer' => true],
            ['gross_income' => 1335524, 'investments' => ['dps' => 200000], 'tds_paid' => 30000],
            ['gross_income' => 1335524, 'investments' => ['shares' => 900000], 'tds_paid' => 200000, 'filing' => 'late'],
            ['gross_income' => 40000000, 'year' => '2030-31'],
            ['gross_income' => 3000000, 'filing' => 'early'],
        ];
        foreach (['2026-07-15', '2026-11-01', '2027-02-01'] as $today) {
            foreach ($cases as $case) {
                $r = $report->build($case + ['year' => '2026-27', 'category' => 'general'], new DateTimeImmutable($today));

                foreach ($r['predictions'] as $p) {
                    $this->assertMatchesRegularExpression(self::BANGLA, $p['title'], "Untranslated title: {$p['title']}");
                    $this->assertMatchesRegularExpression(self::BANGLA, $p['text'], "Untranslated text: {$p['text']}");
                }
                foreach (['waterfall', 'income_split', 'paycheck', 'future', 'categories'] as $chart) {
                    foreach ($r['charts'][$chart] as $row) {
                        $this->assertMatchesRegularExpression(self::BANGLA, $row['label'], "Untranslated {$chart} label: {$row['label']}");
                    }
                }
                foreach ($r['slabs'] as $row) {
                    $this->assertMatchesRegularExpression(self::BANGLA, $row['label']);
                }
            }
        }
    }

    public function test_planner_advice_is_translated(): void
    {
        App::setLocale('bn');
        Money::useDigits('bn');
        $months = [];
        foreach (TdsPlanner::MONTHS as $i => $key) {
            $months[$key] = ['salary' => 150000, 'bonus' => $i === 8 ? 90000 : 0, 'tds' => 0, 'done' => false];
        }

        foreach (['current', 'full_rebate', 'cap'] as $strategy) {
            $plan = app(TdsPlanner::class)->plan([
                'year' => '2027-28', 'category' => 'general', 'months' => $months, 'strategy' => $strategy, 'monthly_cap' => 2000,
                'perks' => ['employer_pf' => 72000], 'investments' => ['dps' => 10000],
            ], new DateTimeImmutable('2026-10-08'));

            $this->assertNotEmpty($plan['insights']);
            foreach ($plan['insights'] as $p) {
                $this->assertMatchesRegularExpression(self::BANGLA, $p['title'], "Untranslated title: {$p['title']}");
                $this->assertMatchesRegularExpression(self::BANGLA, $p['text'], "Untranslated text: {$p['text']}");
            }
            $this->assertMatchesRegularExpression(self::BANGLA, $plan['hr_text']);
        }
    }

    public function test_translation_script_is_served(): void
    {
        $this->get('/lang/bn.js')->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->assertSee('window.KH_LANG', false);
        $this->get('/lang/xx.js')->assertNotFound();
    }
}

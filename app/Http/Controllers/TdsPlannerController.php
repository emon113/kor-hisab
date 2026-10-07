<?php

namespace App\Http\Controllers;

use App\Http\Requests\TdsPlanRequest;
use App\Services\Tax\TdsPlanner;
use App\Support\Money;
use App\Support\TaxOptions;
use App\Support\TaxProfile;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TdsPlannerController extends Controller
{
    public function __construct(private readonly TdsPlanner $planner) {}

    public function index(Request $request): View
    {
        Money::useGrouping($request->cookie('kh_grouping'));
        $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Dhaka'));
        $year = $this->planner->currentYear($today);
        $done = $this->planner->defaultDone($year, $today);

        // The user's salary profile, or a worked example: ৳1,00,000 a month with two bonuses of one month's basic.
        $profile = TaxProfile::for($request->user());
        $pkg = $profile->package() ?? ['monthly' => 100000, 'bonus_each' => 55000];
        $bonusMonths = $this->bonusMonths($profile->hasSalary() ? (int) round($profile->get('bonus_count')) : 2);
        $months = [];
        foreach (TdsPlanner::MONTHS as $i => $key) {
            $months[$key] = [
                'salary' => $pkg['monthly'],
                'bonus' => in_array($i, $bonusMonths, true) ? $pkg['bonus_each'] : 0,
                'tds' => $done[$key] && ! $profile->hasSalary() ? 4000 : 0,
                'done' => $done[$key],
            ];
        }
        $perks = array_fill_keys(array_keys(config('salary.perks')), 0);
        $perks['employer_pf'] = ($pkg['employer_pf'] ?? 0) ?: ($profile->hasSalary() ? 0 : 72000);
        $input = $profile->taxInput() + [
            'year' => $year,
            'months' => $months,
            'perks' => $perks,
            'investments' => ['dps' => 60000],
            'strategy' => 'current',
            'monthly_cap' => 0,
        ];

        return view('tds.index', [
            'boot' => [
                'input' => $input,
                'plan' => $this->planner->plan($input),
                'options' => [
                    'years' => TaxOptions::years(),
                    'categories' => TaxOptions::categories(),
                    'instruments' => TaxOptions::instruments(),
                    'perks' => TaxOptions::perks(),
                ],
                'months' => TdsPlanner::MONTHS,
                'routes' => ['plan' => route('tds.plan'), 'calculator' => route('home')],
            ],
        ]);
    }

    /** Spread n bonuses across the year as a starting point; the user moves them to the real months. */
    private function bonusMonths(int $count): array
    {
        $count = max(0, min(12, $count));
        if ($count === 0) {
            return [];   // range(0, -1) would count down, not return nothing
        }

        return array_map(fn ($i) => intdiv(($i + 1) * 12, $count + 1) - 1, range(0, $count - 1));
    }

    public function plan(TdsPlanRequest $request): JsonResponse
    {
        Money::useGrouping($request->input('grouping'));

        return response()->json($this->planner->plan($request->validated()));
    }
}

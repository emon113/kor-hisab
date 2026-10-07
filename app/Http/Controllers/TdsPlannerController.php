<?php

namespace App\Http\Controllers;

use App\Http\Requests\TdsPlanRequest;
use App\Services\Tax\TdsPlanner;
use App\Support\Lang;
use App\Support\Money;
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

        // A worked example: ৳1,00,000 a month with two festival bonuses of one month's basic.
        $months = [];
        foreach (TdsPlanner::MONTHS as $key) {
            $months[$key] = ['salary' => 100000, 'bonus' => in_array($key, ['mar', 'jun'], true) ? 55000 : 0, 'tds' => $done[$key] ? 4000 : 0, 'done' => $done[$key]];
        }
        $input = ['year' => $year, 'category' => 'general', 'investment' => 0, 'months' => $months];

        $rules = config('tax');

        return view('tds.index', [
            'boot' => [
                'input' => $input,
                'plan' => $this->planner->plan($input),
                'options' => [
                    'years' => collect($rules['years'])->map(fn ($y, $k) => ['key' => $k, 'label' => Lang::label($y['label']), 'income_year' => Lang::label($y['income_year'])])->values(),
                    'categories' => collect($rules['categories'])->map(fn ($l, $k) => ['key' => $k, 'label' => __($l)])->values(),
                ],
                'months' => TdsPlanner::MONTHS,
                'routes' => ['plan' => route('tds.plan'), 'calculator' => route('home')],
            ],
        ]);
    }

    public function plan(TdsPlanRequest $request): JsonResponse
    {
        Money::useGrouping($request->input('grouping'));

        return response()->json($this->planner->plan($request->validated()));
    }
}

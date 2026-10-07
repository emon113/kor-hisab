<?php

namespace App\Http\Controllers;

use App\Http\Requests\TargetTaxRequest;
use App\Services\Tax\TargetTaxSolver;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TargetTaxController extends Controller
{
    public function __construct(private readonly TargetTaxSolver $solver) {}

    public function index(Request $request): View
    {
        Money::useGrouping($request->cookie('kh_grouping'));
        $components = config('tax.salary_components');
        $saved = $request->user()?->salary_ratios;

        $ratios = [];
        foreach ($components as $key => $c) {
            $ratios[$key] = round((float) ($saved[$key] ?? $c['ratio'] * 100), 4);
        }

        $input = [
            'target_tax' => 50000,
            'rebate_mode' => 'none',
            'investment' => 0,
            'year' => config('tax.default_year'),
            'category' => 'general',
            'ratios' => $ratios,
        ];

        return view('target.index', [
            'boot' => [
                'auth' => $request->user() !== null,
                'input' => $input,
                'result' => $this->solver->solve($input),
                'defaults' => collect($components)->map(fn ($c) => round($c['ratio'] * 100, 4))->all(),
                'labels' => collect($components)->map(fn ($c) => $c['label'])->all(),
                'options' => [
                    'years' => collect(config('tax.years'))->map(fn ($y, $k) => ['key' => $k, 'label' => $y['label']])->values(),
                    'categories' => collect(config('tax.categories'))->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values(),
                ],
                'routes' => [
                    'solve' => route('target.solve'),
                    'ratios' => $request->user() ? route('target.ratios') : null,
                ],
            ],
        ]);
    }

    public function solve(TargetTaxRequest $request): JsonResponse
    {
        Money::useGrouping($request->input('grouping'));

        return response()->json($this->solver->solve($request->validated()));
    }

    public function saveRatios(Request $request): JsonResponse
    {
        $rules = [];
        foreach (array_keys(config('tax.salary_components')) as $key) {
            $rules["ratios.{$key}"] = ['required', 'numeric', 'min:0', 'max:100'];
        }
        $data = $request->validate($rules);

        $request->user()->update(['salary_ratios' => array_map('floatval', $data['ratios'])]);

        return response()->json(['message' => 'Saved as your default salary split.']);
    }
}

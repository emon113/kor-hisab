<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalculationInputRequest;
use App\Models\Calculation;
use App\Services\Tax\TaxReport;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalculatorController extends Controller
{
    public function __construct(private readonly TaxReport $reports) {}

    /** The calculator, starting from a sensible example (or the browser's saved draft). */
    public function index(Request $request): View
    {
        return $this->page($request, null);
    }

    /** The calculator, opened on a saved calculation. */
    public function show(Request $request, Calculation $calculation): View
    {
        abort_unless($calculation->belongsToUser($request->user()), 404);

        return $this->page($request, $calculation);
    }

    public function calculate(CalculationInputRequest $request): JsonResponse
    {
        Money::useGrouping($request->input('grouping'));

        return response()->json($this->reports->build($request->taxInput()));
    }

    private function page(Request $request, ?Calculation $calculation): View
    {
        Money::useGrouping($request->cookie('kh_grouping'));

        $input = $calculation?->inputs ?? [
            'year' => config('tax.default_year'),
            'category' => 'general',
            'gross_income' => 1200000,
            'investments' => ['dps' => 60000],
            'tds_paid' => 0,
            'filing' => 'standard',
        ];

        $rules = config('tax');

        return view('calculator.index', [
            'calculation' => $calculation,
            'boot' => [
                'auth' => $request->user() !== null,
                'fresh' => $request->boolean('new'),
                'report' => $this->reports->build($input),
                'calculation' => $calculation ? [
                    'id' => $calculation->id,
                    'title' => $calculation->title,
                    'notes' => $calculation->notes,
                    'updated' => $calculation->updated_at?->diffForHumans(),
                ] : null,
                'options' => [
                    'years' => collect($rules['years'])->map(fn ($y, $k) => [
                        'key' => $k, 'label' => $y['label'], 'income_year' => $y['income_year'], 'projected' => (bool) $y['projected'],
                    ])->values(),
                    'categories' => collect($rules['categories'])->map(fn ($label, $k) => ['key' => $k, 'label' => $label])->values(),
                    'instruments' => collect($rules['instruments'])->map(fn ($i, $k) => ['key' => $k] + $i)->values(),
                    'filing' => collect($rules['filing_periods'])->map(fn ($f, $k) => ['key' => $k, 'label' => $f['label'], 'hint' => $f['hint']])->values(),
                ],
                'routes' => [
                    'calculate' => route('calculate'),
                    'store' => route('calculations.store'),
                    'update' => $calculation ? route('calculations.update', $calculation) : null,
                    'show' => url('/calculations'),
                    'login' => route('login'),
                    'register' => route('register'),
                    'fresh' => route('home', ['new' => 1]),
                ],
            ],
        ]);
    }
}

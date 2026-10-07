<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalculationInputRequest;
use App\Models\Calculation;
use App\Services\Tax\TaxReport;
use App\Support\Money;
use App\Support\TaxOptions;
use App\Support\TaxProfile;
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

        // A new calculation starts from the user's tax profile (and salary, if saved).
        $profile = TaxProfile::for($request->user());
        $input = $calculation?->inputs ?? $profile->taxInput() + [
            'year' => config('tax.default_year'),
            'gross_income' => $profile->package()['gross'] ?? 1200000,
            'investments' => ['dps' => 60000],
            'tds_paid' => 0,
            'filing' => 'standard',
        ];

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
                    'years' => TaxOptions::years(),
                    'categories' => TaxOptions::categories(),
                    'instruments' => TaxOptions::instruments(),
                    'filing' => TaxOptions::filing(),
                ],
                'routes' => [
                    'calculate' => route('calculate'),
                    'store' => route('calculations.store'),
                    'update' => $calculation ? route('calculations.update', $calculation) : null,
                    'show' => url('/calculations'),
                    'login' => route('login'),
                    'register' => route('register'),
                    'fresh' => route('home', ['new' => 1]),
                    'guide' => route('return-guide'),
                ],
            ],
        ]);
    }
}

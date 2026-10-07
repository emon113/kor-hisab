<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCalculationRequest;
use App\Models\Calculation;
use App\Services\Tax\TaxReport;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalculationController extends Controller
{
    public function __construct(private readonly TaxReport $reports) {}

    public function index(Request $request): View
    {
        Money::useGrouping($request->cookie('kh_grouping'));
        $calculations = $request->user()->calculations()->latest('updated_at')->get();
        $chronological = $calculations->sortBy('created_at')->values();

        return view('calculations.index', [
            'calculations' => $calculations,
            'stats' => [
                'count' => $calculations->count(),
                'latest' => $calculations->first(),
                'lowest' => $calculations->sortBy('liability')->first(),
                'average_rate' => $calculations->avg('effective_rate') ?? 0,
            ],
            'chart' => [
                'labels' => $chronological->map(fn ($c) => $c->title)->all(),
                'gross' => $chronological->pluck('gross_income')->all(),
                'liability' => $chronological->pluck('liability')->all(),
                'rate' => $chronological->map(fn ($c) => round($c->effective_rate * 100, 2))->all(),
            ],
        ]);
    }

    public function store(SaveCalculationRequest $request): JsonResponse
    {
        $report = $this->reports->build($request->taxInput());
        $calculation = $request->user()->calculations()->create(
            Calculation::attributesFromReport($report, $request->validated('title'), $request->validated('notes'))
        );

        return response()->json([
            'message' => __('Saved as “:title”.', ['title' => $calculation->title]),
            'calculation' => $this->payload($calculation),
        ], 201);
    }

    public function update(SaveCalculationRequest $request, Calculation $calculation): JsonResponse
    {
        abort_unless($calculation->belongsToUser($request->user()), 404);

        $report = $this->reports->build($request->taxInput());
        $calculation->update(
            Calculation::attributesFromReport($report, $request->validated('title'), $request->validated('notes'))
        );

        return response()->json([
            'message' => __('Changes saved.'),
            'calculation' => $this->payload($calculation),
        ]);
    }

    public function destroy(Request $request, Calculation $calculation): RedirectResponse|JsonResponse
    {
        abort_unless($calculation->belongsToUser($request->user()), 404);
        $title = $calculation->title;
        $calculation->delete();

        return $request->expectsJson()
            ? response()->json(['message' => __('Deleted “:title”.', ['title' => $title])])
            : redirect()->route('calculations.index')->with('status', __('Deleted “:title”.', ['title' => $title]));
    }

    public function compare(Request $request): View|RedirectResponse
    {
        Money::useGrouping($request->cookie('kh_grouping'));
        $ids = $request->validate([
            'a' => ['required', 'integer'],
            'b' => ['required', 'integer', 'different:a'],
        ]);

        $pair = $request->user()->calculations()->whereIn('id', [$ids['a'], $ids['b']])->get()->keyBy('id');
        if ($pair->count() !== 2) {
            return redirect()->route('calculations.index')->with('status', __('Pick two of your saved calculations to compare.'));
        }

        $a = $pair[$ids['a']];
        $b = $pair[$ids['b']];

        return view('calculations.compare', [
            'a' => $a,
            'b' => $b,
            'ra' => $this->reports->build($a->inputs),
            'rb' => $this->reports->build($b->inputs),
        ]);
    }

    private function payload(Calculation $calculation): array
    {
        return [
            'id' => $calculation->id,
            'title' => $calculation->title,
            'notes' => $calculation->notes,
            'url' => route('calculations.show', $calculation),
            'update_url' => route('calculations.update', $calculation),
            'updated' => Money::digits((string) $calculation->updated_at?->diffForHumans()),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WealthStatement;
use App\Services\Wealth\WealthReconciler;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WealthController extends Controller
{
    public function __construct(private readonly WealthReconciler $reconciler) {}

    public function index(Request $request): View
    {
        Money::useGrouping($request->cookie('kh_grouping'));
        $user = $request->user();
        $statements = $user->wealthStatements()->orderBy('tax_year')->get();

        $rows = $statements->map(fn (WealthStatement $st) => [
            'statement' => $st,
            'result' => $this->reconciler->reconcile($st->data(), $this->previousNetWealth($user, $st->tax_year, $st)),
        ]);

        $taken = $statements->pluck('tax_year')->all();
        $available = array_values(array_diff($this->reconciler->availableYears(config('tax.default_year')), $taken));

        return view('wealth.index', [
            'rows' => $rows->reverse()->values(),
            'available' => $available,
            'chart' => [
                'labels' => $rows->map(fn ($r) => WealthStatement::yearLabel($r['statement']->tax_year))->all(),
                'net' => $rows->map(fn ($r) => $r['result']['net_wealth'])->all(),
                'assets' => $rows->map(fn ($r) => $r['result']['totals']['assets'])->all(),
                'liabilities' => $rows->map(fn ($r) => $r['result']['totals']['liabilities'])->all(),
            ],
            'whoMustSubmit' => config('wealth.who_must_submit'),
        ]);
    }

    public function edit(Request $request, string $year): View
    {
        Money::useGrouping($request->cookie('kh_grouping'));
        $user = $request->user();
        $statement = $this->findOrAllow($user, $year);
        [$data, $prefill] = $statement ? [$statement->data(), []] : $this->prefill($user, $year);
        $previous = $this->previousNetWealth($user, $year, $statement);

        $groups = [];
        foreach (config('wealth.groups') as $key => $group) {
            $lines = [];
            foreach ($group['lines'] as $line => $def) {
                $lines[] = ['key' => $line, 'serial' => $def['serial'], 'label' => __($def['label']), 'hint' => $def['hint'] ? __($def['hint']) : null];
            }
            $groups[] = ['key' => $key, 'serial' => $group['serial'], 'title' => __($group['title']), 'lines' => $lines];
        }

        return view('wealth.edit', [
            'year' => $year,
            'statement' => $statement,
            'boot' => [
                'year' => $year,
                'yearLabel' => WealthStatement::yearLabel($year),
                'asOn' => WealthStatement::asOnLabel($year),
                'exists' => $statement !== null,
                'groups' => $groups,
                'data' => $this->reconciler->clean($data),
                'notes' => $statement?->notes,
                'previous' => $previous,
                'hasPreviousStatement' => $this->previousStatement($user, $year) !== null,
                'opening' => $statement?->opening_net_wealth,
                'prefill' => $prefill,
                'tolerance' => config('wealth.tolerance'),
                'routes' => [
                    'save' => route('wealth.update', $year),
                    'print' => route('wealth.print', $year),
                    'index' => route('wealth.index'),
                ],
            ],
        ]);
    }

    public function update(Request $request, string $year): JsonResponse
    {
        $user = $request->user();
        $statement = $this->findOrAllow($user, $year);

        $rules = [
            'opening_net_wealth' => ['nullable', 'numeric', 'min:-1000000000000', 'max:1000000000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
        foreach (config('wealth.groups') as $group => $def) {
            $rules[$group] = ['required', 'array:'.implode(',', array_keys($def['lines']))];
            $rules["{$group}.*"] = ['nullable', 'numeric', 'min:0', 'max:1000000000000'];
        }
        $valid = $request->validate($rules);

        $attributes = $this->reconciler->clean($valid) + [
            'opening_net_wealth' => isset($valid['opening_net_wealth']) ? (int) round($valid['opening_net_wealth']) : null,
            'notes' => $valid['notes'] ?? null,
        ];
        $statement ??= $user->wealthStatements()->make(['tax_year' => $year]);
        $statement->fill($attributes)->save();

        return response()->json([
            'message' => __('Statement for :year saved.', ['year' => WealthStatement::yearLabel($year)]),
            'result' => $this->reconciler->reconcile($statement->data(), $this->previousNetWealth($user, $year, $statement)),
        ]);
    }

    public function destroy(Request $request, string $year): RedirectResponse
    {
        $statement = $request->user()->wealthStatements()->where('tax_year', $year)->firstOrFail();
        $statement->delete();

        return redirect()->route('wealth.index')->with('status', __('Deleted the statement for :year.', ['year' => WealthStatement::yearLabel($year)]));
    }

    public function print(Request $request, string $year): View
    {
        Money::useGrouping($request->cookie('kh_grouping'));
        $user = $request->user();
        $statement = $user->wealthStatements()->where('tax_year', $year)->firstOrFail();

        return view('wealth.print', [
            'statement' => $statement,
            'groups' => config('wealth.groups'),
            'result' => $this->reconciler->reconcile($statement->data(), $this->previousNetWealth($user, $year, $statement)),
        ]);
    }

    // ------------------------------------------------------------------ helpers

    /** The user's statement for the year, or null for a year they may start; anything else is 404. */
    private function findOrAllow(User $user, string $year): ?WealthStatement
    {
        $statement = $user->wealthStatements()->where('tax_year', $year)->first();
        abort_unless($statement || in_array($year, $this->reconciler->availableYears(config('tax.default_year')), true), 404);

        return $statement;
    }

    private function previousStatement(User $user, string $year): ?WealthStatement
    {
        return $user->wealthStatements()->where('tax_year', WealthReconciler::previousYear($year))->first();
    }

    /** Last year's net wealth: from last year's statement, else the opening figure the user typed. */
    private function previousNetWealth(User $user, string $year, ?WealthStatement $statement): ?float
    {
        $previous = $this->previousStatement($user, $year);
        if ($previous) {
            return $this->reconciler->sum('assets', $previous->assets) - $this->reconciler->sum('liabilities', $previous->liabilities);
        }

        return $statement?->opening_net_wealth === null ? null : (float) $statement->opening_net_wealth;
    }

    /** A new year starts from last year's assets and liabilities, and income from that year's saved calculation. */
    private function prefill(User $user, string $year): array
    {
        $data = ['receipts' => [], 'expenses' => [], 'liabilities' => [], 'assets' => []];
        $notes = [];

        if ($previous = $this->previousStatement($user, $year)) {
            $data['assets'] = $previous->assets;
            $data['liabilities'] = $previous->liabilities;
            $notes['carried'] = WealthStatement::yearLabel($previous->tax_year);
        }

        $calculation = $user->calculations()->where('tax_year', $year)->latest('updated_at')->first();
        if ($calculation) {
            $taxable = (float) ($calculation->summary['taxable'] ?? 0);
            $data['receipts']['income_shown'] = $taxable;
            $data['receipts']['exempt_income'] = max(0, $calculation->gross_income - $taxable);
            $data['expenses']['tax_paid'] = (float) ($calculation->inputs['tds_paid'] ?? 0);
            $notes['income_from'] = $calculation->title;
        }

        return [$data, $notes];
    }
}

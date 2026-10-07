<?php

namespace App\Http\Controllers;

use App\Services\Tax\TaxEngine;
use App\Support\Lang;
use App\Support\Money;
use App\Support\TaxProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FilingCheckController extends Controller
{
    public function __construct(private readonly TaxEngine $engine) {}

    public function index(Request $request): View
    {
        Money::useGrouping($request->cookie('kh_grouping'));
        $year = config('tax.default_year');
        $check = config('filing_check');

        // The salary at which tax starts, per category: the income test without repeating tax maths in JS.
        $categories = [];
        foreach (config('tax.categories') as $key => $label) {
            $ctx = $this->engine->context($this->engine->normalize(['year' => $year, 'category' => $key]));
            $categories[] = [
                'key' => $key,
                'label' => __($label),
                'threshold' => $ctx['threshold'],
                'starts_at' => ceil($this->engine->grossForTaxable($ctx['threshold'], $ctx)),
            ];
        }

        return view('filing-check.index', [
            'sources' => $check['sources'],
            'checkedAt' => Money::digits(Carbon::parse($check['checked_at'])->translatedFormat('j F Y')),
            'boot' => [
                'yearLabel' => Lang::label(config("tax.years.{$year}.label")),
                'category' => TaxProfile::for($request->user())->get('category'),
                'categories' => $categories,
                'obligations' => $this->translate($check['obligations'], ['question', 'hint', 'reason']),
                'psr' => $this->translate($check['psr_services'], ['service']),
                'tinOnly' => $this->translate($check['tin_only'], ['service']),
                'steps' => $this->translate($check['next_steps'], ['title', 'text']),
                'routes' => ['calculator' => route('home'), 'guide' => route('return-guide'), 'tds' => route('tds')],
            ],
        ]);
    }

    /** Translate the given text fields of each config item. */
    private function translate(array $items, array $fields): array
    {
        foreach ($items as &$item) {
            foreach ($fields as $field) {
                if (is_string($item[$field] ?? null)) {
                    $item[$field] = __($item[$field]);
                }
            }
        }
        unset($item);

        return $items;
    }
}

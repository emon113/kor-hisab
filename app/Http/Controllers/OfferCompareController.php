<?php

namespace App\Http\Controllers;

use App\Http\Requests\OfferCompareRequest;
use App\Services\Tax\OfferComparer;
use App\Support\Lang;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferCompareController extends Controller
{
    public function __construct(private readonly OfferComparer $comparer) {}

    public function index(Request $request): View
    {
        Money::useGrouping($request->cookie('kh_grouping'));

        $input = [
            'year' => config('tax.default_year'),
            'category' => 'general',
            'investment_mode' => 'none',
            'investment' => 0,
            'offers' => [
                ['name' => __('Current job'), 'monthly' => 100000, 'basic_pct' => 60, 'bonus_count' => 2, 'bonus_base' => 'basic', 'other_annual' => 0, 'employer_pf' => 6000],
                ['name' => __('New offer'), 'monthly' => 120000, 'basic_pct' => 50, 'bonus_count' => 2, 'bonus_base' => 'basic', 'other_annual' => 0, 'employer_pf' => 0],
            ],
        ];
        $rules = config('tax');

        return view('offers.index', [
            'boot' => [
                'input' => $input,
                'result' => $this->comparer->compare($input),
                'options' => [
                    'years' => collect($rules['years'])->map(fn ($y, $k) => ['key' => $k, 'label' => Lang::label($y['label'])])->values(),
                    'categories' => collect($rules['categories'])->map(fn ($l, $k) => ['key' => $k, 'label' => __($l)])->values(),
                ],
                'routes' => ['compute' => route('offers.compute')],
            ],
        ]);
    }

    public function compute(OfferCompareRequest $request): JsonResponse
    {
        Money::useGrouping($request->input('grouping'));

        return response()->json($this->comparer->compare($request->validated()));
    }
}

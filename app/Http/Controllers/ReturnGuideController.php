<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalculationInputRequest;
use App\Models\Calculation;
use App\Services\Tax\ReturnGuide;
use App\Services\Tax\TaxReport;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnGuideController extends Controller
{
    public function __construct(private readonly TaxReport $reports) {}

    /** From the calculator's current inputs (query string), or a blank guide without them. */
    public function show(Request $request): View
    {
        Money::useGrouping($request->cookie('kh_grouping'));

        if (! $request->has('gross_income')) {
            return $this->page(null, null);
        }

        // Resolving the form request validates the query string the same way /calculate does.
        $input = app(CalculationInputRequest::class)->taxInput();

        return $this->page($this->reports->build($input), null);
    }

    public function forCalculation(Request $request, Calculation $calculation): View
    {
        abort_unless($calculation->belongsToUser($request->user()), 404);
        Money::useGrouping($request->cookie('kh_grouping'));

        return $this->page($this->reports->build($calculation->inputs), $calculation);
    }

    private function page(?array $report, ?Calculation $calculation): View
    {
        return view('return-guide.index', [
            'guide' => (new ReturnGuide(config('return_form')))->build($report),
            'report' => $report,
            'calculation' => $calculation,
            'checkedAt' => Money::digits(Carbon::parse(config('return_form.checked_at'))->translatedFormat('j F Y')),
        ]);
    }
}

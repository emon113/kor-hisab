<?php

namespace App\Http\Controllers;

use App\Services\Tax\TaxReport;
use App\Services\Tax\TaxTips;
use App\Support\Lang;
use App\Support\Money;
use App\Support\TaxProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TipsController extends Controller
{
    public function __construct(private readonly TaxTips $tips, private readonly TaxReport $reports) {}

    public function index(Request $request): View
    {
        Money::useGrouping($request->cookie('kh_grouping'));
        $profile = TaxProfile::for($request->user());

        // Signed-in users with a salary profile also see the tips that apply to them.
        $personal = null;
        if ($profile->hasSalary()) {
            $report = $this->reports->build($profile->taxInput() + [
                'year' => config('tax.default_year'),
                'gross_income' => $profile->package()['gross'],
                'investments' => ['provident_fund' => $profile->package()['employer_pf']],
            ]);
            $personal = [
                'tips' => array_values(array_filter($this->tips->for($report), fn ($t) => $t['saving'] !== null)),
                'tax' => $report['summary']['tax_after_rebate'],
                'gross' => $report['summary']['gross'],
                'year' => Lang::label($report['rules']['label']),
            ];
        }

        return view('tips.index', [
            'groups' => $this->tips->catalogue(),
            'personal' => $personal,
            'kinds' => [
                'invest' => __('Invest'),
                'claim' => __('Claim'),
                'file' => __('File'),
                'check' => __('Check'),
                'know' => __('Good to know'),
            ],
        ]);
    }
}

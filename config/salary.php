<?php

/*
|--------------------------------------------------------------------------
| Salary beyond the monthly pay: taxable extras
|--------------------------------------------------------------------------
| Everything here is part of "salary" for tax, as listed in Schedule 1(b) of
| the IT-11GA (2023) return (`line`). Values are the taxable amounts for the
| year as your employer reports them on the salary certificate: valuation
| rules for benefits in kind are not modelled, so users enter the figure.
|
| Shared by the TDS planner and the salary certificate report.
*/

return [

    'perks' => [
        'performance_bonus' => ['label' => 'Performance or incentive bonus', 'line' => '12', 'hint' => 'Any bonus beyond the festival bonuses.'],
        'overtime' => ['label' => 'Overtime', 'line' => '2', 'hint' => null],
        'leave_encashment' => ['label' => 'Leave encashment', 'line' => '12', 'hint' => null],
        'arrear' => ['label' => 'Arrear or advance salary', 'line' => '3', 'hint' => 'Only if it was not taxed in an earlier year.'],
        'accommodation' => ['label' => 'Housing provided by the employer', 'line' => '8', 'hint' => 'The taxable value of rent-free or subsidised housing.'],
        'transport' => ['label' => 'Car or transport provided by the employer', 'line' => '9', 'hint' => 'The taxable value of a company car or transport for personal use.'],
        'other_facility' => ['label' => 'Other benefits: phone, utilities, club', 'line' => '10', 'hint' => null],
        'employer_pf' => ['label' => 'Employer’s contribution to provident fund', 'line' => '11', 'hint' => 'Taxed as salary, and also counts as an investment for the rebate.'],
        'share_scheme' => ['label' => 'Employee share scheme', 'line' => '7', 'hint' => null],
        'other' => ['label' => 'Anything else taxable', 'line' => '12', 'hint' => null],
    ],
];

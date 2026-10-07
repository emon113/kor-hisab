<?php

/*
|--------------------------------------------------------------------------
| Bangladesh personal income tax rules (salaried individuals)
|--------------------------------------------------------------------------
| Source: Income Tax Act 2023 as amended by the Finance Act 2026
| (PwC Bangladesh, "Finance Act, 2026: Key Amendments", June 2026).
|
| Every number the engine uses lives here. When NBR changes a rule, edit it
| once in this file — the calculator, predictions and charts all follow.
| Slab widths are in BDT; a null width means "the rest of the income".
*/

$fa2026 = [
    'thresholds' => [
        'general' => 400000,
        'women_senior' => 450000,
        'disabled' => 525000,
        'third_gender' => 525000,
        'freedom_fighter' => 550000,
    ],
    'slabs' => [
        [300000, 0.10],
        [400000, 0.15],
        [500000, 0.20],
        [2000000, 0.25],
        [null, 0.30],
    ],
    'salary_exemption' => ['fraction' => 1 / 3, 'cap' => 500000],
    'rebate' => ['rate' => 0.10, 'income_pct' => 0.03, 'cap' => 750000],
    'minimum_tax' => 5000,
    'minimum_tax_new' => 1000,
    'projected' => false,
];

return [

    'default_year' => '2026-27',

    'years' => [
        '2026-27' => $fa2026 + [
            'label' => 'AY 2026-27',
            'income_year' => 'Jul 2025 – Jun 2026',
            'income_year_end' => '2026-06-30',
        ],
        '2027-28' => $fa2026 + [
            'label' => 'AY 2027-28',
            'income_year' => 'Jul 2026 – Jun 2027',
            'income_year_end' => '2027-06-30',
        ],
        // Enacted 5-year roadmap. Rebate and exemption rules are assumed unchanged.
        '2028-30' => array_merge($fa2026, [
            'label' => 'AY 2028-29 & 2029-30',
            'income_year' => 'Jul 2027 – Jun 2029',
            'income_year_end' => '2028-06-30',
            'projected' => true,
            'thresholds' => [
                'general' => 450000, 'women_senior' => 500000, 'disabled' => 575000,
                'third_gender' => 575000, 'freedom_fighter' => 600000,
            ],
            'slabs' => [[300000, 0.10], [400000, 0.15], [500000, 0.20], [2000000, 0.25], [26350000, 0.30], [null, 0.35]],
        ]),
        '2030-31' => array_merge($fa2026, [
            'label' => 'AY 2030-31',
            'income_year' => 'Jul 2029 – Jun 2030',
            'income_year_end' => '2030-06-30',
            'projected' => true,
            'thresholds' => [
                'general' => 500000, 'women_senior' => 550000, 'disabled' => 625000,
                'third_gender' => 625000, 'freedom_fighter' => 650000,
            ],
            'slabs' => [[300000, 0.10], [400000, 0.15], [500000, 0.20], [2000000, 0.25], [26300000, 0.30], [null, 0.35]],
        ]),
    ],

    'categories' => [
        'general' => 'General',
        'women_senior' => 'Woman or senior citizen (65+)',
        'disabled' => 'Person with disability',
        'third_gender' => 'Third gender',
        'freedom_fighter' => 'War-wounded freedom fighter or July fighter',
    ],

    // Added to the threshold for each disabled child or dependent.
    'extra_per_disabled_child' => 50000,

    // Rebate-eligible investments (Income Tax Act 2023, Sixth Schedule Part 3).
    // cap = maximum amount counted per year (null = no fixed limit). common = shown first.
    // Order matters: the rebate optimiser fills capped instruments first, in this order.
    'instruments' => [
        'dps' => ['label' => 'DPS', 'cap' => 120000, 'hint' => 'Monthly deposit pension scheme', 'common' => true],
        'savings_certificate' => ['label' => 'Savings certificate', 'cap' => 500000, 'hint' => 'Sanchaypatra or government securities', 'common' => true],
        'mutual_fund' => ['label' => 'Mutual fund', 'cap' => 500000, 'hint' => 'Unit funds and trusts', 'common' => true],
        'shares' => ['label' => 'Listed shares', 'cap' => null, 'hint' => 'New investment in the share market', 'common' => true],
        'provident_fund' => ['label' => 'Provident fund', 'cap' => null, 'hint' => 'Your and your employer’s contributions to a recognised fund', 'common' => false],
        'life_insurance' => ['label' => 'Life insurance premium', 'cap' => null, 'hint' => 'For you, your spouse or minor children; premiums up to 10% of the sum assured count', 'common' => false],
        'pension' => ['label' => 'Universal Pension Scheme', 'cap' => null, 'hint' => 'Contributions to the government’s Sarbojanin Pension', 'common' => false],
        'zakat_donation' => ['label' => 'Zakat and approved donations', 'cap' => null, 'hint' => 'Zakat to the Zakat Fund, or donations to approved hospitals and institutions', 'common' => false],
    ],

    // Return-filing incentive / additional tax (Finance Act 2026), counted from the end of the income year.
    'filing_periods' => [
        'early' => ['label' => 'Jul 1 – Sep 30', 'hint' => '5% rebate, up to ৳25,000', 'rate' => -0.05, 'min' => 0, 'max' => 25000, 'ends' => '09-30'],
        'standard' => ['label' => 'Oct 1 – Dec 31', 'hint' => 'No adjustment', 'rate' => 0.0, 'min' => 0, 'max' => null, 'ends' => '12-31'],
        'late' => ['label' => 'Jan 1 – Mar 31', 'hint' => '2% extra, at least ৳3,000', 'rate' => 0.02, 'min' => 3000, 'max' => null, 'ends' => '03-31'],
        'very_late' => ['label' => 'Apr 1 – Jun 30', 'hint' => '5% extra, at least ৳5,000', 'rate' => 0.05, 'min' => 5000, 'max' => null, 'ends' => '06-30'],
    ],

    // Default salary structure used by "Target tax → income". Ratios of gross income.
    'salary_components' => [
        'basic' => ['label' => 'Basic', 'ratio' => 0.548249],
        'house_rent' => ['label' => 'House Rent', 'ratio' => 0.274125],
        'medical' => ['label' => 'Medical', 'ratio' => 0.054825],
        'conveyance' => ['label' => 'Conveyance', 'ratio' => 0.036550],
        'festival_bonus' => ['label' => 'Festive Bonus', 'ratio' => 0.086251],
        'other_bonus' => ['label' => 'Others Bonuses', 'ratio' => 0.0],
        'overtime' => ['label' => 'Over Time', 'ratio' => 0.0],
    ],
];

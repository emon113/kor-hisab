<?php

/*
|--------------------------------------------------------------------------
| Statement of assets, liabilities and expenses (IT-10B / IT-10BB 2023)
|--------------------------------------------------------------------------
| Lines and serials follow NBR's IT-10B (2023) and IT-10BB (2023), read from
| the official return PDF. The form's own arithmetic is the check we run:
|
|   Net wealth this year (5) = last year's net wealth (2) + sources of fund (1)
|                              − expenses and losses (4)
|
| and that must equal total assets (10) − liabilities (6). A difference is
| wealth the return does not explain, which is what prompts NBR questions.
*/

return [

    'checked_at' => '2026-10-08',
    'sources' => [
        ['label' => 'NBR: IT-11GA (2023) return form for natural persons (PDF)', 'url' => 'https://nbr.gov.bd/uploads/form/Individual_Return_IT-11Ga%282023%292.pdf'],
    ],

    // Years offered when starting a statement (assessment years).
    'first_year' => '2023-24',
    'years_ahead' => 1,

    // A gap below the larger of these is treated as rounding.
    'tolerance' => ['min' => 10000, 'share_of_sources' => 0.01],

    'groups' => [
        'receipts' => [
            'title' => 'Sources of fund',
            'serial' => '1',
            'lines' => [
                'income_shown' => ['serial' => '1(a)', 'label' => 'Total income shown in the return', 'hint' => 'Line 11 of the return: your taxable income.'],
                'exempt_income' => ['serial' => '1(b)', 'label' => 'Tax-exempt income', 'hint' => 'Includes the tax-free part of your salary.'],
                'gifts_other' => ['serial' => '1(c)', 'label' => 'Gifts received and other receipts', 'hint' => 'Gifts, inheritance, sale proceeds not counted as income.'],
            ],
        ],
        'expenses' => [
            'title' => 'Expenses relating to lifestyle (IT-10BB)',
            'serial' => '4',
            'lines' => [
                'food_clothing' => ['serial' => '10BB-1', 'label' => 'Personal and family food, clothing and other essentials', 'hint' => null],
                'housing' => ['serial' => '10BB-2', 'label' => 'Housing expense', 'hint' => 'Rent, maintenance, service charge.'],
                'transport' => ['serial' => '10BB-3', 'label' => 'Personal transport expense', 'hint' => null],
                'utilities' => ['serial' => '10BB-4', 'label' => 'Utilities: electricity, gas, water, phone, mobile, internet', 'hint' => null],
                'education' => ['serial' => '10BB-5', 'label' => 'Education expense', 'hint' => null],
                'travel' => ['serial' => '10BB-6', 'label' => 'Local and foreign travel, vacation', 'hint' => null],
                'festival' => ['serial' => '10BB-7', 'label' => 'Festival and other special expense', 'hint' => null],
                'tax_paid' => ['serial' => '10BB-8', 'label' => 'Tax deducted at source and tax paid with last year’s return', 'hint' => 'Include tax deducted on Sanchaypatra profit.'],
                'loan_interest' => ['serial' => '10BB-9', 'label' => 'Interest paid on personal loans', 'hint' => null],
                'other_loss' => ['serial' => '4(b)', 'label' => 'Gifts given, expenses or losses not in IT-10BB', 'hint' => null],
            ],
        ],
        'liabilities' => [
            'title' => 'Personal liabilities',
            'serial' => '6',
            'lines' => [
                'institutional' => ['serial' => '6(a)', 'label' => 'Institutional liabilities', 'hint' => 'Home loan, bank or card loans: the balance at year end.'],
                'non_institutional' => ['serial' => '6(b)', 'label' => 'Non-institutional liabilities', 'hint' => 'Money borrowed from people.'],
                'other' => ['serial' => '6(c)', 'label' => 'Other liabilities', 'hint' => null],
            ],
        ],
        'assets' => [
            'title' => 'Assets',
            'serial' => '8',
            'lines' => [
                'business' => ['serial' => '8(a)', 'label' => 'Business assets, less business liabilities', 'hint' => null],
                'director_shares' => ['serial' => '8(b)', 'label' => 'Director’s shareholdings in companies', 'hint' => null],
                'partnership' => ['serial' => '8(c)', 'label' => 'Capital in a partnership firm', 'hint' => null],
                'property' => ['serial' => '8(d)', 'label' => 'Non-agricultural property: land, house, flat', 'hint' => 'At cost, including registration and legal expense.'],
                'agricultural' => ['serial' => '8(e)', 'label' => 'Agricultural property', 'hint' => 'At cost.'],
                'shares' => ['serial' => '8(f)(i)', 'label' => 'Shares, debentures, bonds, unit certificates', 'hint' => null],
                'sanchaypatra_dps' => ['serial' => '8(f)(ii)', 'label' => 'Sanchaypatra and DPS', 'hint' => 'Balance at year end.'],
                'loans_given' => ['serial' => '8(f)(iii)', 'label' => 'Loans given to others', 'hint' => 'The form asks for each borrower’s name and NID.'],
                'deposits' => ['serial' => '8(f)(iv)', 'label' => 'Savings and term deposits', 'hint' => 'FDRs and savings accounts.'],
                'provident_fund' => ['serial' => '8(f)(v)', 'label' => 'Provident fund or other fund', 'hint' => null],
                'other_investment' => ['serial' => '8(f)(vi)', 'label' => 'Other investments', 'hint' => null],
                'vehicle' => ['serial' => '8(g)', 'label' => 'Motor vehicles', 'hint' => 'At cost, including registration.'],
                'ornaments' => ['serial' => '8(h)', 'label' => 'Ornaments', 'hint' => 'Gold and jewellery; the form also asks the quantity.'],
                'furniture' => ['serial' => '8(i)', 'label' => 'Furniture and electronics', 'hint' => null],
                'other_assets' => ['serial' => '8(j)', 'label' => 'Other assets', 'hint' => null],
                'bank' => ['serial' => '8(k)(i)', 'label' => 'Bank balance', 'hint' => null],
                'cash' => ['serial' => '8(k)(ii)', 'label' => 'Cash in hand', 'hint' => null],
                'cash_other' => ['serial' => '8(k)(iii)', 'label' => 'Other funds outside business', 'hint' => 'Mobile wallets and similar.'],
                'abroad' => ['serial' => '9', 'label' => 'Assets outside Bangladesh', 'hint' => null],
            ],
        ],
    ],

    // From the IT-10B (2023) header.
    'who_must_submit' => [
        ['text' => 'Every public servant.'],
        ['text' => 'Anyone whose total assets at home and abroad exceed ৳40,00,000.'],
        ['text' => 'Anyone with less, who owns a motor car, has invested in a house or flat in a city corporation area, owns assets outside Bangladesh, or is a shareholder director of a company.'],
    ],
];

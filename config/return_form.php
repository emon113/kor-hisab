<?php

/*
|--------------------------------------------------------------------------
| Return form guide: where each calculated figure goes on the return
|--------------------------------------------------------------------------
| Serial numbers and line labels follow NBR's IT-11GA (2023) return for a
| natural person, read from the official PDF (see sources). Labels are the
| form's English wording; the Bangla form uses the same serial numbers.
|
| `source` points into the values ReturnGuide builds from a TaxReport:
|   summary.*   TaxReport summary figures
|   inv.<key>   amount invested in an instrument (config/tax.php instruments)
|   investments.invested / investments.eligible
|   computed.*  small derived figures (net tax after rebate, tax paid with return…)
| An array source is the sum of its parts.
*/

return [

    'form' => 'IT-11GA (2023)',
    'checked_at' => '2026-10-08',

    'sources' => [
        ['label' => 'NBR: IT-11GA (2023) return form for natural persons (PDF)', 'url' => 'https://nbr.gov.bd/uploads/form/Individual_Return_IT-11Ga%282023%292.pdf'],
        ['label' => 'NBR: income tax forms', 'url' => 'https://nbr.gov.bd/form/income-tax/eng'],
        ['label' => 'The Daily Star: How to file income tax return online, a step by step guideline', 'url' => 'https://www.thedailystar.net/business/news/how-file-income-tax-return-online-step-step-guideline-3997576'],
        ['label' => 'Prothom Alo: How to file return online, step by step guide', 'url' => 'https://en.prothomalo.com/business/bj97f0lu37'],
    ],

    'sections' => [
        'statement' => [
            'title' => 'Return: Statement of Income and Tax',
            'intro' => 'Page 2 and 3 of the return. If salary is your only income, only these lines apply to you; leave the other income lines blank.',
            'rows' => [
                ['serial' => '1', 'label' => 'Income from Employment (annex Schedule 1)', 'source' => 'summary.taxable', 'note' => 'Your taxable salary: the same figure as Schedule 1, line 15.'],
                ['serial' => '11', 'label' => 'Total Income (Aggregate of Serial 1 to 10)', 'source' => 'summary.taxable', 'note' => 'Equal to line 1 when salary is your only income.'],
                ['serial' => '12', 'label' => 'Gross Tax on Taxable Income', 'source' => 'summary.gross_tax', 'note' => 'Tax worked out slab by slab, before the rebate.'],
                ['serial' => '13', 'label' => 'Tax Rebate (annex Schedule 5)', 'source' => 'summary.rebate', 'note' => 'From Schedule 5, line 12.'],
                ['serial' => '14', 'label' => 'Net Tax after Rebate (12 – 13)', 'source' => 'computed.net_after_rebate', 'note' => null],
                ['serial' => '15', 'label' => 'Minimum Tax', 'source' => 'summary.min_tax', 'note' => 'Applies only when taxable income is above your tax-free limit.'],
                ['serial' => '16', 'label' => 'Tax Payable (Higher of 14 and 15)', 'source' => 'summary.tax_after_rebate', 'note' => null],
                ['serial' => '18', 'label' => 'Delay Interest, Penalty or any other amount Under Income Tax Act (if any)', 'source' => 'computed.late_charge', 'note' => 'The additional tax for filing after 31 December, if that is when you file.'],
                ['serial' => '19', 'label' => 'Total Amount Payable (16 + 17 + 18)', 'source' => 'computed.total_payable', 'note' => 'An early-filing rebate is not a separate line on this form: check the figure the e-return system shows.'],
                ['serial' => '20', 'label' => 'Tax Deducted or Collected at Source (attach proof)', 'source' => 'summary.tds_paid', 'note' => 'TDS from your employer’s certificate, plus tax deducted on interest or other sources.'],
                ['serial' => '23', 'label' => 'Tax Paid with this Return', 'source' => 'computed.paid_with_return', 'note' => 'What is still due after TDS. Pay it before or while submitting.'],
                ['serial' => '24', 'label' => 'Total Tax Paid and Adjusted (20 + 21 + 22 + 23)', 'source' => 'computed.total_paid', 'note' => null],
                ['serial' => '25', 'label' => 'Excess Payment (24 – 19)', 'source' => 'computed.excess', 'note' => 'More tax was deducted than you owe: this is your refund claim.'],
                ['serial' => '26', 'label' => 'Tax Exempted / Tax Free Income (attach proof)', 'source' => 'summary.exemption', 'note' => 'The tax-free part of your salary (Schedule 1, line 14). Add any other tax-free income you received.'],
            ],
        ],
        'schedule1' => [
            'title' => 'Schedule 1: Income from Employment (part b, non-government)',
            'intro' => 'For salaries outside the government pay scale. Split lines 1 to 12 from your employer’s salary statement; the totals below must match.',
            'rows' => [
                ['serial' => '13', 'label' => 'Total Salary Received (aggregate of 1 to 12)', 'source' => 'summary.gross', 'note' => 'Basic, allowances, bonuses, perquisites and employer PF contribution together.'],
                ['serial' => '14', 'label' => 'Exempted Amount (as per Part 1 of 6th Schedule)', 'source' => 'summary.exemption', 'note' => 'One-third of total salary, capped at ৳5,00,000.'],
                ['serial' => '15', 'label' => 'Total Income from Salary (13 – 14)', 'source' => 'summary.taxable', 'note' => 'Carries to line 1 of the return.'],
            ],
        ],
        'schedule5' => [
            'title' => 'Schedule 5: Investment Tax Credit',
            'intro' => 'Enter what you actually invested during the income year. The rebate on line 12 already respects each instrument’s limit.',
            'rows' => [
                ['serial' => '2', 'label' => 'Contribution to Deposit Pension Scheme', 'source' => 'inv.dps', 'note' => null],
                ['serial' => '3', 'label' => 'Investment in Government Securities, Unit Certificate, Mutual Fund, ETF or Joint Investment Scheme Unit Certificate', 'source' => ['inv.savings_certificate', 'inv.mutual_fund'], 'note' => 'Sanchaypatra and mutual funds together.'],
                ['serial' => '4', 'label' => 'Investment in Securities listed with Approved Stock Exchange', 'source' => 'inv.shares', 'note' => null],
                ['serial' => '11', 'label' => 'Total Investment (aggregate of 1 to 10)', 'source' => 'investments.invested', 'note' => 'Add life insurance premiums, provident fund contributions and other lines 1, 5 to 10 if you have them.'],
                ['serial' => '12', 'label' => 'Amount of Tax Rebate', 'source' => 'summary.rebate', 'note' => 'Carries to line 13 of the return.'],
            ],
        ],
    ],

    'steps' => [
        ['title' => 'Register on the e-return portal', 'text' => 'Go to etaxnbr.gov.bd and choose e-Return. First time: register with your TIN and your NID-verified mobile number, confirm the one-time code and set a password.'],
        ['title' => 'Start a new return', 'text' => 'Sign in, open Return Submission and pick the assessment year. Choose the regular return; a single-page return is offered only if you qualify.'],
        ['title' => 'Enter your salary', 'text' => 'Fill Schedule 1 from your salary statement. The tax-free amount and taxable salary should match the figures on this page.'],
        ['title' => 'Enter investments and tax paid', 'text' => 'Fill Schedule 5 with your investments and the return page with the TDS your employer deducted.'],
        ['title' => 'Complete assets and expenses', 'text' => 'Fill the statement of assets and liabilities (IT-10B) and lifestyle expenses (IT-10BB). Your net wealth change should be explained by your income.'],
        ['title' => 'Pay any balance and submit', 'text' => 'Pay what is still due through the portal’s online payment options, then submit. Download the acknowledgement: it is your proof of return submission.'],
    ],

    'documents' => [
        ['label' => 'Salary statement or certificate from your employer, showing TDS deducted'],
        ['label' => 'TIN certificate and National ID'],
        ['label' => 'Bank statements, and certificates for interest earned on savings instruments'],
        ['label' => 'Proof of investments: DPS statement, Sanchaypatra certificate, mutual fund or share statements, insurance premium receipts'],
        ['label' => 'Proof of any tax paid in advance or deducted at source on other income'],
        ['label' => 'Last year’s return or acknowledgement, if you filed one'],
    ],
];

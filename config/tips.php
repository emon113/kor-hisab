<?php

/*
|--------------------------------------------------------------------------
| Legal ways to lower your tax
|--------------------------------------------------------------------------
| Every tip here is a choice the Income Tax Act 2023 gives the taxpayer, not
| a way around it. App\Services\Tax\TaxTips decides which apply to a given
| report and works out the saving; this file only holds the words.
|
|   kind      invest | file | claim | check | know  (badge and sort order)
|   title     general name, used on the catalogue page
|   about     general explanation
|   headline  personalised line with :placeholders, shown in the calculator
|   law       where the rule comes from
*/

return [

    'rebate_gap' => [
        'kind' => 'invest', 'icon' => 'target',
        'title' => 'Invest enough for the full rebate',
        'about' => 'Eligible investments earn a rebate on your tax, up to a limit set by your income. Money invested up to that limit is the most reliable saving the law offers a salaried person.',
        'headline' => 'Invest :gap more to pay :saving less tax',
        'law' => 'Income Tax Act 2023, Sixth Schedule, Part 3',
    ],
    'provident_fund' => [
        'kind' => 'claim', 'icon' => 'coins',
        'title' => 'Claim your provident fund contributions',
        'about' => 'If your employer runs a recognised provident fund, your own and your employer’s contributions both count as investment for the rebate. They are easy to forget because nothing is bought separately.',
        'headline' => 'Count your provident fund as investment',
        'law' => 'Return Schedule 5, line 6',
    ],
    'life_insurance' => [
        'kind' => 'invest', 'icon' => 'shield',
        'title' => 'Life insurance premiums count',
        'about' => 'Premiums for a life policy on you, your spouse or your minor children count as investment, up to 10% of the sum assured. Protection you may already need also lowers your tax.',
        'headline' => 'A life insurance premium can fill part of your :gap gap',
        'law' => 'Income Tax Act 2023, Sixth Schedule, Part 3',
    ],
    'pension' => [
        'kind' => 'invest', 'icon' => 'sprout',
        'title' => 'Universal Pension Scheme contributions count',
        'about' => 'Contributions to the government’s Universal (Sarbojanin) Pension Scheme count as investment with no fixed limit, and build a pension at the same time.',
        'headline' => 'Pension contributions count towards your :gap gap',
        'law' => 'Income Tax Act 2023, Sixth Schedule, Part 3',
    ],
    'zakat' => [
        'kind' => 'claim', 'icon' => 'gift',
        'title' => 'Zakat and approved donations count',
        'about' => 'Zakat paid to the government Zakat Fund, and donations to approved hospitals, welfare and educational institutions, count as investment for the rebate. Paying through these channels turns giving you already do into a rebate.',
        'headline' => 'Zakat or approved donations can count for the rebate',
        'law' => 'Return Schedule 5, lines 9 and 10',
    ],
    'early_filing' => [
        'kind' => 'file', 'icon' => 'calendar',
        'title' => 'File between July and September',
        'about' => 'Returns filed from 1 July to 30 September get a 5% rebate on the tax, up to ৳25,000. Filing after December costs extra.',
        'headline' => 'File by 30 September :year to save :saving',
        'law' => 'Finance Act 2026',
    ],
    'late_filing' => [
        'kind' => 'file', 'icon' => 'alert',
        'title' => 'Do not file late',
        'about' => 'Filing after 31 December adds 2% (at least ৳3,000), rising to 5% (at least ৳5,000) after March.',
        'headline' => 'Filing by 31 December saves :saving',
        'law' => 'Finance Act 2026',
    ],
    'first_return' => [
        'kind' => 'check', 'icon' => 'sprout',
        'title' => 'First return: lower minimum tax',
        'about' => 'Your first ever return has a minimum tax of ৳1,000 instead of ৳5,000. If this is your first return, say so when you file.',
        'headline' => 'If this is your first return, the minimum tax is :saving less',
        'law' => 'Finance Act 2026',
    ],
    'category' => [
        'kind' => 'check', 'icon' => 'user',
        'title' => 'Check your taxpayer category',
        'about' => 'Women, people aged 65 or more, persons with disability, third-gender taxpayers and war-wounded freedom fighters or July fighters have higher tax-free limits. Make sure the right category is on your return.',
        'headline' => 'If you are a woman or 65 or older, you pay :saving less',
        'law' => 'Finance Act 2026',
    ],
    'disabled_child' => [
        'kind' => 'check', 'icon' => 'shield',
        'title' => 'Parents of a disabled child: higher limit',
        'about' => 'Each child or dependent with a disability adds ৳50,000 to the parent’s tax-free limit. If both parents are taxpayers, only one of them claims it.',
        'headline' => 'A disabled child or dependent would save you :saving',
        'law' => 'Finance Act 2026',
    ],
    'household' => [
        'kind' => 'know', 'icon' => 'wallet',
        'title' => 'Couples: each has a separate limit',
        'about' => 'Spouses are taxed separately. Each has their own tax-free limit and their own rebate room, so tax-saving investments go furthest in the name of whichever of you still has room.',
        'headline' => 'Spouses have separate rebate room',
        'law' => 'Income Tax Act 2023',
    ],
    'salary_structure' => [
        'kind' => 'know', 'icon' => 'percent',
        'title' => 'Your salary split does not change your tax',
        'about' => 'One-third of total salary is tax-free, up to ৳5,00,000, whatever the split between basic, house rent and other allowances. When you negotiate, compare gross pay and benefits instead.',
        'headline' => 'Basic or allowance: the tax is the same',
        'law' => 'Income Tax Act 2023, Sixth Schedule, Part 1',
    ],
    'over_invest' => [
        'kind' => 'know', 'icon' => 'alert',
        'title' => 'Do not over-invest for tax',
        'about' => 'Investment above the rebate limit, or above an instrument’s cap, earns no rebate this year. Keep extra savings where they suit you best, not in locked tax products.',
        'headline' => ':amount of your investment earns no rebate',
        'law' => 'Income Tax Act 2023, Sixth Schedule, Part 3',
    ],
    'minimum_tax' => [
        'kind' => 'know', 'icon' => 'alert',
        'title' => 'At the minimum tax, investing does not help',
        'about' => 'Once your tax is down to the minimum, more investment cannot lower it. Buy tax-saving products only when there is rebate room left.',
        'headline' => 'You are at the minimum tax of :amount',
        'law' => 'Finance Act 2026',
    ],
    'declare_to_hr' => [
        'kind' => 'claim', 'icon' => 'receipt',
        'title' => 'Declare investments to your employer',
        'about' => 'Your employer deducts TDS each month from your estimated tax. Declaring planned investments with proof lets them deduct for the lower tax, so the saving reaches you every month instead of as a refund.',
        'headline' => 'Get the rebate in your monthly pay',
        'law' => 'Income Tax Act 2023, section 86',
    ],
    'refund' => [
        'kind' => 'claim', 'icon' => 'receipt',
        'title' => 'Claim what was over-deducted',
        'about' => 'If more tax was deducted than you owe, claim the excess in your return. Salaried taxpayers now get refunds without a separate application.',
        'headline' => 'Claim your :amount refund',
        'law' => 'Return line 25',
    ],
    'keep_proof' => [
        'kind' => 'know', 'icon' => 'folder',
        'title' => 'Keep the proof',
        'about' => 'A rebate is allowed only for investments you can prove: certificates, statements and receipts in your name. Keep them with your copy of the return.',
        'headline' => 'Keep certificates for every investment',
        'law' => 'Income Tax Act 2023',
    ],
];

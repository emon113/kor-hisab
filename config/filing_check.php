<?php

/*
|--------------------------------------------------------------------------
| "Do I need to file a return?"
|--------------------------------------------------------------------------
| Obligations follow section 166 of the Income Tax Act 2023. The income test
| uses the tax-free threshold from config/tax.php for the chosen category.
|
| Since the Finance Ordinance 2025, a person who is not required to file is
| also exempt from giving proof of return submission (PSR), and some services
| accept a TIN instead. The PSR list below is therefore shown as "what you
| will need it for", not as a separate reason to file. Section 264 is long and
| changes with each budget: only common examples for individuals are listed.
*/

return [

    'checked_at' => '2026-10-08',

    'sources' => [
        ['label' => 'Income Tax Act 2023, section 166 (return filing)', 'url' => 'https://bdtaxhelp.com/index.php/income-tax-act-2023/section/166'],
        ['label' => 'ICAB: Finance Ordinance 2025 changes to the Income Tax Act (PSR exemption)', 'url' => 'https://www.icab.org.bd/icabadmin/uploads/ckeditor/4053Finance%20Ordinance%202025_Income%20Tax_ICAB_3Sept25.pdf'],
        ['label' => 'TaxpertBD: personal tax changes through the Finance Ordinance 2025 (TIN instead of PSR)', 'url' => 'https://taxpertbd.com/personal-tax-changes-2025/'],
        ['label' => 'TaxVATPoint: services requiring PSR under section 264', 'url' => 'https://www.taxvatpoint.com/proof-of-submission-of-return-psr-tax-act/'],
        ['label' => 'NBR e-TIN registration', 'url' => 'https://secure.incometax.gov.bd'],
    ],

    // Each "yes" is a reason you must file. The income question is built from config/tax.php.
    'obligations' => [
        ['id' => 'assessed', 'question' => 'Did you file a return, or were you assessed for tax, in any of the last three years?', 'hint' => null,
            'reason' => 'You were assessed in one of the last three years, so you must keep filing.'],
        ['id' => 'company', 'question' => 'Do you work for a company, as an employee or as a shareholder director?', 'hint' => 'Any private or public limited company, including multinationals and banks.',
            'reason' => 'Employees and shareholder directors of a company must file, whatever their income.'],
        ['id' => 'government', 'question' => 'Are you a government employee?', 'hint' => null,
            'reason' => 'Government employees must file.'],
        ['id' => 'executive', 'question' => 'Do you hold an executive or managerial post in any business?', 'hint' => null,
            'reason' => 'People in executive or managerial posts must file.'],
        ['id' => 'partner', 'question' => 'Are you a partner in a firm, or a member of an association of persons?', 'hint' => null,
            'reason' => 'Partners of a firm and members of an association must file.'],
        ['id' => 'special_income', 'question' => 'Do you receive income that the law exempts from tax or taxes at a reduced rate?', 'hint' => 'Income covered by the Sixth Schedule, such as some investment or export income.',
            'reason' => 'Income that is exempt or taxed at a reduced rate still has to be shown in a return.'],
    ],

    // Common services that ask for proof of return submission (PSR) from people who file.
    'psr_services' => [
        ['service' => 'A loan of more than ৳5 lakh from a bank or financial institution'],
        ['service' => 'Registering the sale or purchase of land, a building or a flat worth more than ৳10 lakh in a city corporation, paurashava or cantonment area'],
        ['service' => 'Registering a car, changing its owner or renewing its fitness certificate'],
        ['service' => 'A new gas or electricity connection in a city corporation or cantonment area'],
        ['service' => 'Building plan approval from RAJUK, CDA, KDA or RDA'],
        ['service' => 'Keeping more than ৳10 lakh in a bank deposit'],
        ['service' => 'Admitting a child to an English-medium school that follows a foreign curriculum'],
        ['service' => 'Becoming a company director or sponsor shareholder'],
        ['service' => 'Membership of a club or a professional body'],
        ['service' => 'Contesting an election'],
    ],

    // Since the Finance Ordinance 2025 a TIN is enough for these.
    'tin_only' => [
        ['service' => 'A credit card'],
        ['service' => 'Buying more than ৳5 lakh of Sanchaypatra'],
        ['service' => 'A post office savings account above ৳5 lakh'],
        ['service' => 'Getting a trade licence'],
    ],

    'next_steps' => [
        ['title' => 'Get an e-TIN', 'text' => 'Register free at secure.incometax.gov.bd with your NID and mobile number. You get the TIN certificate immediately.'],
        ['title' => 'Work out your tax', 'text' => 'Use the calculator with your salary, investments and TDS.'],
        ['title' => 'File online', 'text' => 'Follow the return form guide to copy each figure into the e-return system.'],
    ],
];

<?php

/*
|--------------------------------------------------------------------------
| Reading a salary certificate
|--------------------------------------------------------------------------
| `components` are the regular parts of salary; together with the perks in
| config/salary.php they make up "Total Salary Received" (Schedule 1).
|
| `labels` maps each figure to the wordings employers use, as regular
| expressions matched against a lower-cased line. Order matters: the first
| match wins, so specific wordings come before general ones ("employer's
| provident fund" before "total"). Add wordings here when a certificate is
| not read well; no code change needed.
*/

return [

    'components' => [
        'basic' => ['label' => 'Basic salary'],
        'house_rent' => ['label' => 'House rent allowance'],
        'medical' => ['label' => 'Medical allowance'],
        'conveyance' => ['label' => 'Conveyance allowance'],
        'festival_bonus' => ['label' => 'Festival bonus'],
        'other_allowances' => ['label' => 'Other allowances'],
    ],

    // Field => list of patterns (PCRE, without delimiters).
    'labels' => [
        'tds' => ['tax\s*deducted', '\btds\b', 'tax\s*at\s*source', 'income\s*tax', '\bait\b', 'withholding', 'উৎসে\s*কর', 'কর্তনকৃত\s*কর', 'আয়কর'],
        'employer_pf' => ['employer.{0,20}(pf|provident|p\.f)', '(company|organi[sz]ation).{0,20}(pf|provident)', '(pf|provident).{0,25}(employer|company|organi[sz]ation)', 'নিয়োগকর্তা.{0,20}ভবিষ্য'],
        'employee_pf' => ['employee.{0,20}(pf|provident)', '(own|self|personal).{0,15}(pf|provident|contribution)', '(pf|provident).{0,25}(employee|own|deduct)'],
        'gross_total' => ['gross\s*(total|salary|pay)', 'total\s*(salary|gross|income|earning|pay|remuneration)', 'grand\s*total', 'মোট\s*বেতন'],
        'basic' => ['basic', 'মূল\s*বেতন'],
        'house_rent' => ['house\s*rent', '\bh\.?\s*r\.?\s*a\b', 'house\s*allowance', 'বাড়ি\s*ভাড়া', 'বাড়িভাড়া'],
        'medical' => ['medical', 'চিকিৎসা'],
        'accommodation' => ['accommodation', 'rent[\s-]*free', 'housing\s*facility', 'quarter'],
        'transport' => ['car\s*(facility|benefit)', 'transport\s*facility', 'vehicle'],
        'conveyance' => ['conveyance', 'transport', 'travel\s*allowance', 'যাতায়াত'],
        'festival_bonus' => ['festival', 'festive', '\beid\b', 'boishakh', 'noboborsho', 'উৎসব', 'বোনাস'],
        'performance_bonus' => ['performance', 'incentive', '\bkpi\b', 'profit\s*(bonus|share)', 'variable\s*pay'],
        'overtime' => ['over\s*time', '\bot\b'],
        'leave_encashment' => ['leave\s*(encash|fare)', 'encashment'],
        'arrear' => ['arrear', 'advance\s*salary'],
        'share_scheme' => ['share\s*(scheme|option)', '\besop\b', '\bstock'],
        // Cash allowances ("Mobile allowance") before benefits in kind ("Mobile phone facility").
        'other_allowances' => ['allowance', 'lunch', 'special', 'dearness', 'ভাতা'],
        'other_facility' => ['mobile', 'phone', 'internet', 'utility', 'club', 'facility'],
    ],

    // Columns that hold the yearly figure when a certificate shows monthly and yearly side by side.
    'yearly_headers' => ['yearly', 'annual', 'year', 'total', 'বার্ষিক'],
    'monthly_headers' => ['monthly', 'per\s*month', 'month', 'মাসিক'],
];

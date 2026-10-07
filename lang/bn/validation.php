<?php

// Bangla validation messages for the rules this app uses. Anything missing falls back to English.
return [
    'array' => ':attribute একটি তালিকা হতে হবে।',
    'boolean' => ':attribute হ্যাঁ অথবা না হতে হবে।',
    'confirmed' => ':attribute দুবার একই লিখুন।',
    'current_password' => 'বর্তমান পাসওয়ার্ড সঠিক নয়।',
    'different' => ':attribute এবং :other আলাদা হতে হবে।',
    'email' => 'সঠিক ইমেইল ঠিকানা লিখুন।',
    'in' => 'নির্বাচিত :attribute সঠিক নয়।',
    'integer' => ':attribute একটি পূর্ণসংখ্যা হতে হবে।',
    'lowercase' => ':attribute ছোট হাতের অক্ষরে লিখুন।',
    'max' => [
        'array' => ':attribute এ :max টির বেশি থাকতে পারবে না।',
        'numeric' => ':attribute :max এর বেশি হতে পারবে না।',
        'string' => ':attribute :max অক্ষরের বেশি হতে পারবে না।',
    ],
    'min' => [
        'array' => ':attribute এ কমপক্ষে :min টি থাকতে হবে।',
        'numeric' => ':attribute কমপক্ষে :min হতে হবে।',
        'string' => ':attribute কমপক্ষে :min অক্ষরের হতে হবে।',
    ],
    'numeric' => ':attribute একটি সংখ্যা হতে হবে।',
    'password' => [
        'letters' => ':attribute এ অন্তত একটি বর্ণ থাকতে হবে।',
        'mixed' => ':attribute এ অন্তত একটি বড় ও একটি ছোট হাতের বর্ণ থাকতে হবে।',
        'numbers' => ':attribute এ অন্তত একটি সংখ্যা থাকতে হবে।',
        'symbols' => ':attribute এ অন্তত একটি চিহ্ন থাকতে হবে।',
        'uncompromised' => 'এই :attribute একটি তথ্য ফাঁসে পাওয়া গেছে। অন্য একটি বেছে নিন।',
    ],
    'required' => ':attribute দিতে হবে।',
    'string' => ':attribute লেখা হতে হবে।',
    'unique' => 'এই :attribute আগেই ব্যবহৃত হয়েছে।',

    'attributes' => [
        'name' => 'নাম',
        'email' => 'ইমেইল',
        'password' => 'পাসওয়ার্ড',
        'current_password' => 'বর্তমান পাসওয়ার্ড',
        'title' => 'নাম',
        'notes' => 'নোট',
        'year' => 'করবর্ষ',
        'category' => 'করদাতার শ্রেণি',
        'filing' => 'দাখিলের সময়',
        'investment' => 'বিনিয়োগ',
        'rebate_mode' => 'রেয়াতের ধরন',
    ],
];

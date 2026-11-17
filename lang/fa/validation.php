<?php

// Partial Persian validation messages. Rules not listed here fall back to the key name, so add a line when a new rule is used.
return [
    'required' => ':attribute الزامی است.',
    'string' => ':attribute باید متن باشد.',
    'max' => [
        'string' => ':attribute نباید بیشتر از :max نویسه باشد.',
    ],
    'min' => [
        'string' => ':attribute باید حداقل :min نویسه باشد.',
    ],
    'confirmed' => 'تکرار :attribute با هم یکسان نیست.',
    'different' => ':attribute باید با مقدار فعلی متفاوت باشد.',
    'current_password' => 'رمز عبور فعلی درست نیست.',
    'attributes' => [
        'mobile' => 'شماره موبایل',
        'password' => 'رمز عبور',
        'current_password' => 'رمز عبور فعلی',
        'password_confirmation' => 'تکرار رمز عبور',
    ],
];

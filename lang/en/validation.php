<?php

$identity = require __DIR__.'/iam.php';

return [
    'required' => 'Enter :attribute.',
    'string' => 'The :attribute field must be text.',
    'array' => 'The :attribute field must be a list.',
    'integer' => 'The :attribute field must be an integer.',
    'boolean' => 'Choose a valid status.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'email' => 'Enter a valid email address.',
    'in' => 'Choose a valid :attribute.',
    'regex' => 'The :attribute format is invalid.',
    'distinct' => 'The :attribute field contains a duplicate selection.',
    'max' => ['string' => 'Use at most :max characters for :attribute.', 'array' => 'Select at most :max items.'],
    'min' => ['string' => 'Use at least :min characters for :attribute.', 'numeric' => 'The :attribute must be at least :min.'],
    'size' => ['string' => 'Enter :size characters for :attribute.'],
    'attributes' => [
        'nik' => 'NIK',
        'login' => $identity['identifier'],
        'password' => $identity['password'],
        'current_password' => $identity['current_password'],
        'reset_code' => $identity['reset_code'],
        'code' => $identity['factor_code'],
        'email' => $identity['email'],
        'is_active' => $identity['account_status'],
        'roles' => $identity['roles'],
        'permissions' => $identity['permissions'],
        'scopes' => $identity['account_access'],
        'name' => $identity['role_name'],
    ],
];

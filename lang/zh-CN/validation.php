<?php

$identity = require __DIR__.'/iam.php';

return [
    'required' => '请填写:attribute。',
    'string' => ':attribute必须为文本。',
    'array' => ':attribute必须为列表。',
    'integer' => ':attribute必须为整数。',
    'boolean' => '请选择有效的状态。',
    'confirmed' => ':attribute确认不一致。',
    'email' => '请输入有效的电子邮箱地址。',
    'in' => '请选择有效的:attribute。',
    'regex' => ':attribute格式无效。',
    'distinct' => ':attribute中有重复选项。',
    'max' => ['string' => ':attribute最多可包含 :max 个字符。', 'array' => '最多可选择 :max 项。'],
    'min' => ['string' => ':attribute至少需要 :min 个字符。', 'numeric' => ':attribute不得小于 :min。'],
    'size' => ['string' => ':attribute必须包含 :size 个字符。'],
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

<?php

$identity = require __DIR__.'/iam.php';

return [
    'date_format' => 'Gunakan format :format untuk :attribute.',
    'after_or_equal' => ':attribute harus pada atau setelah :date.',
    'before_or_equal' => ':attribute harus pada atau sebelum :date.',
    'not_in' => 'Pilihan :attribute tidak tersedia.',
    'missing' => 'Kolom :attribute tidak dapat diubah pada form ini.',
    'file' => 'Unggah berkas yang valid.',
    'required' => 'Kolom :attribute wajib diisi.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'array' => 'Kolom :attribute harus berupa daftar.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'boolean' => 'Pilih status yang valid.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'email' => 'Masukkan alamat email yang valid.',
    'in' => 'Pilihan :attribute tidak valid.',
    'regex' => 'Format :attribute tidak valid.',
    'distinct' => 'Kolom :attribute memuat pilihan ganda.',
    'max' => ['string' => 'Kolom :attribute maksimal :max karakter.', 'array' => 'Pilih maksimal :max item.'],
    'min' => ['string' => 'Kolom :attribute minimal :min karakter.', 'numeric' => 'Kolom :attribute minimal :min.'],
    'size' => ['string' => 'Kolom :attribute harus berisi :size karakter.'],
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

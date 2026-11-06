<?php

namespace App\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class Locale
{
    public const OPTIONS = [
        'id' => ['ID', 'Bahasa Indonesia'],
        'en' => ['EN', 'English'],
        'zh-CN' => ['简中', '简体中文'],
    ];

    public static function set(mixed $value): string
    {
        $locale = Validator::validate(
            ['locale' => $value],
            ['locale' => ['required', 'string', Rule::in(array_keys(self::OPTIONS))]],
        )['locale'];

        session()->put('locale', $locale);
        App::setLocale($locale);

        return $locale;
    }
}

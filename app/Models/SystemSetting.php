<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class SystemSetting extends Model
{
    public const CREATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['setting_value' => 'json'];

    public static function defaultLocale(): string
    {
        // Install and migration commands can bootstrap the app before this table exists.
        return Schema::hasTable('system_settings')
            ? (string) (self::query()->where('setting_key', 'default_locale')->first()?->setting_value ?? 'id')
            : 'id';
    }
}

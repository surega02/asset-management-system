<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true, 'auth_version' => 1, 'locale' => 'id'];

    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'auth_version' => 'integer',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_step' => 'integer',
        ];
    }

    public static function normalizeEmail(?string $value): ?string
    {
        $value = mb_strtolower(trim($value ?? ''));

        return $value === '' ? null : $value;
    }

    protected function email(): Attribute
    {
        return Attribute::set(self::normalizeEmail(...));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

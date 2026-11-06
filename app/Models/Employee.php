<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Employee extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (self $employee): void {
            if ($employee->isDirty('nik')) {
                throw new LogicException('Employee NIK is immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Employees must be retained.');
        });
    }

    protected function nik(): Attribute
    {
        return Attribute::set(fn (string $value): string => trim($value));
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}

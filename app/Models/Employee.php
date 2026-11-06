<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Employee extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['joined_at' => 'immutable_date'];

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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function homeLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}

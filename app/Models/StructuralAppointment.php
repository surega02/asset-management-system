<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StructuralAppointment extends Model
{
    protected $guarded = ['id'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeForUnit(Builder $query, string $role, string $type, ?int $id): Builder
    {
        return $query->where('role_kind', $role)->where('scope_type', $type)
            ->when($type !== 'company', fn (Builder $query) => $query->where($type.'_id', $id));
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('starts_on', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $date));
    }
}

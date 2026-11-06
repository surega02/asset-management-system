<?php

namespace App\Models;

use App\Support\MasterType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

abstract class OrganizationMaster extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Organization masters must be archived.'));
    }

    public function type(): MasterType
    {
        return MasterType::from($this->getTable());
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo($this->type()->owner()->model(), $this->type()->ownerKey());
    }
}

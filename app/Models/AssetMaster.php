<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class AssetMaster extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Asset masters must be archived.'));
    }
}

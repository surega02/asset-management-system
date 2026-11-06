<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AssetTypeAttribute extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['is_required' => 'boolean'];
}

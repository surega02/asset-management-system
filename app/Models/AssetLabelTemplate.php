<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AssetLabelTemplate extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['allowed_fields' => 'array', 'is_active' => 'boolean'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AssetAttachment extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $hidden = ['disk', 'storage_path'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AssetCatalogDefault extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public const ID = 1;
}

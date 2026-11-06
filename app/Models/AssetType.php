<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetType extends AssetMaster
{
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }
}

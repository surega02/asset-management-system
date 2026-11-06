<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Location extends OrganizationMaster
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}

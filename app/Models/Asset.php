<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

final class Asset extends Model
{
    public const CREATED_AT = 'registered_at';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id'];

    protected $hidden = ['public_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'acquisition_cost' => 'decimal:2',
        'purchase_date' => 'immutable_date',
        'warranty_ends_on' => 'immutable_date',
    ];

    protected static function booted(): void
    {
        self::updating(function (self $asset): void {
            if ($asset->isDirty(['asset_code', 'public_token'])) {
                throw new LogicException('Asset identifiers are immutable.');
            }
        });
        self::deleting(fn () => throw new LogicException('Assets must be deactivated.'));
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'physical_location_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'status_id');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(AssetCondition::class, 'condition_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('ended_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssetAttachment::class);
    }
}

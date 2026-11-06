<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

final class AttributeDefinition extends Model
{
    public $timestamps = false;

    protected $guarded = ['id', 'active_key'];

    protected $casts = ['is_active' => 'boolean', 'is_required' => 'boolean', 'allowed_options' => 'array', 'validation_rules' => 'array'];

    protected static function booted(): void
    {
        self::updating(function (self $definition): void {
            if (array_diff(array_keys($definition->getDirty()), ['is_active']) !== [] || $definition->is_active) {
                throw new LogicException('Create a new attribute version instead of rewriting a definition.');
            }
        });
        self::deleting(fn () => throw new LogicException('Attribute definitions must be retained.'));
    }

    public function configuration(): HasOne
    {
        return $this->hasOne(AssetTypeAttribute::class);
    }
}

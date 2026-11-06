<?php

namespace App\Support;

use App\Models\AssetCategory;
use App\Models\AssetCondition;
use App\Models\AssetMaster;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Vendor;

enum AssetMasterType: string
{
    case Category = 'categories';
    case Type = 'types';
    case Brand = 'brands';
    case Vendor = 'vendors';
    case Status = 'statuses';
    case Condition = 'conditions';

    /** @return class-string<AssetMaster> */
    public function model(): string
    {
        return match ($this) {
            self::Category => AssetCategory::class,
            self::Type => AssetType::class,
            self::Brand => Brand::class,
            self::Vendor => Vendor::class,
            self::Status => AssetStatus::class,
            self::Condition => AssetCondition::class,
        };
    }

    public function defaultColumn(): ?string
    {
        return match ($this) {
            self::Status => 'status_id',
            self::Condition => 'condition_id',
            default => null,
        };
    }
}

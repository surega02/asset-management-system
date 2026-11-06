<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Location;
use App\Models\OrganizationMaster;
use App\Models\Position;

enum MasterType: string
{
    case Branch = 'branches';
    case Division = 'divisions';
    case Department = 'departments';
    case Position = 'positions';
    case Location = 'locations';

    /** @return class-string<OrganizationMaster> */
    public function model(): string
    {
        return match ($this) {
            self::Branch => Branch::class,
            self::Division => Division::class,
            self::Department => Department::class,
            self::Position => Position::class,
            self::Location => Location::class,
        };
    }

    public function owner(): ?self
    {
        return match ($this) {
            self::Division, self::Location => self::Branch,
            self::Department => self::Division,
            default => null,
        };
    }

    public function ownerKey(): ?string
    {
        return match ($this->owner()) {
            self::Branch => 'branch_id',
            self::Division => 'division_id',
            default => null,
        };
    }
}

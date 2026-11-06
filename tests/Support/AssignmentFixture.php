<?php

namespace Tests\Support;

use App\Actions\Assets\RegisterAsset;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AssignmentFixture
{
    public static function type(): AssetType
    {
        $code = 'QA-'.Str::random(12);
        $category = AssetCategory::query()->create(['code' => $code, 'name' => 'Fixture category']);

        return AssetType::query()->create(['code' => $code, 'name' => 'Fixture type', 'category_id' => $category->id]);
    }

    public static function assign(User $actor, int $employee, int $location, int $typeId): int
    {
        $type = AssetType::query()->findOrFail($typeId);

        return app(RegisterAsset::class)($actor, [
            'name' => 'Aset sintetis', 'physical_location_id' => $location, 'employee_id' => $employee,
            'asset_type_id' => $type->id, 'category_id' => $type->category_id,
            'purchase_date' => '2026-01-15', 'acquisition_cost' => '1.25',
        ])->id;
    }

    public static function clean(AssetType $type): void
    {
        $ids = DB::table('assets')->where('asset_type_id', $type->id)->pluck('id');
        foreach (['audit_logs', 'asset_location_histories', 'asset_assignments'] as $table) {
            DB::table($table)->whereIn('asset_id', $ids)->delete();
        }
        DB::table('assets')->whereIn('id', $ids)->delete();
        DB::table('asset_types')->where('id', $type->id)->delete();
        DB::table('asset_categories')->where('id', $type->category_id)->delete();
    }
}

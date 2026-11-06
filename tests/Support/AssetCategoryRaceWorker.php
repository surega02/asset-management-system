<?php

namespace Tests\Support;

use App\Actions\Assets\SaveAssetMaster;
use App\Models\User;
use App\Support\AssetMasterType;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

final class AssetCategoryRaceWorker
{
    public static function run(int $actor, int $category, int $parent): void
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        if (getenv('AMS_TEST_MYSQL') !== '1' || config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_test')) {
            exit(2);
        }

        try {
            $app->make(SaveAssetMaster::class)->save(User::query()->findOrFail($actor), AssetMasterType::Category, $category, [
                'name' => 'Category race', 'is_active' => 1, 'parent_id' => $parent,
            ]);
            echo 'saved';
        } catch (ValidationException $exception) {
            if (($exception->errors()['parent_id'][0] ?? null) !== __('asset_catalog.category_cycle')) {
                throw $exception;
            }
            echo 'cycle';
        }
    }
}

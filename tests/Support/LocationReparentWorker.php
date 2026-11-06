<?php

namespace Tests\Support;

use App\Actions\Organization\SaveMaster;
use App\Models\Location;
use App\Models\User;
use App\Support\MasterType;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

final class LocationReparentWorker
{
    public static function run(int $actorId, int $locationId, int $parentId): void
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        if (getenv('AMS_TEST_MYSQL') !== '1' || config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_test')) {
            exit(2);
        }

        $actor = User::query()->findOrFail($actorId);
        $location = Location::query()->findOrFail($locationId);
        try {
            $app->make(SaveMaster::class)($actor, MasterType::Location, $location->id, OrganizationFixtures::data($location->code, ['parent_id' => $parentId]));
            echo 'saved';
        } catch (ValidationException $exception) {
            if (($exception->errors()['parent_id'][0] ?? null) !== __('masters.cycle')) {
                throw $exception;
            }
            echo 'cycle';
        }
    }
}

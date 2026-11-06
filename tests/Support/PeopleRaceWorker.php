<?php

namespace Tests\Support;

use App\Actions\Assets\ReassignAsset;
use App\Actions\Employees\ManageEmployee;
use App\Actions\Organization\AssignStructuralOfficer;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

final class PeopleRaceWorker
{
    public static function run(string $operation, int $actorId, int $employeeId, int $target, int $typeId): void
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        if (getenv('AMS_TEST_MYSQL') !== '1' || config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_test')) {
            exit(2);
        }
        $actor = User::query()->findOrFail($actorId);
        $employee = Employee::query()->findOrFail($employeeId);

        try {
            match ($operation) {
                'appointment' => $app->make(AssignStructuralOfficer::class)->create($actor, [
                    'employee_id' => $employeeId, 'role_kind' => 'hod', 'scope_type' => 'department', 'scope_id' => $target,
                    'appointment_kind' => 'acting', 'starts_on' => '2026-05-01', 'ends_on' => '2026-05-31',
                ]),
                'deactivate' => $app->make(ManageEmployee::class)->update($actor, $employee, self::inactiveProfile($employee)),
                'assign' => AssignmentFixture::assign($actor, $employeeId, $target, $typeId),
                'reassign' => $app->make(ReassignAsset::class)($actor, Asset::query()->findOrFail($target), [
                    'employee_id' => $employeeId, 'assignment_id' => $typeId, 'reason' => 'Parallel reassignment',
                ]),
            };
            echo 'saved';
        } catch (ValidationException $exception) {
            if ($operation === 'reassign') {
                foreach ([
                    'stale_assignment' => ['assignment_id', 'tracking.stale_assignment'],
                    'inactive_employee' => ['employee_id', 'people.inactive_employee'],
                    'invalid_assignee' => ['assignee', 'assets.invalid_assignee'],
                ] as $result => [$field, $message]) {
                    if (($exception->errors()[$field][0] ?? null) === __($message)) {
                        echo $result;

                        return;
                    }
                }
                throw $exception;
            }
            $expected = match ($operation) {
                'appointment' => ['starts_on', 'overlap'],
                'deactivate' => ['employment_status', 'has_assets'],
                'assign' => ['employee_id', 'inactive_employee'],
            };
            if (($exception->errors()[$expected[0]][0] ?? null) !== __('people.'.$expected[1])) {
                throw $exception;
            }
            echo $expected[1];
        }
    }

    public static function inactiveProfile(Employee $employee): array
    {
        return [
            'name' => $employee->name, 'position_id' => $employee->position_id, 'home_location_id' => $employee->home_location_id,
            'joined_at' => $employee->joined_at->toDateString(), 'employment_status' => 'inactive',
        ];
    }
}

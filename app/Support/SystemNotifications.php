<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\StructuralAppointment;
use App\Models\User;
use App\Queries\Assets;
use App\Queries\PeopleScope;
use Illuminate\Database\Eloquent\Builder as ModelQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

final class SystemNotifications
{
    public static function record(string $kind, int $subject, string $key, array $context = []): void
    {
        DB::table('notification_events')->insertOrIgnore([
            'kind' => $kind, 'subject_id' => $subject, 'event_key' => $key, 'created_at' => now(),
        ] + $context);
    }

    public static function remindActing(): void
    {
        self::actingSoon()->select(['id', 'ends_on'])->chunkById(100, function ($appointments): void {
            foreach ($appointments as $appointment) {
                self::record('acting_expiry', $appointment->id, 'acting:'.$appointment->id.':'.$appointment->ends_on,
                    ['ends_on' => $appointment->ends_on]);
            }
        });
    }

    public function publish(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $ids = DB::table('notification_events')->whereNull('published_at')->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $event = DB::table('notification_events')->whereNull('published_at')->lockForUpdate()->find($id);
                if ($event === null) {
                    return;
                }
                foreach ($this->candidates($event)->where('is_active', true)->lazyById(100) as $actor) {
                    if ($this->visibleEvents($actor)->where('notification_events.id', $event->id)->exists()) {
                        DB::table('notifications')->insertOrIgnore([
                            'user_id' => $actor->id, 'notification_event_id' => $event->id, 'created_at' => now(),
                        ]);
                    }
                }
                DB::table('notification_events')->where('id', $id)->update(['published_at' => now()]);
            }, 3);
        }
    }

    public function inbox(User $actor): Builder
    {
        return DB::table('notifications')->join('notification_events as event', 'event.id', '=', 'notifications.notification_event_id')
            ->where('notifications.user_id', $actor->id)
            ->whereIn('event.id', $this->visibleEvents($actor));
    }

    private function visibleEvents(User $actor): Builder
    {
        $query = DB::table('notification_events')->select('notification_events.id');
        if (! $actor->is_active) {
            return $query->whereRaw('1 = 0');
        }
        $gate = Gate::forUser($actor);

        return $query->where(function (Builder $events) use ($actor, $gate): void {
            $events->whereRaw('1 = 0');
            foreach (['asset_import' => [Asset::class, 'import_batches'], 'employee_import' => [Employee::class, 'employee_imports']] as $kind => [$model, $table]) {
                if ($gate->allows('import', $model)) {
                    $events->orWhere(fn (Builder $q) => $q->where('kind', $kind)->whereIn('subject_id',
                        DB::table($table)->where('user_id', $actor->id)->where('created_at', '>', now()->subDays(90))->select('id')));
                }
            }
            if ($actor->hasAnyRole([IdentityAccess::SUPER_ADMIN, 'asset-admin'])) {
                $events->orWhere(fn (Builder $q) => $q->whereIn('kind', ['asset_deactivated', 'asset_reactivated'])
                    ->whereIn('subject_id', Assets::operational($actor)->select('assets.id')));
                $events->orWhere(fn (Builder $q) => $q->where('kind', 'employee_transferred')
                    ->whereIn('subject_id', PeopleScope::employees($actor)->select('employees.id')));
            }
            if ($gate->allows('viewAny', Asset::class)) {
                $events->orWhere(fn (Builder $q) => $q->where('kind', 'asset_reassigned')
                    ->where(fn (Builder $assignee) => $assignee->where('old_employee_id', $actor->employee_id)->orWhere('new_employee_id', $actor->employee_id))
                    ->whereIn('subject_id', Assets::visible($actor)->select('assets.id')));
            }
            if (IdentityAccess::superAdminCan($actor, 'appointments.view')) {
                $events->orWhere(fn (Builder $q) => $q->where('kind', 'acting_expiry')
                    ->whereExists(self::actingSoon()->whereColumn('structural_appointments.id', 'notification_events.subject_id')
                        ->whereColumn('structural_appointments.ends_on', 'notification_events.ends_on')->select('id')->toBase()));
            }
        });
    }

    private function candidates(object $event): ModelQuery
    {
        return match ($event->kind) {
            'asset_import', 'employee_import' => User::query()->whereIn('id', DB::table($event->kind === 'asset_import' ? 'import_batches' : 'employee_imports')
                ->where('id', $event->subject_id)->select('user_id')),
            'asset_reassigned' => User::query()->whereIn('employee_id', array_filter([$event->old_employee_id, $event->new_employee_id])),
            'acting_expiry' => User::role(IdentityAccess::SUPER_ADMIN),
            default => User::role([IdentityAccess::SUPER_ADMIN, 'asset-admin']),
        };
    }

    private static function actingSoon(): ModelQuery
    {
        return StructuralAppointment::query()->where('appointment_kind', 'acting')->where('starts_on', '<=', today()->toDateString())
            ->whereBetween('ends_on', [today()->toDateString(), today()->addDays(7)->toDateString()]);
    }
}

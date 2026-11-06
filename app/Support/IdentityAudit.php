<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class IdentityAudit
{
    public static function record(?int $actorId, Model $subject, string $event, string $reason, ?array $old = null, ?array $new = null): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => $actorId,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'old_values' => self::snapshot($old),
            'new_values' => self::snapshot($new),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private static function snapshot(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }

        $keys = ['employee_id', 'is_active', 'has_email', 'role_ids', 'permission_ids', 'scope_ids', 'name', 'enabled', 'locale', 'theme', 'code', 'branch_id', 'division_id', 'parent_id', 'department_id', 'position_id', 'home_location_id', 'employment_status', 'joined_at', 'role_kind', 'scope_type', 'appointment_kind', 'starts_on', 'ends_on', 'category_id', 'status_id', 'condition_id', 'asset_type_id', 'attribute_definition_id', 'key', 'version', 'label', 'data_type', 'allowed_options', 'validation_rules', 'is_required', 'sort_order'];

        return json_encode(array_intersect_key($values, array_flip($keys)), JSON_THROW_ON_ERROR);
    }
}

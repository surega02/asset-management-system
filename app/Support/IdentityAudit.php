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

        $keys = ['employee_id', 'is_active', 'has_email', 'role_ids', 'permission_ids', 'scope_ids', 'name', 'enabled', 'locale', 'theme'];

        return json_encode(array_intersect_key($values, array_flip($keys)), JSON_THROW_ON_ERROR);
    }
}

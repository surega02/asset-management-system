<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class IdentityAudit
{
    public static function record(?int $actorId, Model $subject, string $event, string $reason, ?array $old = null, ?array $new = null): int
    {
        if ($subject instanceof Employee || $subject instanceof User) {
            unset($old['name'], $new['name']);
        }

        return DB::table('audit_logs')->insertGetId([
            'asset_id' => $subject instanceof Asset ? $subject->getKey() : null,
            'actor_id' => $actorId,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'old_values' => self::snapshot($old),
            'new_values' => self::snapshot($new),
            'reason' => self::safeText($reason),
            'created_at' => now(),
        ]);
    }

    private static function snapshot(?array $values): ?string
    {
        return $values === null ? null : json_encode(self::safeValues($values), JSON_THROW_ON_ERROR);
    }

    public static function safeValues(array $values): array
    {
        $keys = ['employee_id', 'is_active', 'has_email', 'role_ids', 'permission_ids', 'scope_ids', 'name', 'enabled', 'locale', 'theme', 'code', 'branch_id', 'division_id', 'parent_id', 'department_id', 'position_id', 'home_location_id', 'employment_status', 'joined_at', 'role_kind', 'scope_type', 'appointment_kind', 'starts_on', 'ends_on', 'category_id', 'status_id', 'condition_id', 'asset_type_id', 'attribute_definition_id', 'key', 'version', 'label', 'data_type', 'allowed_options', 'validation_rules', 'is_required', 'sort_order'];

        $keys = array_merge($keys, ['asset_code', 'physical_location_id', 'brand_id', 'vendor_id', 'purchase_date', 'acquisition_cost', 'assignment_id', 'location_id', 'attachment_ids', 'attribute_definition_ids', 'import_batch_id', 'import_row_number', 'import_action']);

        $keys = [...$keys, 'width_mm', 'height_mm', 'allowed_fields'];
        $result = array_intersect_key($values, array_flip($keys));
        foreach ($result as $key => $value) {
            if (! is_array($value)) {
                continue;
            }
            if ($key === 'validation_rules') {
                $result[$key] = array_filter(array_intersect_key($value, ['min' => true, 'max' => true]), 'is_numeric');
            } elseif (array_is_list($value) && in_array($key, ['role_ids', 'permission_ids', 'attachment_ids', 'attribute_definition_ids'], true)) {
                $result[$key] = array_values(array_filter($value, fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id))));
            } elseif (array_is_list($value) && $key === 'scope_ids') {
                $result[$key] = array_values(array_filter($value, fn ($scope) => is_string($scope) && preg_match('/\A(branch|division|department):[0-9]+\z/', $scope)));
            } elseif (array_is_list($value) && in_array($key, ['allowed_options', 'allowed_fields'], true)) {
                $result[$key] = array_values(array_filter($value, 'is_string'));
            } else {
                unset($result[$key]);
            }
        }
        array_walk_recursive($result, function (&$value): void {
            if (is_string($value)) {
                $value = self::safeText($value);
            }
        });

        return $result;
    }

    public static function safeText(string $value): string
    {
        $credential = '/(?:password|passphrase|kata\s+sandi|secret|rahasia|recovery[ _-]?codes?|token|authorization|api[ _-]?key|otp|密码|令牌|nik|email)\s*[:=]|\bBearer\s+\S|\b[\w.+-]+@[\w.-]+\.[a-z]{2,}\b|\b[a-f0-9]{64}\b/iu';

        return preg_match($credential, $value) ? '[redacted]' : $value;
    }
}

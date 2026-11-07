<?php

namespace App\Actions\Identity;

use App\Actions\Assets\PrintAssetLabels;
use App\Models\AssetLabelTemplate;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use App\Support\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ManageSystemSettings
{
    public function locale(User $actor, array $input): void
    {
        Gate::forUser($actor)->authorize('update-global-settings');
        $data = Validator::make($input, ['locale' => ['required', Rule::in(array_keys(Locale::OPTIONS))]])->validate();
        DB::transaction(function () use ($actor, $data): void {
            $actor = IdentityAccess::lockAdministrator($actor);
            Gate::forUser($actor)->authorize('update-global-settings');
            $setting = SystemSetting::query()->where('setting_key', 'default_locale')->lockForUpdate()->firstOrFail();
            $before = $setting->setting_value;
            if ($before !== $data['locale']) {
                $setting->update(['setting_value' => $data['locale'], 'updated_by' => $actor->id]);
                IdentityAudit::record($actor->id, $setting, 'settings.locale_changed', 'global-settings',
                    ['locale' => $before], ['locale' => $data['locale']]);
            }
        }, 3);
    }

    public function label(User $actor, int $id, array $input): void
    {
        Gate::forUser($actor)->authorize('update-global-settings');
        $data = Validator::make($input, [
            'width_mm' => ['required', 'decimal:0,2', 'between:60,120'],
            'height_mm' => ['required', 'decimal:0,2', 'between:36,75'],
            'allowed_fields' => ['sometimes', 'array', 'max:3'],
            'allowed_fields.*' => ['distinct', Rule::in(PrintAssetLabels::OPTIONAL_FIELDS)],
        ], attributes: __('system.settings_fields'))->validate();
        $data['allowed_fields'] = array_values($data['allowed_fields'] ?? []);
        DB::transaction(function () use ($actor, $id, $data): void {
            $actor = IdentityAccess::lockAdministrator($actor);
            Gate::forUser($actor)->authorize('update-global-settings');
            $template = AssetLabelTemplate::query()->lockForUpdate()->findOrFail($id);
            $before = $template->only(array_keys($data));
            $template->fill($data);
            if ($template->isDirty()) {
                $template->save();
                IdentityAudit::record($actor->id, $template, 'settings.label_changed', 'global-settings', $before, $template->only(array_keys($data)));
            }
        }, 3);
    }
}

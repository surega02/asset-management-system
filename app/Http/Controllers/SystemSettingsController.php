<?php

namespace App\Http\Controllers;

use App\Actions\Identity\ManageSystemSettings;
use App\Models\AssetLabelTemplate;
use App\Models\SystemSetting;
use App\Support\QueueHealth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

final class SystemSettingsController
{
    public function show(): View
    {
        Gate::authorize('update-global-settings');

        return view('system.settings', [
            'locale' => SystemSetting::defaultLocale(),
            'templates' => AssetLabelTemplate::query()->orderBy('id')->get(),
            'health' => Cache::get(QueueHealth::KEY),
        ]);
    }

    public function locale(Request $request, ManageSystemSettings $settings): RedirectResponse
    {
        $settings->locale($request->user(), $request->only('locale'));

        return to_route('system.settings')->with('status', __('system.saved'));
    }

    public function label(Request $request, int $template, ManageSystemSettings $settings): RedirectResponse
    {
        $settings->label($request->user(), $template, $request->only('width_mm', 'height_mm', 'allowed_fields'));

        return to_route('system.settings')->with('status', __('system.saved'));
    }
}

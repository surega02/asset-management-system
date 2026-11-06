<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\IdentityAudit;
use App\Support\TwoFactor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorSettingsController
{
    public function show(Request $request, TwoFactor $twoFactor): View
    {
        $user = $request->user();
        $secret = $user->two_factor_confirmed_at ? null : $this->pendingSecret($request);

        return view('auth.security', [
            'enabled' => $user->two_factor_confirmed_at !== null,
            'secret' => $secret,
            'qr' => $secret ? base64_encode($twoFactor->qr($user, $secret)) : null,
            'recoveryCodes' => $request->session()->pull('new_recovery_codes', []),
        ]);
    }

    public function start(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $this->confirmPassword($request, $request->user());
        abort_if($request->user()->two_factor_confirmed_at, 409);
        $request->session()->put('auth.enrollment', [
            'secret' => $twoFactor->generateSecret(),
            'expires' => now()->addMinutes(10)->timestamp,
        ]);

        return to_route('security.show');
    }

    public function confirm(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $input = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $secret = $this->pendingSecret($request);
        $step = $secret ? $twoFactor->step($secret, $input['code']) : false;
        if ($step === false) {
            throw ValidationException::withMessages(['code' => __('iam.invalid_factor')]);
        }

        [$user, $codes] = DB::transaction(function () use ($request, $secret, $step, $twoFactor): array {
            $user = $this->lockedUser($request);
            $this->confirmPassword($request, $user);
            abort_if($user->two_factor_confirmed_at, 409);
            $user->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_confirmed_at' => now(),
                'two_factor_last_step' => $step,
                'auth_version' => $user->auth_version + 1,
            ])->save();

            $codes = $twoFactor->replaceRecoveryCodes($user);
            IdentityAudit::record($user->id, $user, 'account.two_factor_enabled', 'self_service', ['enabled' => false], ['enabled' => true]);

            return [$user, $codes];
        }, 3);

        $request->session()->forget('auth.enrollment');

        return $this->finish($request, $user, $codes);
    }

    public function regenerate(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        return $this->updateExisting($request, $twoFactor, false);
    }

    public function disable(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        return $this->updateExisting($request, $twoFactor, true);
    }

    private function updateExisting(Request $request, TwoFactor $twoFactor, bool $disable): RedirectResponse
    {
        $input = $request->validate(['code' => ['required', 'string', 'max:64']]);
        [$user, $codes] = DB::transaction(function () use ($request, $twoFactor, $input, $disable): array {
            $user = $this->lockedUser($request);
            $this->confirmPassword($request, $user);

            if (! $user->two_factor_confirmed_at || ! $twoFactor->consume($user, $input['code'])) {
                throw ValidationException::withMessages(['code' => __('iam.invalid_factor')]);
            }

            $codes = [];
            if ($disable) {
                $user->forceFill([
                    'two_factor_secret' => null,
                    'two_factor_confirmed_at' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_last_step' => null,
                ]);
            } else {
                $codes = $twoFactor->replaceRecoveryCodes($user);
            }

            $user->forceFill(['auth_version' => $user->auth_version + 1])->save();
            IdentityAudit::record(
                $user->id,
                $user,
                $disable ? 'account.two_factor_disabled' : 'account.recovery_codes_replaced',
                'self_service',
                ['enabled' => true],
                ['enabled' => ! $disable],
            );

            return [$user, $codes];
        }, 3);

        return $this->finish($request, $user, $codes);
    }

    private function finish(Request $request, User $user, array $codes): RedirectResponse
    {
        Auth::setUser($user);
        $request->session()->regenerate();
        $request->session()->put('auth.version', $user->auth_version);

        if ($codes !== []) {
            $request->session()->flash('new_recovery_codes', $codes);
        }

        return to_route('security.show')->with('status', __('iam.security_saved'));
    }

    private function confirmPassword(Request $request, User $user): void
    {
        $input = $request->validate(['current_password' => ['required', 'string', 'max:1024']]);
        if (strlen($input['current_password']) > 72 || ! Hash::check($input['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('iam.invalid_password')]);
        }
    }

    private function lockedUser(Request $request): User
    {
        $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
        abort_unless($user->is_active && $request->session()->get('auth.version') === $user->auth_version, 403);

        return $user;
    }

    private function pendingSecret(Request $request): ?string
    {
        $pending = $request->session()->get('auth.enrollment');

        return $pending && $pending['expires'] > now()->timestamp ? $pending['secret'] : null;
    }
}

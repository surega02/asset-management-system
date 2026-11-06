<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\IdentityAttempts;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

class SessionController
{
    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate(['login' => ['required', 'string', 'max:254'], 'password' => ['required', 'string', 'max:1024']]);
        $identifier = trim($input['login']);
        $account = str_contains($identifier, '@')
            ? User::query()->where('email', User::normalizeEmail($identifier))->first()
            : User::query()->whereHas('employee', fn ($query) => $query->where('nik', $identifier))->first();

        $identity = $account ? 'user:'.$account->id : 'unknown:'.mb_strtolower($identifier);
        $user = IdentityAttempts::check('login', $identity, $request->ip(), function () use ($account, $input): ?User {
            return (new Timebox)->call(function (Timebox $timebox) use ($account, $input): ?User {
                if (! $account || strlen($input['password']) > 72 || ! Hash::check($input['password'], $account->password) || ! $account->is_active) {
                    return null;
                }

                $timebox->returnEarly();

                return $account;
            }, 250_000);
        });

        if (! $user) {
            throw ValidationException::withMessages(['login' => __('iam.credentials')]);
        }

        if ($user->two_factor_confirmed_at) {
            $request->session()->regenerate();
            $request->session()->put('auth.challenge', [
                'user' => $user->id,
                'version' => $user->auth_version,
                'expires' => now()->addMinutes(5)->timestamp,
            ]);

            return to_route('two-factor.challenge');
        }

        return $this->signIn($request, $user);
    }

    public function challenge(Request $request): mixed
    {
        return $this->pending($request) ? view('auth.challenge') : to_route('login');
    }

    public function verify(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return to_route('login');
        }

        $input = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $user = IdentityAttempts::check('two-factor', (string) $pending['user'], $request->ip(), function () use ($pending, $input, $twoFactor): ?User {
            return DB::transaction(function () use ($pending, $input, $twoFactor): ?User {
                $user = User::query()->lockForUpdate()->find($pending['user']);

                if (! $user || ! $user->is_active || $user->auth_version !== $pending['version'] || ! $user->two_factor_confirmed_at) {
                    return null;
                }

                return $twoFactor->consume($user, $input['code']) ? $user : null;
            }, 3);
        });

        if (! $user) {
            throw ValidationException::withMessages(['code' => __('iam.invalid_factor')]);
        }

        return $this->signIn($request, $user);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('auth.challenge');
        $request->session()->put('auth.version', $user->auth_version);

        return redirect()->intended(route('workspace'));
    }

    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get('auth.challenge');

        return $pending && $pending['expires'] > now()->timestamp ? $pending : null;
    }
}

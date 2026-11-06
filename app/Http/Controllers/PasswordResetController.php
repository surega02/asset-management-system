<?php

namespace App\Http\Controllers;

use App\Actions\Identity\PasswordResets;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

class PasswordResetController
{
    public function requestCode(Request $request, PasswordResets $resets): RedirectResponse
    {
        $input = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        (new Timebox)->call(fn () => $resets->requestByEmail(User::normalizeEmail($input['email'])), 250_000);

        return back()->with('status', __('iam.reset_requested'));
    }

    public function update(Request $request, PasswordResets $resets): RedirectResponse
    {
        $input = $request->validate([
            'reset_code' => ['required', 'string', 'regex:/\A[0-9a-fA-F]{48}\z/'],
            'password' => ['required', 'string', 'min:12', 'confirmed', function (string $attribute, string $value, Closure $fail): void {
                if (strlen($value) > 72) {
                    $fail(__('iam.password_length'));
                }
            }],
        ]);

        if (! $resets->redeem($input['reset_code'], $input['password'])) {
            throw ValidationException::withMessages(['reset_code' => __('iam.invalid_reset')]);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', __('iam.password_updated'));
    }
}

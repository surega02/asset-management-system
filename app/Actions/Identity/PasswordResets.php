<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use App\Support\IdentityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class PasswordResets
{
    public function requestByEmail(string $email): void
    {
        DB::transaction(function () use ($email): void {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if (! $user?->is_active || DB::table('password_reset_tokens')
                ->where('user_id', $user->id)->where('created_at', '>', now()->subMinute())->exists()) {
                return;
            }

            $this->requestLocked($user, null, 'self_service');
        }, 3);
    }

    public function requestLocked(User $user, ?int $actorId, string $reason): void
    {
        $code = $user->email ? bin2hex(random_bytes(24)) : null;
        $this->store($user, $code);
        IdentityAudit::record($actorId, $user, 'account.password_reset_requested', $reason);

        if ($code !== null) {
            $email = $user->email;
            $locale = $user->locale;
            $userId = $user->id;
            DB::afterCommit(function () use ($code, $email, $locale, $userId): void {
                try {
                    Notification::route('mail', $email)->notify((new PasswordResetCode($userId, $code))->locale($locale));
                } catch (Throwable $exception) {
                    Log::warning('Password reset queue dispatch failed.', ['exception_type' => $exception::class]);
                }
            });
        }
    }

    public function issueHandover(User $user): ?string
    {
        return DB::transaction(function () use ($user): ?string {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $pending = DB::table('password_reset_tokens')->where('user_id', $user->id)
                ->whereNull('token_hash')->where('created_at', '>=', now()->subMinutes(30))->exists();

            if (! $user->is_active || $user->email || ! $pending) {
                return null;
            }

            $code = bin2hex(random_bytes(24));
            $this->store($user, $code);
            IdentityAudit::record(null, $user, 'account.password_reset_handover_issued', 'verified_operator_handover');

            return $code;
        }, 3);
    }

    public function redeem(#[\SensitiveParameter] string $code, #[\SensitiveParameter] string $password): bool
    {
        $hash = hash('sha256', strtolower(trim($code)));
        $id = DB::table('password_reset_tokens')->where('token_hash', $hash)->value('user_id');
        if (! $id) {
            return false;
        }

        return DB::transaction(function () use ($id, $hash, $password): bool {
            $user = User::query()->lockForUpdate()->find($id);
            $token = DB::table('password_reset_tokens')->where('user_id', $id)
                ->where('created_at', '>=', now()->subMinutes(30))->first();

            if (! $user?->is_active || ! $token?->token_hash || ! hash_equals($token->token_hash, $hash)) {
                return false;
            }

            $user->forceFill(['password' => $password, 'auth_version' => $user->auth_version + 1])->save();
            DB::table('password_reset_tokens')->where('user_id', $id)->delete();
            IdentityAudit::record($user->id, $user, 'account.password_reset_completed', 'one_time_code');

            return true;
        }, 3);
    }

    private function store(User $user, #[\SensitiveParameter] ?string $code): void
    {
        DB::table('password_reset_tokens')->updateOrInsert(['user_id' => $user->id], [
            'token_hash' => $code === null ? null : hash('sha256', $code),
            'created_at' => now(),
        ]);
    }
}

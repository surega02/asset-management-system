<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class PasswordResetCode extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 20;

    public function __construct(private int $userId, private string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('iam.reset_title'))
            ->line(__('iam.reset_mail_intro'))
            ->line($this->code)
            ->action(__('iam.reset_title'), rtrim(config('app.url'), '/').route('password.reset', absolute: false))
            ->line(__('iam.reset_mail_expiry'));
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return User::query()->whereKey($this->userId)->where('is_active', true)
            ->where('email', $notifiable->routeNotificationFor('mail'))
            ->whereExists(DB::table('password_reset_tokens')->whereColumn('user_id', 'users.id')
                ->where('token_hash', hash('sha256', $this->code))
                ->where('created_at', '>=', now()->subMinutes(30)))
            ->exists();
    }
}

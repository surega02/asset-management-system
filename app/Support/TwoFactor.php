<?php

namespace App\Support;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactor
{
    public function __construct(private Google2FA $totp) {}

    public function generateSecret(): string
    {
        return $this->totp->generateSecretKey();
    }

    public function qr(User $user, string $secret): string
    {
        $label = $user->email ?? $user->employee->nik;
        $url = $this->totp->getQRCodeUrl('AMS', $label, $secret);

        return (new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd)))->writeString($url);
    }

    public function step(string $secret, string $code, ?int $previous = null): int|false
    {
        if (! preg_match('/\A[0-9]{6}\z/', $code)) {
            return false;
        }

        return $this->totp->verifyKeyNewer($secret, $code, $previous ?? 0, 1);
    }

    /**
     * Validate a factor while holding the account row lock.
     */
    public function consume(User $user, string $value): bool
    {
        $step = $this->step($user->two_factor_secret, $value, $user->two_factor_last_step);
        if ($step !== false) {
            $user->forceFill(['two_factor_last_step' => $step])->save();

            return true;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        $index = array_search(hash('sha256', strtolower(trim($value))), $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    public function replaceRecoveryCodes(User $user): array
    {
        $codes = array_map(fn (): string => bin2hex(random_bytes(12)), range(1, 8));
        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code): string => hash('sha256', $code), $codes),
        ])->save();

        return $codes;
    }
}

<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use LogicException;

final class AssetQr
{
    public static function prefix(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $parts = parse_url($base);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || empty($parts['host']) || array_intersect_key($parts, array_flip(['user', 'pass', 'query', 'fragment']))) {
            throw new LogicException('APP_URL must be the canonical HTTP(S) base URL for asset QR codes.');
        }

        return $base.'/q/';
    }

    public static function dataImage(string $token): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(256, 4), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString(self::prefix().$token, ecLevel: ErrorCorrectionLevel::M()));
    }
}

<?php

namespace App\Support;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR code kama SVG (haihitaji GD/Imagick).
 */
class QrCode
{
    public static function svg(string $text, int $size = 160, string $hex = '#0f172a'): string
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        $renderer = new ImageRenderer(
            new RendererStyle($size, 1, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb($r, $g, $b))),
            new SvgImageBackEnd()
        );

        // Ondoa tamko la XML ili SVG iweze kuwekwa moja kwa moja ndani ya HTML.
        return preg_replace('/^<\?xml.*?\?>\s*/', '', (new Writer($renderer))->writeString($text));
    }
}

<?php

namespace App\Services\Groups;

use Illuminate\Support\Facades\Storage;

class GeneratedGroupImageService
{
    private const FALLBACK_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGOSHzRgAAAAABJRU5ErkJggg==';

    private const BANNER_WIDTH = 1500;

    private const BANNER_HEIGHT = 400;

    private const PROFILE_SIZE = 800;

    public function generateBannerImage(string $slug, string $name, ?string $datacenter): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return $this->storeFallbackPng('groups/generated-banners/'.$slug.'.png');
        }

        $canvas = imagecreatetruecolor(self::BANNER_WIDTH, self::BANNER_HEIGHT);

        if (! $canvas) {
            return $this->storeFallbackPng('groups/generated-banners/'.$slug.'.png');
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $palette = [
            'base' => $this->palette($slug.'-base', 14, 54),
            'secondary' => $this->palette($slug.'-secondary', 28, 96),
            'accent' => $this->palette($slug.'-accent', 92, 182),
            'soft' => $this->palette($slug.'-soft', 150, 235),
            'hot' => $this->palette($slug.'-hot', 175, 250),
        ];

        $this->drawGradientBackground($canvas, self::BANNER_WIDTH, self::BANNER_HEIGHT, $palette['base'], $palette['secondary']);
        $this->drawAtmosphere($canvas, $slug, $palette);

        match ($this->seededNumber($slug, 'banner-variant', 0, 3)) {
            0 => $this->drawOrbitalVariant($canvas, $slug, $palette),
            1 => $this->drawMountainVariant($canvas, $slug, $palette),
            2 => $this->drawGridVariant($canvas, $slug, $palette),
            default => $this->drawShardVariant($canvas, $slug, $palette),
        };

        $this->drawNoiseLines($canvas, $slug, $palette);

        ob_start();
        imagepng($canvas);
        $binary = ob_get_clean();
        imagedestroy($canvas);

        if (! is_string($binary) || $binary === '') {
            return $this->storeFallbackPng('groups/generated-banners/'.$slug.'.png');
        }

        $path = 'groups/generated-banners/'.$slug.'.png';
        Storage::disk('public')->put($path, $binary);

        return Storage::disk('public')->url($path);
    }

    public function generateProfileImage(string $slug, string $name, ?string $datacenter): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return $this->storeFallbackPng('groups/generated-profiles/'.$slug.'.png');
        }

        $canvas = imagecreatetruecolor(self::PROFILE_SIZE, self::PROFILE_SIZE);

        if (! $canvas) {
            return $this->storeFallbackPng('groups/generated-profiles/'.$slug.'.png');
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $palette = [
            'base' => $this->palette($slug.'-profile-base', 20, 62),
            'secondary' => $this->palette($slug.'-profile-secondary', 56, 136),
            'accent' => $this->palette($slug.'-profile-accent', 110, 210),
            'soft' => $this->palette($slug.'-profile-soft', 180, 245),
            'hot' => $this->palette($slug.'-profile-hot', 200, 255),
        ];

        $this->drawGradientBackground($canvas, self::PROFILE_SIZE, self::PROFILE_SIZE, $palette['base'], $palette['secondary']);
        $this->drawProfileGlow($canvas, $slug, $palette);
        $this->drawProfilePanels($canvas, $slug, $palette);
        $this->drawProfileCore($canvas, $slug, $name, $palette);

        ob_start();
        imagepng($canvas);
        $binary = ob_get_clean();
        imagedestroy($canvas);

        if (! is_string($binary) || $binary === '') {
            return $this->storeFallbackPng('groups/generated-profiles/'.$slug.'.png');
        }

        $path = 'groups/generated-profiles/'.$slug.'.png';
        Storage::disk('public')->put($path, $binary);

        return Storage::disk('public')->url($path);
    }

    /**
     * @param  array{0:int,1:int,2:int}  $topColor
     * @param  array{0:int,1:int,2:int}  $bottomColor
     */
    private function drawGradientBackground($canvas, int $width, int $height, array $topColor, array $bottomColor): void
    {
        for ($y = 0; $y < $height; $y++) {
            $mix = $y / max(1, $height - 1);
            $red = (int) round($topColor[0] + (($bottomColor[0] - $topColor[0]) * $mix));
            $green = (int) round($topColor[1] + (($bottomColor[1] - $topColor[1]) * $mix));
            $blue = (int) round($topColor[2] + (($bottomColor[2] - $topColor[2]) * $mix));
            $lineColor = imagecolorallocate($canvas, $red, $green, $blue);
            imageline($canvas, 0, $y, $width, $y, $lineColor);
        }
    }

    /**
     * @param  array<string, array{0:int,1:int,2:int}>  $palette
     */
    private function drawAtmosphere($canvas, string $slug, array $palette): void
    {
        $leftGlow = imagecolorallocatealpha($canvas, $palette['accent'][0], $palette['accent'][1], $palette['accent'][2], 86);
        $rightGlow = imagecolorallocatealpha($canvas, $palette['soft'][0], $palette['soft'][1], $palette['soft'][2], 98);
        $topGlow = imagecolorallocatealpha($canvas, $palette['hot'][0], $palette['hot'][1], $palette['hot'][2], 108);

        imagefilledellipse(
            $canvas,
            $this->seededNumber($slug, 'glow-left-x', 180, 380),
            $this->seededNumber($slug, 'glow-left-y', 70, 180),
            $this->seededNumber($slug, 'glow-left-w', 340, 620),
            $this->seededNumber($slug, 'glow-left-h', 260, 540),
            $leftGlow
        );
        imagefilledellipse(
            $canvas,
            $this->seededNumber($slug, 'glow-right-x', 1080, 1360),
            $this->seededNumber($slug, 'glow-right-y', 120, 280),
            $this->seededNumber($slug, 'glow-right-w', 420, 760),
            $this->seededNumber($slug, 'glow-right-h', 320, 660),
            $rightGlow
        );
        imagefilledellipse(
            $canvas,
            $this->seededNumber($slug, 'glow-top-x', 520, 980),
            -20,
            $this->seededNumber($slug, 'glow-top-w', 260, 560),
            $this->seededNumber($slug, 'glow-top-h', 160, 340),
            $topGlow
        );
    }

    /**
     * @param  array<string, array{0:int,1:int,2:int}>  $palette
     */
    private function drawOrbitalVariant($canvas, string $slug, array $palette): void
    {
        $orb = imagecolorallocatealpha($canvas, $palette['hot'][0], $palette['hot'][1], $palette['hot'][2], 52);
        $ring = imagecolorallocatealpha($canvas, $palette['soft'][0], $palette['soft'][1], $palette['soft'][2], 78);

        $orbX = $this->seededNumber($slug, 'orb-x', 220, 1180);
        $orbY = $this->seededNumber($slug, 'orb-y', 72, 210);
        $orbSize = $this->seededNumber($slug, 'orb-size', 120, 240);

        imagefilledellipse($canvas, $orbX, $orbY, $orbSize, $orbSize, $orb);

        foreach ([1.6, 2.4, 3.2] as $multiplier) {
            imageellipse(
                $canvas,
                $orbX,
                $orbY,
                (int) round($orbSize * $multiplier),
                (int) round(($orbSize * $multiplier) * 0.64),
                $ring
            );
        }
    }

    /**
     * @param  array<string, array{0:int,1:int,2:int}>  $palette
     */
    private function drawMountainVariant($canvas, string $slug, array $palette): void
    {
        $backdrop = imagecolorallocatealpha($canvas, $palette['secondary'][0], $palette['secondary'][1], $palette['secondary'][2], 68);
        $foreground = imagecolorallocatealpha($canvas, $palette['base'][0], $palette['base'][1], $palette['base'][2], 28);

        imagefilledpolygon($canvas, [
            0, 292,
            180, $this->seededNumber($slug, 'mountain-a', 120, 220),
            390, 284,
            620, $this->seededNumber($slug, 'mountain-b', 110, 240),
            860, 286,
            1100, $this->seededNumber($slug, 'mountain-c', 118, 210),
            1500, 278,
            1500, 400,
            0, 400,
        ], $backdrop);

        imagefilledpolygon($canvas, [
            0, 338,
            220, $this->seededNumber($slug, 'peak-a', 180, 260),
            420, 340,
            760, $this->seededNumber($slug, 'peak-b', 170, 250),
            990, 344,
            1320, $this->seededNumber($slug, 'peak-c', 178, 270),
            1500, 336,
            1500, 400,
            0, 400,
        ], $foreground);
    }

    /**
     * @param  array<string, array{0:int,1:int,2:int}>  $palette
     */
    private function drawGridVariant($canvas, string $slug, array $palette): void
    {
        $grid = imagecolorallocatealpha($canvas, $palette['soft'][0], $palette['soft'][1], $palette['soft'][2], 102);
        $horizon = $this->seededNumber($slug, 'grid-horizon', 180, 250);

        for ($x = -180; $x <= self::BANNER_WIDTH + 180; $x += 110) {
            imageline($canvas, $x, self::BANNER_HEIGHT, $this->seededNumber($slug, 'grid-tilt-'.$x, 580, 920), $horizon, $grid);
        }

        for ($line = 0; $line < 8; $line++) {
            $y = $horizon + ($line * 22);
            imageline($canvas, 0, $y, self::BANNER_WIDTH, $y + $this->seededNumber($slug, 'grid-slope-'.$line, 10, 34), $grid);
        }
    }

    /**
     * @param  array<string, array{0:int,1:int,2:int}>  $palette
     */
    private function drawShardVariant($canvas, string $slug, array $palette): void
    {
        $panel = imagecolorallocatealpha($canvas, $palette['accent'][0], $palette['accent'][1], $palette['accent'][2], 82);
        $panelSoft = imagecolorallocatealpha($canvas, $palette['soft'][0], $palette['soft'][1], $palette['soft'][2], 100);

        foreach (range(0, 5) as $index) {
            $x = $this->seededNumber($slug, 'shard-x-'.$index, 40 + ($index * 180), 240 + ($index * 180));
            $top = $this->seededNumber($slug, 'shard-top-'.$index, 10, 140);
            $bottom = $this->seededNumber($slug, 'shard-bottom-'.$index, 250, 390);
            $width = $this->seededNumber($slug, 'shard-width-'.$index, 80, 180);
            $color = $index % 2 === 0 ? $panel : $panelSoft;

            imagefilledpolygon($canvas, [
                $x, $top,
                $x + $width, $top + 30,
                $x + (int) round($width * 0.72), $bottom,
                $x - (int) round($width * 0.16), $bottom - 42,
            ], $color);
        }
    }

    /**
     * @param  array<string, array{0:int,1:int,2:int}>  $palette
     */
    private function drawNoiseLines($canvas, string $slug, array $palette): void
    {
        $lineShade = imagecolorallocatealpha($canvas, 255, 255, 255, 112);
        $softLine = imagecolorallocatealpha($canvas, $palette['soft'][0], $palette['soft'][1], $palette['soft'][2], 106);
        $lineCount = $this->seededNumber($slug, 'line-count', 7, 12);

        foreach (range(0, $lineCount - 1) as $index) {
            $x = $this->seededNumber($slug, 'line-x-'.$index, 40, 1460);
            $offset = $this->seededNumber($slug, 'line-offset-'.$index, 80, 220);
            $color = $index % 3 === 0 ? $softLine : $lineShade;
            imageline($canvas, $x, 0, $x - $offset, self::BANNER_HEIGHT, $color);
        }
    }

    /**
     * @param  array<string, array{0:int,1:int,2:int}>  $palette
     */
    private function drawProfileGlow($canvas, string $slug, array $palette): void
    {
        $leftGlow = imagecolorallocatealpha($canvas, $palette['accent'][0], $palette['accent'][1], $palette['accent'][2], 84);
        $rightGlow = imagecolorallocatealpha($canvas, $palette['soft'][0], $palette['soft'][1], $palette['soft'][2], 94);
        $centerGlow = imagecolorallocatealpha($canvas, $palette['hot'][0], $palette['hot'][1], $palette['hot'][2], 106);

        imagefilledellipse(
            $canvas,
            $this->seededNumber($slug, 'profile-left-x', 140, 260),
            $this->seededNumber($slug, 'profile-left-y', 140, 320),
            $this->seededNumber($slug, 'profile-left-w', 260, 420),
            $this->seededNumber($slug, 'profile-left-h', 260, 420),
            $leftGlow
        );
        imagefilledellipse(
            $canvas,
            $this->seededNumber($slug, 'profile-right-x', 520, 700),
            $this->seededNumber($slug, 'profile-right-y', 180, 380),
            $this->seededNumber($slug, 'profile-right-w', 300, 460),
            $this->seededNumber($slug, 'profile-right-h', 300, 460),
            $rightGlow
        );
        imagefilledellipse($canvas, 400, 410, 340, 340, $centerGlow);
    }

    /**
     * @param  array<string, array{0:int,1:int,2:int}>  $palette
     */
    private function drawProfilePanels($canvas, string $slug, array $palette): void
    {
        $panel = imagecolorallocatealpha($canvas, $palette['soft'][0], $palette['soft'][1], $palette['soft'][2], 98);
        $line = imagecolorallocatealpha($canvas, 255, 255, 255, 112);

        foreach (range(0, 4) as $index) {
            $x = $this->seededNumber($slug, 'profile-panel-x-'.$index, 30 + ($index * 120), 120 + ($index * 120));
            $width = $this->seededNumber($slug, 'profile-panel-width-'.$index, 80, 150);
            $top = $this->seededNumber($slug, 'profile-panel-top-'.$index, 40, 180);
            $bottom = $this->seededNumber($slug, 'profile-panel-bottom-'.$index, 500, 760);

            imagefilledpolygon($canvas, [
                $x, $top,
                $x + $width, $top + 34,
                $x + (int) round($width * 0.74), $bottom,
                $x - (int) round($width * 0.18), $bottom - 42,
            ], $panel);
        }

        foreach (range(0, 5) as $index) {
            $x = $this->seededNumber($slug, 'profile-line-x-'.$index, 50, 750);
            $offset = $this->seededNumber($slug, 'profile-line-offset-'.$index, 100, 220);
            imageline($canvas, $x, 0, $x - $offset, self::PROFILE_SIZE, $line);
        }
    }

    /**
     * @param  array<string, array{0:int,1:int,2:int}>  $palette
     */
    private function drawProfileCore($canvas, string $slug, string $name, array $palette): void
    {
        $outerRing = imagecolorallocatealpha($canvas, 255, 255, 255, 92);
        $innerRing = imagecolorallocatealpha($canvas, $palette['hot'][0], $palette['hot'][1], $palette['hot'][2], 58);
        $core = imagecolorallocatealpha($canvas, $palette['base'][0], $palette['base'][1], $palette['base'][2], 22);
        $text = imagecolorallocate($canvas, 248, 250, 252);

        imagefilledellipse($canvas, 400, 400, 360, 360, $core);
        imageellipse($canvas, 400, 400, 360, 360, $outerRing);
        imageellipse($canvas, 400, 400, 270, 270, $innerRing);
        imageellipse($canvas, 400, 400, 210, 210, $outerRing);

        $initials = $this->initials($name);
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($initials);
        $textHeight = imagefontheight($font);
        imagestring(
            $canvas,
            $font,
            (int) round((self::PROFILE_SIZE - $textWidth) / 2),
            (int) round((self::PROFILE_SIZE - $textHeight) / 2),
            $initials,
            $text
        );
    }

    private function storeFallbackPng(string $path): string
    {
        Storage::disk('public')->put($path, base64_decode(self::FALLBACK_PNG, true) ?: '');

        return Storage::disk('public')->url($path);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function palette(string $seed, int $min, int $max): array
    {
        $hash = md5($seed);

        return [
            $this->colorComponent(substr($hash, 0, 2), $min, $max),
            $this->colorComponent(substr($hash, 2, 2), $min, $max),
            $this->colorComponent(substr($hash, 4, 2), $min, $max),
        ];
    }

    private function colorComponent(string $hex, int $min, int $max): int
    {
        $raw = hexdec($hex);
        $range = max(1, $max - $min);

        return $min + ($raw % ($range + 1));
    }

    private function seededNumber(string $slug, string $key, int $min, int $max): int
    {
        $raw = hexdec(substr(md5($slug.'|'.$key), 0, 8));
        $range = max(1, $max - $min);

        return $min + ($raw % ($range + 1));
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = collect($parts)
            ->filter()
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('');

        if ($letters !== '') {
            return $letters;
        }

        return mb_strtoupper(mb_substr(trim($name), 0, 2)) ?: 'GP';
    }
}

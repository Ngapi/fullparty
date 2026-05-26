<?php

namespace App\Services\Runs;

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

class GeneratedRunImageService
{
    private const FALLBACK_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGOSHzRgAAAAABJRU5ErkJggg==';

    private const IMAGE_WIDTH = 640;

    private const IMAGE_HEIGHT = 960;

    public function generateResultImage(Activity $activity, string $activityTypeName): string
    {
        $seed = implode('|', [
            (string) $activity->id,
            (string) ($activity->activityType?->slug ?? ''),
            $activityTypeName,
            (string) ($activity->difficulty ?? $activity->activityTypeVersion?->difficulty ?? ''),
        ]);

        $hash = substr(md5($seed), 0, 10);
        $path = 'runs/generated-discovery/'.$activity->id.'-'.$hash.'.png';

        Storage::disk('public')->put($path, $this->buildPng($activity, $seed));

        return Storage::disk('public')->url($path);
    }

    private function buildPng(Activity $activity, string $seed): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return base64_decode(self::FALLBACK_PNG, true) ?: '';
        }

        [$base, $secondary, $accent, $soft] = $this->paletteForDifficulty(
            $activity->activityTypeVersion?->difficulty ?? $activity->activityType?->currentPublishedVersion?->difficulty,
            $seed,
        );

        $canvas = imagecreatetruecolor(self::IMAGE_WIDTH, self::IMAGE_HEIGHT);

        if (! $canvas) {
            return base64_decode(self::FALLBACK_PNG, true) ?: '';
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        for ($y = 0; $y < self::IMAGE_HEIGHT; $y++) {
            $mix = $y / max(1, self::IMAGE_HEIGHT - 1);
            $red = (int) round($base[0] + (($secondary[0] - $base[0]) * $mix));
            $green = (int) round($base[1] + (($secondary[1] - $base[1]) * $mix));
            $blue = (int) round($base[2] + (($secondary[2] - $base[2]) * $mix));
            $lineColor = imagecolorallocate($canvas, $red, $green, $blue);
            imageline($canvas, 0, $y, self::IMAGE_WIDTH, $y, $lineColor);
        }

        $accentGlow = imagecolorallocatealpha($canvas, $accent[0], $accent[1], $accent[2], 74);
        $softGlow = imagecolorallocatealpha($canvas, $soft[0], $soft[1], $soft[2], 92);
        $mountainBack = imagecolorallocatealpha($canvas, 255, 255, 255, 112);
        $mountainFront = imagecolorallocatealpha($canvas, 6, 10, 18, 42);
        $line = imagecolorallocatealpha($canvas, 255, 255, 255, 116);

        imagefilledellipse($canvas, 162, 180, 264, 264, $softGlow);
        imagefilledellipse($canvas, 500, 160, 344, 344, $accentGlow);
        imagefilledpolygon($canvas, [0, 710, 118, 520, 228, 616, 338, 442, 454, 584, 560, 410, 640, 482, 640, 960, 0, 960], $mountainBack);
        imagefilledpolygon($canvas, [0, 818, 142, 640, 242, 710, 372, 556, 486, 708, 640, 598, 640, 960, 0, 960], $mountainFront);

        foreach ([122, 278, 436, 560] as $index => $x) {
            imageline($canvas, $x, 0, $index % 2 === 0 ? max(0, $x - 76) : min(self::IMAGE_WIDTH, $x + 84), self::IMAGE_HEIGHT, $line);
        }

        ob_start();
        imagepng($canvas);
        $binary = ob_get_clean();
        imagedestroy($canvas);

        return is_string($binary) && $binary !== ''
            ? $binary
            : (base64_decode(self::FALLBACK_PNG, true) ?: '');
    }

    /**
     * @return array{
     *     0: array{0:int,1:int,2:int},
     *     1: array{0:int,1:int,2:int},
     *     2: array{0:int,1:int,2:int},
     *     3: array{0:int,1:int,2:int}
     * }
     */
    private function paletteForDifficulty(?string $difficulty, string $seed): array
    {
        return match ($difficulty) {
            'savage' => [
                $this->palette($seed.'-base', 18, 42),
                $this->palette($seed.'-secondary', 58, 34),
                $this->palette($seed.'-accent', 150, 72),
                $this->palette($seed.'-soft', 220, 158),
            ],
            'ultimate' => [
                $this->palette($seed.'-base', 16, 38),
                $this->palette($seed.'-secondary', 42, 52),
                $this->palette($seed.'-accent', 150, 38),
                $this->palette($seed.'-soft', 232, 96),
            ],
            'chaotic' => [
                $this->palette($seed.'-base', 16, 40),
                $this->palette($seed.'-secondary', 40, 56),
                $this->palette($seed.'-accent', 96, 48),
                $this->palette($seed.'-soft', 176, 120),
            ],
            'criterion' => [
                $this->palette($seed.'-base', 14, 34),
                $this->palette($seed.'-secondary', 26, 56),
                $this->palette($seed.'-accent', 32, 120),
                $this->palette($seed.'-soft', 110, 220),
            ],
            'extreme', 'unreal' => [
                $this->palette($seed.'-base', 14, 32),
                $this->palette($seed.'-secondary', 34, 56),
                $this->palette($seed.'-accent', 110, 82),
                $this->palette($seed.'-soft', 224, 188),
            ],
            default => [
                $this->palette($seed.'-base', 16, 34),
                $this->palette($seed.'-secondary', 30, 52),
                $this->palette($seed.'-accent', 70, 140),
                $this->palette($seed.'-soft', 160, 228),
            ],
        };
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
}

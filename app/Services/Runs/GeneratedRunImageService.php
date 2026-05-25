<?php

namespace App\Services\Runs;

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

class GeneratedRunImageService
{
    public function generateResultImage(Activity $activity, string $activityTypeName): string
    {
        $seed = implode('|', [
            (string) $activity->id,
            (string) ($activity->activityType?->slug ?? ''),
            $activityTypeName,
            (string) ($activity->difficulty ?? $activity->activityTypeVersion?->difficulty ?? ''),
        ]);

        $hash = substr(md5($seed), 0, 10);
        $path = 'runs/generated-discovery/'.$activity->id.'-'.$hash.'.svg';

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, $this->buildSvg($activity, $activityTypeName, $seed));
        }

        return Storage::disk('public')->url($path);
    }

    private function buildSvg(Activity $activity, string $activityTypeName, string $seed): string
    {
        [$base, $secondary, $accent, $soft] = $this->paletteForDifficulty(
            $activity->activityTypeVersion?->difficulty ?? $activity->activityType?->currentPublishedVersion?->difficulty,
            $seed,
        );

        $title = $this->escapeSvg($activityTypeName);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 960" role="img" aria-label="{$title}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="rgb({$base[0]},{$base[1]},{$base[2]})"/>
      <stop offset="100%" stop-color="rgb({$secondary[0]},{$secondary[1]},{$secondary[2]})"/>
    </linearGradient>
    <radialGradient id="glowA" cx="28%" cy="18%" r="62%">
      <stop offset="0%" stop-color="rgba({$accent[0]},{$accent[1]},{$accent[2]},0.58)"/>
      <stop offset="100%" stop-color="rgba({$accent[0]},{$accent[1]},{$accent[2]},0)"/>
    </radialGradient>
    <radialGradient id="glowB" cx="82%" cy="24%" r="55%">
      <stop offset="0%" stop-color="rgba({$soft[0]},{$soft[1]},{$soft[2]},0.38)"/>
      <stop offset="100%" stop-color="rgba({$soft[0]},{$soft[1]},{$soft[2]},0)"/>
    </radialGradient>
  </defs>
  <rect width="640" height="960" fill="url(#bg)"/>
  <rect width="640" height="960" fill="url(#glowA)"/>
  <rect width="640" height="960" fill="url(#glowB)"/>
  <path d="M0 710L118 520L228 616L338 442L454 584L560 410L640 482V960H0Z" fill="rgba(255,255,255,0.06)"/>
  <path d="M0 818L142 640L242 710L372 556L486 708L640 598V960H0Z" fill="rgba(6,10,18,0.34)"/>
  <circle cx="162" cy="180" r="132" fill="rgba({$soft[0]},{$soft[1]},{$soft[2]},0.10)"/>
  <circle cx="500" cy="160" r="172" fill="rgba(255,255,255,0.06)"/>
  <path d="M78 62L246 134L168 312L34 246Z" fill="rgba({$accent[0]},{$accent[1]},{$accent[2]},0.14)"/>
  <path d="M438 82L598 126L556 276L396 220Z" fill="rgba({$soft[0]},{$soft[1]},{$soft[2]},0.10)"/>
  <path d="M122 0L46 960" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
  <path d="M278 0L232 960" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
  <path d="M436 0L520 960" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
  <path d="M560 0L640 960" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
</svg>
SVG;
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

    private function escapeSvg(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

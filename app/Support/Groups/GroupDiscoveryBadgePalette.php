<?php

namespace App\Support\Groups;

use App\Models\Group;

final class GroupDiscoveryBadgePalette
{
    /**
     * @return array<string, mixed>
     */
    public function lookupColors(): array
    {
        return config('group_discovery.badge_colors', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function badgeMetaForGroup(Group $group): array
    {
        $region = $group->inferredRegion();

        return [
            'recruiting_status' => $this->singleValueBadgeMeta($group->recruiting_status, 'recruiting_statuses'),
            'primary_focuses' => $this->multiValueBadgeMeta($group->primary_focuses ?? [], 'primary_focuses'),
            'experience_expectation' => $this->singleValueBadgeMeta($group->experience_expectation, 'experience_expectations'),
            'voice_expectation' => $this->singleValueBadgeMeta($group->voice_expectation, 'voice_expectations'),
            'preferred_languages' => $this->multiValueBadgeMeta($group->preferred_languages ?? [], 'preferred_languages'),
            'active_days' => $this->multiValueBadgeMeta($group->active_days ?? [], 'active_days'),
            'tags' => $this->tagBadges($group->tags ?? []),
            'region' => $region !== null
                ? [
                    'value' => $region,
                    'color' => $this->colorFor('regions', $region),
                ]
                : null,
        ];
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public function tagBadges(array $tags): array
    {
        return array_values(array_map(fn (string $tag) => [
            'value' => $tag,
            'label' => $tag,
            'color' => $this->tagColor($tag),
        ], $tags));
    }

    public function tagColor(string $tag): string
    {
        $hash = crc32(mb_strtolower(trim($tag)));

        return $this->hashedReadableHexColor(abs((int) $hash));
    }

    public function colorFor(string $category, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return config("group_discovery.badge_colors.{$category}.{$value}");
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, array{value: string, color: string|null}>
     */
    private function multiValueBadgeMeta(array $values, string $category): array
    {
        return array_values(array_map(fn (string $value) => [
            'value' => $value,
            'color' => $this->colorFor($category, $value),
        ], $values));
    }

    /**
     * @return array{value: string, color: string|null}|null
     */
    private function singleValueBadgeMeta(?string $value, string $category): ?array
    {
        if ($value === null) {
            return null;
        }

        return [
            'value' => $value,
            'color' => $this->colorFor($category, $value),
        ];
    }

    private function hashedReadableHexColor(int $hash): string
    {
        $hue = $hash % 360;
        $saturation = 48 + ($hash % 12); // 48-59
        $lightness = 42 + (($hash >> 4) % 8); // 42-49

        $rgb = $this->hslToRgb($hue / 360, $saturation / 100, $lightness / 100);

        while ($this->contrastRatio($rgb, [255, 255, 255]) < 4.5 && $lightness > 28) {
            $lightness -= 2;
            $rgb = $this->hslToRgb($hue / 360, $saturation / 100, $lightness / 100);
        }

        return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function hslToRgb(float $hue, float $saturation, float $lightness): array
    {
        if ($saturation == 0.0) {
            $value = (int) round($lightness * 255);

            return [$value, $value, $value];
        }

        $q = $lightness < 0.5
            ? $lightness * (1 + $saturation)
            : ($lightness + $saturation - ($lightness * $saturation));
        $p = (2 * $lightness) - $q;

        $red = $this->hueToRgb($p, $q, $hue + (1 / 3));
        $green = $this->hueToRgb($p, $q, $hue);
        $blue = $this->hueToRgb($p, $q, $hue - (1 / 3));

        return [
            (int) round($red * 255),
            (int) round($green * 255),
            (int) round($blue * 255),
        ];
    }

    private function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }

        if ($t > 1) {
            $t -= 1;
        }

        if ($t < 1 / 6) {
            return $p + (($q - $p) * 6 * $t);
        }

        if ($t < 1 / 2) {
            return $q;
        }

        if ($t < 2 / 3) {
            return $p + (($q - $p) * ((2 / 3) - $t) * 6);
        }

        return $p;
    }

    /**
     * @param  array{0:int,1:int,2:int}  $foreground
     * @param  array{0:int,1:int,2:int}  $background
     */
    private function contrastRatio(array $foreground, array $background): float
    {
        $foregroundLuminance = $this->relativeLuminance($foreground);
        $backgroundLuminance = $this->relativeLuminance($background);

        $lighter = max($foregroundLuminance, $backgroundLuminance);
        $darker = min($foregroundLuminance, $backgroundLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * @param  array{0:int,1:int,2:int}  $rgb
     */
    private function relativeLuminance(array $rgb): float
    {
        [$red, $green, $blue] = array_map(function (int $value): float {
            $channel = $value / 255;

            return $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return (0.2126 * $red) + (0.7152 * $green) + (0.0722 * $blue);
    }
}

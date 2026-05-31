<?php

namespace App\Services\Groups;

use App\Models\Group;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class GroupEmbedImageService
{
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    private const PROFILE_SIZE = 152;

    private const PADDING = 64;

    private const FALLBACK_BACKGROUND = '/landing.png';

    private const RENDER_VERSION = 2;

    public function urlFor(Group $group): ?string
    {
        if (! $this->isAvailable()) {
            return self::FALLBACK_BACKGROUND;
        }

        $path = $this->pathFor($group);

        if (! Storage::disk('public')->exists($path)) {
            $this->generate($group, $path);
            $this->deleteStaleImages($group, $path);
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : self::FALLBACK_BACKGROUND;
    }

    private function generate(Group $group, string $path): void
    {
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if (! $canvas) {
            return;
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $this->drawBackground($canvas, $group);
        $this->drawOverlay($canvas);
        $this->drawProfileImage($canvas, $group);
        $this->drawName($canvas, $group->name);

        ob_start();
        $encoded = imagepng($canvas, null, 7);
        $binary = ob_get_clean();
        imagedestroy($canvas);

        if (! $encoded || ! is_string($binary) || $binary === '') {
            return;
        }

        Storage::disk('public')->put($path, $binary);
    }

    private function drawBackground(\GdImage $canvas, Group $group): void
    {
        $source = $this->loadImage($group->banner_image_url);

        if ($source) {
            $this->copyObjectCover($source, $canvas, 0, 0, self::WIDTH, self::HEIGHT);
            imagedestroy($source);

            return;
        }

        $fallback = $this->loadImage(self::FALLBACK_BACKGROUND);

        if ($fallback) {
            $this->copyObjectCover($fallback, $canvas, 0, 0, self::WIDTH, self::HEIGHT);
            imagedestroy($fallback);

            return;
        }

        for ($y = 0; $y < self::HEIGHT; $y++) {
            $mix = $y / max(1, self::HEIGHT - 1);
            $red = (int) round(10 + (34 * $mix));
            $green = (int) round(12 + (20 * $mix));
            $blue = (int) round(24 + (52 * $mix));
            $color = imagecolorallocate($canvas, $red, $green, $blue);
            imageline($canvas, 0, $y, self::WIDTH, $y, $color);
        }

        $glow = imagecolorallocatealpha($canvas, 151, 88, 255, 88);
        imagefilledellipse($canvas, 900, 160, 620, 420, $glow);
    }

    private function drawOverlay(\GdImage $canvas): void
    {
        $dark = imagecolorallocatealpha($canvas, 5, 7, 15, 44);
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $dark);

        for ($x = 0; $x < self::WIDTH; $x++) {
            $strength = max(0, 96 - (int) round(($x / self::WIDTH) * 108));
            $shade = imagecolorallocatealpha($canvas, 0, 0, 0, max(0, 127 - $strength));
            imageline($canvas, $x, 0, $x, self::HEIGHT, $shade);
        }

        $bottom = imagecolorallocatealpha($canvas, 0, 0, 0, 38);
        imagefilledrectangle($canvas, 0, self::HEIGHT - 180, self::WIDTH, self::HEIGHT, $bottom);
    }

    private function drawProfileImage(\GdImage $canvas, Group $group): void
    {
        $source = $this->loadImage($group->profile_picture_url);

        if (! $source) {
            return;
        }

        $x = self::WIDTH - self::PADDING - self::PROFILE_SIZE;
        $y = self::PADDING;
        $profile = imagecreatetruecolor(self::PROFILE_SIZE, self::PROFILE_SIZE);

        if (! $profile) {
            imagedestroy($source);

            return;
        }

        imagealphablending($profile, false);
        imagesavealpha($profile, true);
        $transparent = imagecolorallocatealpha($profile, 0, 0, 0, 127);
        imagefill($profile, 0, 0, $transparent);

        $this->copyObjectCover($source, $profile, 0, 0, self::PROFILE_SIZE, self::PROFILE_SIZE);
        $this->applyCircleMask($profile);

        $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 60);
        imagefilledellipse(
            $canvas,
            $x + (int) (self::PROFILE_SIZE / 2),
            $y + (int) (self::PROFILE_SIZE / 2) + 8,
            self::PROFILE_SIZE + 28,
            self::PROFILE_SIZE + 28,
            $shadow
        );

        imagecopy($canvas, $profile, $x, $y, 0, 0, self::PROFILE_SIZE, self::PROFILE_SIZE);

        $border = imagecolorallocatealpha($canvas, 255, 255, 255, 58);
        imageellipse(
            $canvas,
            $x + (int) (self::PROFILE_SIZE / 2),
            $y + (int) (self::PROFILE_SIZE / 2),
            self::PROFILE_SIZE + 4,
            self::PROFILE_SIZE + 4,
            $border
        );

        imagedestroy($profile);
        imagedestroy($source);
    }

    private function drawName(\GdImage $canvas, string $name): void
    {
        $fontPath = $this->fontPath();
        $maxWidth = self::WIDTH - (self::PADDING * 2) - self::PROFILE_SIZE - 80;

        if ($fontPath && function_exists('imagettftext')) {
            $fontSize = 54;
            $lines = $this->wrapText($name, $fontPath, $fontSize, $maxWidth, 2);
            $lineHeight = 68;
            $totalHeight = count($lines) * $lineHeight;
            $startY = (int) round((self::HEIGHT - $totalHeight) / 2) + 52;
            $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 18);
            $text = imagecolorallocate($canvas, 255, 255, 255);

            foreach ($lines as $index => $line) {
                $y = $startY + ($index * $lineHeight);
                imagettftext($canvas, $fontSize, 0, self::PADDING + 3, $y + 4, $shadow, $fontPath, $line);
                imagettftext($canvas, $fontSize, 0, self::PADDING, $y, $text, $fontPath, $line);
            }

            return;
        }

        $font = 5;
        $line = Str::limit($name, 60, '...');
        $text = imagecolorallocate($canvas, 255, 255, 255);
        imagestring($canvas, $font, self::PADDING, (int) round(self::HEIGHT / 2), $line, $text);
    }

    /**
     * @return array<int, string>
     */
    private function wrapText(string $text, string $fontPath, int $fontSize, int $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $wordCount = count($words);
        $index = 0;

        while ($index < $wordCount && count($lines) < $maxLines) {
            $line = '';

            while ($index < $wordCount) {
                $candidate = $line === '' ? $words[$index] : "{$line} {$words[$index]}";

                if ($this->textWidth($candidate, $fontPath, $fontSize) > $maxWidth) {
                    if ($line === '') {
                        $line = $this->truncateText($candidate, $fontPath, $fontSize, $maxWidth);
                        $index++;
                    }

                    break;
                }

                $line = $candidate;
                $index++;
            }

            if ($line === '') {
                break;
            }

            if (count($lines) === $maxLines - 1 && $index < $wordCount) {
                $line = $this->truncateText(
                    trim($line.' '.implode(' ', array_slice($words, $index))),
                    $fontPath,
                    $fontSize,
                    $maxWidth
                );
                $index = $wordCount;
            }

            $lines[] = $line;
        }

        return $lines !== []
            ? $lines
            : [$this->truncateText($text, $fontPath, $fontSize, $maxWidth)];
    }

    private function truncateText(string $text, string $fontPath, int $fontSize, int $maxWidth): string
    {
        $text = trim($text);

        if ($this->textWidth($text, $fontPath, $fontSize) <= $maxWidth) {
            return $text;
        }

        $ellipsis = '...';

        while ($text !== '') {
            $text = mb_substr($text, 0, max(0, mb_strlen($text) - 1));
            $candidate = rtrim($text).$ellipsis;

            if ($this->textWidth($candidate, $fontPath, $fontSize) <= $maxWidth) {
                return $candidate;
            }
        }

        return $ellipsis;
    }

    private function textWidth(string $text, string $fontPath, int $fontSize): int
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text);

        if (! is_array($box)) {
            return 0;
        }

        return abs((int) $box[2] - (int) $box[0]);
    }

    private function copyObjectCover(\GdImage $source, \GdImage $target, int $targetX, int $targetY, int $targetWidth, int $targetHeight): void
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $cropWidth = (int) round($targetWidth / $scale);
        $cropHeight = (int) round($targetHeight / $scale);
        $sourceX = max(0, (int) floor(($sourceWidth - $cropWidth) / 2));
        $sourceY = max(0, (int) floor(($sourceHeight - $cropHeight) / 2));

        imagecopyresampled(
            $target,
            $source,
            $targetX,
            $targetY,
            $sourceX,
            $sourceY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight
        );
    }

    private function applyCircleMask(\GdImage $image): void
    {
        $size = imagesx($image);
        $radius = $size / 2;
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $distance = sqrt((($x - $radius) ** 2) + (($y - $radius) ** 2));

                if ($distance > $radius) {
                    imagesetpixel($image, $x, $y, $transparent);
                }
            }
        }
    }

    private function loadImage(?string $url): ?\GdImage
    {
        $binary = $this->localImageContents($url);

        if (! $binary) {
            return null;
        }

        $image = @imagecreatefromstring($binary);

        if (! $image) {
            return null;
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    private function localImageContents(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, '/storage/')) {
            $storagePath = Str::after($path, '/storage/');

            return Storage::disk('public')->exists($storagePath)
                ? Storage::disk('public')->get($storagePath)
                : null;
        }

        $publicPath = public_path(ltrim($path, '/'));

        return is_file($publicPath) ? file_get_contents($publicPath) ?: null : null;
    }

    private function pathFor(Group $group): string
    {
        $hash = md5(implode('|', [
            self::RENDER_VERSION,
            $group->id,
            $group->slug,
            $group->name,
            $group->banner_image_url,
            $group->profile_picture_url,
            $group->updated_at?->timestamp,
        ]));

        return sprintf('groups/embeds/%s-%s.png', $group->slug, $hash);
    }

    private function deleteStaleImages(Group $group, string $currentPath): void
    {
        $prefix = sprintf('groups/embeds/%s-', $group->slug);

        foreach (Storage::disk('public')->files('groups/embeds') as $path) {
            if ($path !== $currentPath && str_starts_with($path, $prefix)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function isAvailable(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagecreatefromstring')
            && function_exists('imagecopyresampled')
            && function_exists('imagepng');
    }

    private function fontPath(): ?string
    {
        foreach ([
            public_path('fonts/Inter-Bold.ttf'),
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\segoeuib.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}

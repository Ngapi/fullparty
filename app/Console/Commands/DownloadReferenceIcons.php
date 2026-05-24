<?php

namespace App\Console\Commands;

use App\Services\ManagedImageStorage;
use App\Support\SeedData\ReferenceIconCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DownloadReferenceIcons extends Command
{
    protected $signature = 'reference-icons:download
                            {--force : Redownload files even if they already exist}
                            {--base-path= : Directory that should receive the committed public assets}';

    protected $description = 'Download seeded character class and phantom job reference icons into committed public assets';

    public function handle(ManagedImageStorage $managedImageStorage): int
    {
        $basePath = $this->resolveBasePath((string) ($this->option('base-path') ?? ''));
        $force = (bool) $this->option('force');
        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach (ReferenceIconCatalog::downloadEntries() as $entry) {
            $absolutePath = $this->absolutePath($basePath, $entry['public_path']);

            if (is_file($absolutePath) && ! $force) {
                $skipped++;
                $this->line(sprintf('<comment>Skipped</comment> %s', $entry['public_path']));

                continue;
            }

            try {
                $managedImageStorage->downloadImageToPath(
                    url: $entry['source_url'],
                    field: Str::slug($entry['label'], '_'),
                    absolutePath: $absolutePath,
                );

                $downloaded++;
                $this->line(sprintf('<info>Downloaded</info> %s', $entry['public_path']));
            } catch (ValidationException $exception) {
                $failed++;
                $message = collect($exception->errors())->flatten()->first() ?? 'Unable to download image from the provided URL.';

                $this->error(sprintf('%s (%s)', $entry['public_path'], $message));
            }
        }

        $this->newLine();
        $this->info(sprintf('Downloaded: %d', $downloaded));
        $this->line(sprintf('Skipped: %d', $skipped));
        $this->line(sprintf('Failed: %d', $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resolveBasePath(string $basePathOption): string
    {
        if (blank($basePathOption)) {
            return public_path();
        }

        if (str_starts_with($basePathOption, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $basePathOption) === 1) {
            return $basePathOption;
        }

        return base_path($basePathOption);
    }

    private function absolutePath(string $basePath, string $publicPath): string
    {
        return rtrim($basePath, '\\/').DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($publicPath, '\\/'));
    }
}

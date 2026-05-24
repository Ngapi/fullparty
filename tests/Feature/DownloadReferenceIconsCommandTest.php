<?php

use App\Support\SeedData\ReferenceIconCatalog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('downloads reference icons into committed-style public paths', function () {
    Http::fake(fn () => Http::response('png-binary', 200, [
        'Content-Type' => 'image/png',
    ]));

    $basePath = base_path('bootstrap/testing/reference-icons-'.Str::uuid());

    try {
        $this->artisan('reference-icons:download', [
            '--base-path' => $basePath,
        ])->assertExitCode(0);

        foreach (ReferenceIconCatalog::downloadEntries() as $entry) {
            $absolutePath = $basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entry['public_path']);

            expect(File::exists($absolutePath))->toBeTrue();
        }

        Http::assertSent(function (Request $request) {
            return str_starts_with($request->url(), 'http');
        });
    } finally {
        File::deleteDirectory($basePath);
    }
});

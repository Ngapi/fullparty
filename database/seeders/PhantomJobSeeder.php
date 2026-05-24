<?php

namespace Database\Seeders;

use App\Models\PhantomJob;
use App\Support\SeedData\ReferenceIconCatalog;
use Illuminate\Database\Seeder;

class PhantomJobSeeder extends Seeder
{
    /**
     * Seed the application's phantom jobs.
     */
    public function run(): void
    {
        foreach (ReferenceIconCatalog::phantomJobs() as $phantomJob) {
            PhantomJob::updateOrCreate(
                ['name' => $phantomJob['name']],
                [
                    'max_level' => $phantomJob['max_level'],
                    'icon_url' => $phantomJob['icon_url'],
                    'black_icon_url' => $phantomJob['black_icon_url'],
                    'transparent_icon_url' => $phantomJob['transparent_icon_url'],
                    'sprite_url' => $phantomJob['sprite_url'],
                ]
            );
        }
    }
}

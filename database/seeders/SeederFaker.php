<?php

namespace Database\Seeders;

use Faker\Generator;

if (! function_exists(__NAMESPACE__.'\fake')) {
    function fake(?string $locale = null): Generator
    {
        if ($locale !== null) {
            return app(Generator::class, ['locale' => $locale]);
        }

        return app(Generator::class);
    }
}

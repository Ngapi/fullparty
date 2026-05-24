<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="dark">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="fullparty-authenticated" content="{{ auth()->check() ? '1' : '0' }}">
        <meta name="color-scheme" content="dark">
        <link rel="icon" href="/favicon.ico">
        @vite('resources/css/app.css')
        @vite('resources/js/app.js')
        @inertiaHead
        @routes
    </head>
    <body class="dark bg-neutral-950 text-neutral-50">
        <div class="isolate">
            @inertia
        </div>
    </body>
</html>

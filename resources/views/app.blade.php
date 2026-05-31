<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="dark">
    <head>
        @php
            $serverMeta = $serverMeta ?? app(\App\Support\Seo\ServerMeta::class)->defaults();
            $siteName = $serverMeta['site_name'] ?? config('app.name', 'FullParty');
            $pageTitle = $serverMeta['title'] ?? null;
            $fullTitle = filled($pageTitle) && $pageTitle !== $siteName
                ? $pageTitle.' - '.$siteName
                : $siteName;
            $description = $serverMeta['description'] ?? null;
            $canonicalUrl = $serverMeta['url'] ?? request()->fullUrl();
            $imageUrl = collect($serverMeta['images'] ?? [$serverMeta['image'] ?? null])
                ->filter()
                ->unique()
                ->first();
            $ogType = $serverMeta['type'] ?? 'website';
            $robots = $serverMeta['robots'] ?? 'index, follow';
        @endphp
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="fullparty-authenticated" content="{{ auth()->check() ? '1' : '0' }}">
        <meta name="color-scheme" content="dark">
        <title>{{ $fullTitle }}</title>
        @if (filled($description))
            <meta name="description" content="{{ $description }}">
            <meta property="og:description" content="{{ $description }}">
            <meta name="twitter:description" content="{{ $description }}">
        @endif
        <meta name="robots" content="{{ $robots }}">
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <meta property="og:title" content="{{ $fullTitle }}">
        <meta property="og:type" content="{{ $ogType }}">
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        @if (filled($imageUrl))
            <meta property="og:image" content="{{ $imageUrl }}">
            <meta name="twitter:image" content="{{ $imageUrl }}">
        @endif
        <meta name="twitter:card" content="{{ filled($imageUrl) ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $fullTitle }}">
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

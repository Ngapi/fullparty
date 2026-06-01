<?php

namespace App\Services\Notifications;

use App\Http\Middleware\ApplyLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class NotificationActionUrlService
{
    public function forRequest(Request $request, ?string $actionUrl): string
    {
        if (blank($actionUrl)) {
            return route('account.notifications.index');
        }

        if (str_starts_with($actionUrl, '/') && ! str_starts_with($actionUrl, '//')) {
            return $this->localizedPath($actionUrl, $this->currentLocale($request));
        }

        $parts = parse_url($actionUrl);

        if (! $this->isSafeSameOriginUrl($request->getSchemeAndHttpHost(), $parts)) {
            return route('account.notifications.index');
        }

        return $this->localizedPath($this->pathWithSuffixes($parts), $this->currentLocale($request));
    }

    public function forBrowserLocalePreference(?string $actionUrl): ?string
    {
        if (blank($actionUrl)) {
            return null;
        }

        if (str_starts_with($actionUrl, '/') && ! str_starts_with($actionUrl, '//')) {
            return $this->unlocalizedPath($actionUrl);
        }

        $parts = parse_url($actionUrl);

        if (! $this->isSafeSameOriginUrl((string) config('app.url'), $parts)) {
            return $actionUrl;
        }

        $path = $this->unlocalizedPath($this->pathWithSuffixes($parts));

        return url($path);
    }

    private function localizedPath(string $path, string $locale): string
    {
        $parts = parse_url($path);

        if (! is_array($parts)) {
            return route('account.notifications.index');
        }

        $segments = $this->pathSegments((string) ($parts['path'] ?? '/'));

        if ($segments !== [] && in_array($segments[0], ApplyLocale::SUPPORTED_LOCALES, true)) {
            array_shift($segments);
        }

        $localizedPath = '/'.$locale.($segments !== [] ? '/'.implode('/', $segments) : '');

        return $localizedPath.$this->queryAndFragment($parts);
    }

    private function unlocalizedPath(string $path): string
    {
        $parts = parse_url($path);

        if (! is_array($parts)) {
            return $path;
        }

        $segments = $this->pathSegments((string) ($parts['path'] ?? '/'));

        if ($segments !== [] && in_array($segments[0], ApplyLocale::SUPPORTED_LOCALES, true)) {
            array_shift($segments);
        }

        $unlocalizedPath = '/'.implode('/', $segments);

        return ($unlocalizedPath === '/' ? '/' : rtrim($unlocalizedPath, '/')).$this->queryAndFragment($parts);
    }

    private function currentLocale(Request $request): string
    {
        $locale = App::getLocale()
            ?: $request->session()->get('locale')
            ?: $request->cookie('locale')
            ?: config('app.locale');

        return in_array($locale, ApplyLocale::SUPPORTED_LOCALES, true)
            ? $locale
            : config('app.locale');
    }

    /**
     * @param  array<string, mixed>|false  $parts
     */
    private function isSafeSameOriginUrl(string $origin, array|false $parts): bool
    {
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $actionOrigin = strtolower($parts['scheme'].'://'.$parts['host'].$port);
        $requestOrigin = strtolower(rtrim($origin, '/'));

        return hash_equals($requestOrigin, $actionOrigin);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function pathWithSuffixes(array $parts): string
    {
        return ((string) ($parts['path'] ?? '/')).$this->queryAndFragment($parts);
    }

    /**
     * @return array<int, string>
     */
    private function pathSegments(string $path): array
    {
        return collect(explode('/', trim('/'.ltrim($path, '/'), '/')))
            ->filter(fn (string $segment) => $segment !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function queryAndFragment(array $parts): string
    {
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $query.$fragment;
    }
}

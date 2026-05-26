<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\User;
use App\Policies\GroupActivityPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::defaults(['locale' => app()->getLocale()]);

        Gate::policy(Activity::class, GroupActivityPolicy::class);
        Gate::define('viewPulse', fn (?User $user) => (bool) $user?->is_admin);

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', Provider::class);
            $event->extendSocialite('xivauth', \SocialiteProviders\XIVAuth\Provider::class);
        });
    }
}

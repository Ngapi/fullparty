<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\User;
use App\Policies\GroupActivityPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
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

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', Provider::class);
            $event->extendSocialite('xivauth', \SocialiteProviders\XIVAuth\Provider::class);
        });
    }
}

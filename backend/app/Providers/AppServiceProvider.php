<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        // Behind a tunnel (e.g. Cloudflare) the request host can be "localhost"; force links to use APP_URL instead.
        if (config('bwops.force_app_url') && ! app()->runningInConsole()) {
            \Illuminate\Support\Facades\URL::forceRootUrl(rtrim((string) config('app.url'), '/'));
            if (str_starts_with((string) config('app.url'), 'https://')) {
                \Illuminate\Support\Facades\URL::forceScheme('https');
            }
        }

        // Owners can do everything; everyone else is limited by their role's permissions.
        \Illuminate\Support\Facades\Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole('Owner') ? true : null);

        \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
            'user' => \App\Models\User::class,
            'operator' => \App\Models\OperatorProfile::class,
            'vehicle' => \App\Models\Vehicle::class,
            'service' => \App\Models\Service::class,
            'rate_card' => \App\Models\RateCard::class,
            'extra' => \App\Models\Extra::class,
            'page' => \App\Models\Page::class,
            'faq' => \App\Models\Faq::class,
            'booking' => \App\Models\Booking::class,
            'company' => \App\Models\Company::class,
            'client_profile' => \App\Models\ClientProfile::class,
            'document' => \App\Models\Document::class,
            'feature' => \App\Models\Feature::class,
            'setting' => \App\Models\Setting::class,
            'booking_assignment' => \App\Models\BookingAssignment::class,
            'invoice' => \App\Models\Invoice::class,
            'payout' => \App\Models\Payout::class,
            'surcharge_rule' => \App\Models\SurchargeRule::class,
            'role' => \Spatie\Permission\Models\Role::class,
            'permission' => \Spatie\Permission\Models\Permission::class,
        ]);
    }
}

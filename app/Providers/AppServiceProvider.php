<?php

namespace App\Providers;

use Modules\Authorization\app\Models\Admin;
use App\Core\Contracts\MediaServiceInterface;
use App\Core\Services\MediaService;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            MediaServiceInterface::class,
            MediaService::class
        );

        // Payments: type-hint PaymentGateway for the default gateway, or the manager to pick one.
        $this->app->singleton(\App\Services\Payment\PaymentGatewayManager::class);
        $this->app->bind(
            \App\Services\Payment\Contracts\PaymentGateway::class,
            fn ($app) => $app->make(\App\Services\Payment\PaymentGatewayManager::class)->driver()
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['ar', 'en']) 
                ->labels([
                    'ar' => 'العربية',
                    'en' => 'English',
                ])
                ->circular(); 
        });
        // Policies for third-party models, which Laravel cannot auto-discover.
        Gate::policy(\Spatie\Activitylog\Models\Activity::class, \App\Policies\ActivityPolicy::class);
        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);

        Gate::before(function ($user, $ability) {
            // Super admins bypass every permission and policy check.
            return $user instanceof Admin && $user->isSuperAdmin() ? true : null;
        });
    }
}

<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Models\InvoiceTestRel;
use App\Observers\InvoiceTestRelObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Services\AuditTrail::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureCommands();
        $this->configureModels();
        $this->configureUrl();
        $this->configureRateLimiting();
        InvoiceTestRel::observe(InvoiceTestRelObserver::class);
        \App\Models\Invoice::observe(\App\Observers\PortalInvoiceObserver::class);
        foreach (['creating', 'created', 'updating', 'updated', 'deleting', 'deleted', 'restoring', 'restored'] as $event) {
            \Illuminate\Support\Facades\Event::listen('eloquent.'.$event.': *', function ($name, $models) {
                app(\App\Services\AuditTrail::class)->observe($name, $models);
            });
        }
    }

    /**
     * Prevent destructive commands in production
     */
    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands(
            $this->app->isProduction(),
        );
    }

    /**
     * Enable strict mode for Eloquent models in non-production
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
    }

    /**
     * Force HTTPS in production
     */
    private function configureUrl(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Configure rate limiting for API routes
     */
    private function configureRateLimiting(): void
    {
        // General API rate limit
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Separate buckets: reading the portal must not consume its opt-in/test limits.
        RateLimiter::for('portal-app', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        foreach (['portal-subscribe' => 10, 'portal-push-test' => 3] as $name => $maximum) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($maximum)
                ->by(hash('sha256', (string) $request->route('token')).'|'.$request->ip()));
        }

        // Strict rate limit for login attempts - 5 per minute
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email') . '|' . $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again later.',
                    ], 429);
                });
        });

        // Strict rate limit for registration - 3 per hour (production only)
        RateLimiter::for('register', function (Request $request) {
            // Skip rate limiting in non-production environments
            if (! app()->isProduction()) {
                return Limit::none();
            }

            return Limit::perHour(3)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many registration attempts. Please try again later.',
                    ], 429);
                });
        });

        // Strict rate limit for password reset - 3 per hour
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perHour(3)->by($request->input('email') . '|' . $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many password reset attempts. Please try again later.',
                    ], 429);
                });
        });

        // Strict rate limit for OTP/verification code - 5 per 15 minutes
        RateLimiter::for('verification', function (Request $request) {
            return Limit::perMinutes(15, 5)->by($request->input('email') . '|' . $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many verification attempts. Please try again later.',
                    ], 429);
                });
        });

        // Rate limit for resending codes - 3 per hour
        RateLimiter::for('resend-code', function (Request $request) {
            return Limit::perHour(3)->by($request->input('email') . '|' . $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many resend requests. Please try again later.',
                    ], 429);
                });
        });
    }
}

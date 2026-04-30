<?php

namespace A2ZWeb\Affiliate;

use A2ZWeb\Affiliate\Console\Commands\CloseMonthCommand;
use A2ZWeb\Affiliate\Console\Commands\MigrateFromJijunairCommand;
use A2ZWeb\Affiliate\Console\Commands\RecalcPartnerCommand;
use A2ZWeb\Affiliate\Contracts\ReferredUserInfoResolver;
use A2ZWeb\Affiliate\Contracts\RevenueResolver;
use A2ZWeb\Affiliate\Events\StatementIssued;
use A2ZWeb\Affiliate\Events\StatementPaid;
use A2ZWeb\Affiliate\Http\Middleware\CaptureAffiliateReferral;
use A2ZWeb\Affiliate\Listeners\AttachReferralOnRegister;
use A2ZWeb\Affiliate\Listeners\SendStatementIssuedMails;
use A2ZWeb\Affiliate\Listeners\SendStatementPaidMails;
use A2ZWeb\Affiliate\Livewire\ApplicationForm;
use A2ZWeb\Affiliate\Livewire\PartnerDashboard;
use A2ZWeb\Affiliate\Livewire\PayoutDetailsForm;
use A2ZWeb\Affiliate\Livewire\PayoutRequestForm;
use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use A2ZWeb\Affiliate\Policies\AffiliateCommissionStatementPolicy;
use A2ZWeb\Affiliate\Services\AffiliateCodeGenerator;
use A2ZWeb\Affiliate\Services\CommissionCalculator;
use A2ZWeb\Affiliate\Services\EligibilityChecker;
use A2ZWeb\Affiliate\Services\MonthlyCloser;
use A2ZWeb\Affiliate\Services\NullReferredUserInfoResolver;
use A2ZWeb\Affiliate\Services\NullRevenueResolver;
use A2ZWeb\Affiliate\Services\PartnerStatistics;
use A2ZWeb\Affiliate\Services\PayoutCompletionWorkflow;
use A2ZWeb\Affiliate\Services\PayoutRequestService;
use A2ZWeb\Affiliate\Services\ReferralAttributor;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Nova;
use Livewire\Livewire;

class AffiliateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/affiliate.php', 'affiliate');
        $this->mergeConfigFrom(__DIR__.'/../config/affiliate_statements.php', 'affiliate_statements');

        $this->app->singleton(AffiliateCodeGenerator::class);
        $this->app->singleton(ReferralAttributor::class);
        $this->app->singleton(EligibilityChecker::class);
        $this->app->singleton(CommissionCalculator::class);
        $this->app->singleton(MonthlyCloser::class);
        $this->app->singleton(PayoutRequestService::class);
        $this->app->singleton(PayoutCompletionWorkflow::class);
        $this->app->singleton(PartnerStatistics::class);

        $this->app->bind(RevenueResolver::class, function ($app) {
            $configured = config('affiliate.resolvers.revenue');

            return $configured ? $app->make($configured) : $app->make(NullRevenueResolver::class);
        });

        $this->app->bind(ReferredUserInfoResolver::class, function ($app) {
            $configured = config('affiliate.resolvers.referred_user_info');

            return $configured ? $app->make($configured) : $app->make(NullReferredUserInfoResolver::class);
        });
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerMigrations();
        $this->registerViews();
        $this->registerTranslations();
        $this->registerRoutes();
        $this->registerListeners();
        $this->registerLivewire();
        $this->registerCommands();
        $this->registerMiddleware();
        $this->registerNovaAdminUrlDefaults();
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/affiliate.php' => config_path('affiliate.php'),
        ], 'affiliate-config');

        $this->publishes([
            __DIR__.'/../config/affiliate_statements.php' => config_path('affiliate_statements.php'),
        ], 'affiliate-statements-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'affiliate-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/affiliate'),
        ], 'affiliate-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/affiliate'),
        ], 'affiliate-lang');
    }

    private function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    private function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views/blade', 'affiliate');
        $this->loadViewsFrom(__DIR__.'/../resources/views/livewire', 'affiliate-livewire');
    }

    private function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'affiliate');
    }

    private function registerRoutes(): void
    {
        if (! config('affiliate.routes.enabled', true)) {
            return;
        }

        $config = config('affiliate.routes');

        Route::group([
            'prefix' => $config['prefix'] ?? 'dashboard/affiliate',
            'as' => $config['name_prefix'] ?? 'affiliate.',
            'middleware' => $config['middleware'] ?? ['web', 'auth'],
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    private function registerListeners(): void
    {
        Event::listen(Registered::class, AttachReferralOnRegister::class);
        Event::listen(StatementIssued::class, SendStatementIssuedMails::class);
        Event::listen(StatementPaid::class, SendStatementPaidMails::class);

        Gate::policy(
            AffiliateCommissionStatement::class,
            AffiliateCommissionStatementPolicy::class,
        );
    }

    private function registerLivewire(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        $this->callAfterResolving('livewire', function ($livewire): void {
            $livewire->component('affiliate.partner-dashboard', PartnerDashboard::class);
            $livewire->component('affiliate.application-form', ApplicationForm::class);
            $livewire->component('affiliate.payout-details-form', PayoutDetailsForm::class);
            $livewire->component('affiliate.payout-request-form', PayoutRequestForm::class);
        });
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            CloseMonthCommand::class,
            RecalcPartnerCommand::class,
            MigrateFromJijunairCommand::class,
        ]);
    }

    private function registerMiddleware(): void
    {
        if (! config('affiliate.capture_middleware_enabled', true)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);
        if (method_exists($kernel, 'pushMiddleware')) {
            $kernel->pushMiddleware(CaptureAffiliateReferral::class);
        }
    }

    /**
     * Populate the affiliate.admin_*_url notification CTAs from Nova's path
     * when the host hasn't set them explicitly. Skipped when Nova isn't installed.
     */
    private function registerNovaAdminUrlDefaults(): void
    {
        if (! class_exists(Nova::class)) {
            return;
        }

        $novaPath = rtrim((string) config('nova.path', '/nova'), '/');

        if (! config('affiliate.admin_partner_url')) {
            config(['affiliate.admin_partner_url' => $novaPath.'/resources/affiliate-partners/{id}']);
        }
        if (! config('affiliate.admin_payout_request_url')) {
            config(['affiliate.admin_payout_request_url' => $novaPath.'/resources/affiliate-payout-requests/{id}']);
        }
        if (! config('affiliate.admin_statement_url')) {
            config(['affiliate.admin_statement_url' => $novaPath.'/resources/affiliate-commission-statements/{id}']);
        }
    }
}

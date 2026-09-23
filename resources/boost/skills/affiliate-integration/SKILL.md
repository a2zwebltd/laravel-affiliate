---
name: affiliate-integration
description: Integrate and operate a2zwebltd/laravel-affiliate in a host app, covering the RevenueResolver and ReferredUserInfoResolver contracts, the HasAffiliateProgram trait, referral attribution, config/affiliate.php and config/affiliate_statements.php, view overrides and layout, commission statements, Nova, and the affiliate:* commands. Use it when wiring the package, writing a billing resolver, overriding EligibilityChecker or views, issuing statements or payouts, or testing affiliate code.
---

# Laravel Affiliate integration

## When to use this skill

- Installing `a2zwebltd/laravel-affiliate`, or checking an existing install.
- Writing or debugging a `RevenueResolver` (commissions are all 0) or a `ReferredUserInfoResolver`.
- Referrals are not recorded, or eligibility needs a custom rule.
- Overriding partner views or the layout, or styles are missing.
- Configuring statements (legal entity, PDF disk, queue), payouts or Nova.
- Scheduling or running `affiliate:*` commands, or writing tests around the package.

## Install / wiring checklist

1. `composer require a2zwebltd/laravel-affiliate`. The provider `A2ZWeb\Affiliate\AffiliateServiceProvider` is auto-discovered.
2. `php artisan vendor:publish --tag=affiliate-migrations && php artisan migrate`. Migrations also load from the package without publishing.
3. Optional: `--tag=affiliate-config` (config/affiliate.php) and `--tag=affiliate-statements-config` (config/affiliate_statements.php). Unpublished, every key is still readable through env.
4. Add `use A2ZWeb\Affiliate\Concerns\HasAffiliateProgram;` to the User model. Set `AFFILIATE_USER_MODEL` if the model is not `App\Models\User`.
5. Bind a `RevenueResolver` (required, otherwise `NullRevenueResolver` returns 0) and, optionally, a `ReferredUserInfoResolver`.
6. Set `AFFILIATE_LAYOUT` to a layout that meets the layout contract below.
7. Set the statement legal entity: `AFFILIATE_STATEMENTS_ENTITY_CODE` and `AFFILIATE_STATEMENTS_LEGAL_NAME` at least.
8. Schedule `affiliate:close-month` and run a queue worker.
9. With Nova: register `NovaIntegration::resources()` and `NovaIntegration::menuSection()`.
10. Set `AFFILIATE_ADMIN_EMAIL`, plus `AFFILIATE_GENERAL_TERMS_URL` and `AFFILIATE_AFFILIATE_TERMS_URL` for the apply form.

## API & config reference

### Contracts (`A2ZWeb\Affiliate\Contracts`)

```php
interface RevenueResolver
{
    // Realised revenue in cents for a fully closed past month. Called by MonthlyCloser.
    public function revenueForUserInMonth(int $userId, int $year, int $month): int;

    // Month-to-date revenue in cents. Feeds the dashboard's current-month estimate. May return 0.
    public function currentMonthRunningRevenueCents(int $userId): int;
}

interface ReferredUserInfoResolver
{
    /** @return array{display_name:string,is_paying:bool,plan:?string} */
    public function infoFor(int $userId): array;
}
```

Binding: the provider resolves `config('affiliate.resolvers.revenue')` and `config('affiliate.resolvers.referred_user_info')` as class-strings through the container. A container bind in the host's `AppServiceProvider::register()` also works, and overrides the config.

`ReferredUserInfoResolver` output appears in the partner's referral table, so never return emails, raw revenue or other private data. `NullReferredUserInfoResolver` returns `['display_name' => 'User #ID', 'is_paying' => false, 'plan' => null]`.

### `HasAffiliateProgram` trait

| Method | Notes |
|---|---|
| `affiliatePartner(): HasOne` | `affiliate_partners.user_id` |
| `affiliateReferralsAsPartner(): HasMany` | referrals this user brought in |
| `affiliateReferralAsReferred(): HasOne` | who referred this user |
| `isAffiliateApproved(): bool` | |
| `isAffiliateEligibleToApply(): bool` | delegates to the container's `EligibilityChecker` |
| `affiliateCode(): ?string` | **creates** a pending share-only partner row on first call |
| `affiliateLink(?string $base = null): ?string` | `{base or app.url}/?{query_param}={code}`, and also creates the row |
| `affiliateApplyDefaults(): array` | pre-fills the apply form: keys `email`, `phone`, `address`, `country_of_tax_residence`, `full_name`. Override it in the User model to map your own profile fields. |

### Key config (`config/affiliate.php`)

| Key | Env | Default |
|---|---|---|
| `user_model` | `AFFILIATE_USER_MODEL` | `App\Models\User` |
| `currency` / `currencies` | `AFFILIATE_CURRENCY` / `AFFILIATE_CURRENCIES` | `usd` / `usd,eur,gbp,pln,aud,cad` |
| `revenue_share_bp` | `AFFILIATE_REVENUE_SHARE_BP` | `3000` (30%) |
| `min_referred_users` | `AFFILIATE_MIN_REFERRED_USERS` | `2`, counting **all** referred signups, paying or not |
| `min_payout_cents` | `AFFILIATE_MIN_PAYOUT_CENTS` | `5000` |
| `cookie_name` / `cookie_ttl_days` / `query_param` | `AFFILIATE_COOKIE_NAME` / `AFFILIATE_COOKIE_TTL_DAYS` / `AFFILIATE_QUERY_PARAM` | `sa_aff` / `60` / `aff` |
| `attribution` | `AFFILIATE_ATTRIBUTION` | `first_touch` (or `last_touch`) |
| `allow_self_referral` | `AFFILIATE_ALLOW_SELF_REFERRAL` | `false` |
| `commission_window.mode` / `.months` | `AFFILIATE_WINDOW_MODE` / `AFFILIATE_WINDOW_MONTHS` | `lifetime` / `12` |
| `layout` | `AFFILIATE_LAYOUT` | `layouts.app` |
| `partner_statement_view` | `AFFILIATE_PARTNER_STATEMENT_VIEW` | `affiliate::partner-statement` (bundled) |
| `invoice_disk` | `AFFILIATE_INVOICE_DISK` | `local` (partner-uploaded payout invoices) |
| `routes.prefix` / `.name_prefix` / `.middleware` | `AFFILIATE_ROUTES_PREFIX` / `AFFILIATE_ROUTES_NAME_PREFIX` / `AFFILIATE_ROUTES_MIDDLEWARE` | `dashboard/affiliate` / `affiliate.` / `web,auth` |
| `routes.enabled`, `capture_middleware_enabled` | none (publish the config) | `true` |
| `admin_partner_url` etc. | `AFFILIATE_ADMIN_*_URL` | filled from `nova.path` when Nova is installed |
| `nova.user_resource` / `.menu_label` / `.menu_icon` | `AFFILIATE_NOVA_*` | `App\Nova\User` / `Affiliate Program` / `currency-dollar` |

Statement config (`config/affiliate_statements.php`): `issuing_entity.{code,legal_name,company_number,company_number_label,address,country,tax_status_note,statement_prefix,support_email}` (`AFFILIATE_STATEMENTS_*`), `default_currency`, `admin_notification_email`, `pdf.disk` (`AFFILIATE_STATEMENTS_DISK`), `pdf.queue` (`AFFILIATE_STATEMENTS_QUEUE`), `agreement.{version,date,product_name}`.

### Routes (group `affiliate.`, prefix `dashboard/affiliate`)

`affiliate.dashboard` (GET /), `affiliate.apply.show` / `affiliate.apply.store` (/apply), `affiliate.payout-details.update` (PATCH /payout-details), `affiliate.payouts.store` (POST /payouts), `affiliate.payouts.cancel` (DELETE /payouts/{payoutRequest}), `affiliate.statements.show`, `affiliate.statements.download`. Host middleware such as `verified` or a custom gate goes in `AFFILIATE_ROUTES_MIDDLEWARE`, for example `web,auth,verified`.

### Events (`A2ZWeb\Affiliate\Events`)

`ApplicationSubmitted`, `ApplicationApproved`, `ApplicationRejected` (`$partner`, `$reason`), `PayoutRequested`, `PayoutApproved`, `PayoutPaid` (`$request`), `StatementIssued`, `StatementPaid` (`$statement`), `MonthClosed` (`$year`, `$month`, `$commissionsTouched`). The package listens to `StatementIssued` and `StatementPaid` to send mails. `StatementCancelled` exists but nothing dispatches it.

### Commands

| Command | Effect / risk |
|---|---|
| `affiliate:close-month {--month=YYYY-MM} {--partner=ID}` | Closes the previous month by default for approved partners. Idempotent: it skips rows already requested or paid. It calls your `RevenueResolver` for each referral, which can be slow against a live billing API. |
| `affiliate:recalc-partner {partner}` | Recomputes one partner's (user id) closed months. Skips requested or paid rows. |
| `affiliate:notify-eligible {--dry-run} {--force}` | Emails **every** currently eligible user. It is not idempotent: each run sends again. Run `--dry-run` first. |
| `affiliate:migrate-from-jijunair {--dry-run}` | One-shot import from the `jijunair/laravel-referral` `referrals` table. Skips users who already have a referral. |

## Recipes

### Stripe / Cashier revenue resolver

```php
namespace App\Affiliate;

use A2ZWeb\Affiliate\Contracts\RevenueResolver;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

class StripeRevenueResolver implements RevenueResolver
{
    public function revenueForUserInMonth(int $userId, int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        if ($end->greaterThanOrEqualTo(Carbon::now()->startOfMonth())) {
            return 0; // only fully closed months
        }

        return (int) Cache::remember(
            sprintf('affiliate:stripe-revenue:%d:%d-%02d', $userId, $year, $month),
            now()->addDay(),
            fn (): int => $this->paidCents($userId, $start, $end),
        );
    }

    public function currentMonthRunningRevenueCents(int $userId): int
    {
        $now = Carbon::now();

        return (int) Cache::remember(
            sprintf('affiliate:stripe-revenue:running:%d:%d-%02d', $userId, $now->year, $now->month),
            now()->addMinutes(15),
            fn (): int => $this->paidCents($userId, $now->copy()->startOfMonth(), $now),
        );
    }

    private function paidCents(int $userId, Carbon $from, Carbon $to): int
    {
        $user = User::query()->find($userId);
        if (! $user?->stripe_id) {
            return 0;
        }

        $total = 0;
        $params = ['customer' => $user->stripe_id, 'status' => 'paid', 'limit' => 100,
            'created' => ['gte' => $from->timestamp, 'lte' => $to->timestamp]];

        try {
            do {
                $page = Cashier::stripe()->invoices->all($params);
                foreach ($page->data as $invoice) {
                    $total += max(0, (int) $invoice->amount_paid - (int) ($invoice->amount_refunded ?? 0));
                }
                $params['starting_after'] = end($page->data)->id ?? null;
            } while ($page->has_more && $params['starting_after']);
        } catch (\Throwable $e) {
            Log::warning('StripeRevenueResolver failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }

        return $total;
    }
}
```

Bind it in `AppServiceProvider::register()`:

```php
$this->app->bind(\A2ZWeb\Affiliate\Contracts\RevenueResolver::class, \App\Affiliate\StripeRevenueResolver::class);
$this->app->bind(\A2ZWeb\Affiliate\Contracts\ReferredUserInfoResolver::class, \App\Affiliate\SubscriptionStatusResolver::class);
```

### ReferredUserInfoResolver (Cashier subscription)

```php
class SubscriptionStatusResolver implements \A2ZWeb\Affiliate\Contracts\ReferredUserInfoResolver
{
    public function infoFor(int $userId): array
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return ['display_name' => 'User #'.$userId, 'is_paying' => false, 'plan' => null];
        }

        $subscription = $user->subscription('default');
        $isPaying = $subscription?->valid() ?? false;

        return [
            'display_name' => $user->name ?: 'User #'.$userId,
            'is_paying' => $isPaying,
            'plan' => $isPaying ? ($subscription->stripe_price ?? 'active') : null,
        ];
    }
}
```

### Custom eligibility (extend and rebind)

`EligibilityChecker` is a container singleton, and the trait, controllers, Livewire forms, `ReferralAttributor` and `affiliate:notify-eligible` all resolve it from the container. Extend it and rebind it:

```php
namespace App\Affiliate;

use A2ZWeb\Affiliate\Services\EligibilityChecker as BaseEligibilityChecker;
use Illuminate\Database\Eloquent\Model;

class EligibilityChecker extends BaseEligibilityChecker
{
    public function isEligibleToApply(Model $user): bool
    {
        if ((bool) ($user->affiliate_apply_override ?? false)) {
            return true; // admin bypass
        }

        return parent::isEligibleToApply($user);
    }
}

// AppServiceProvider::register()
$this->app->bind(\A2ZWeb\Affiliate\Services\EligibilityChecker::class, \App\Affiliate\EligibilityChecker::class);
```

Other public methods you can override: `hasOpenApplication()`, `referredCount()`, `referralsNeeded()`.

### Layout contract and views

Partner pages (`affiliate::partner.dashboard`, `affiliate::partner.apply`, `affiliate::partner-statement`) run `@extends(config('affiliate.layout'))` and fill `@section('title')` and `@section('content')`. They `@push('scripts')` inline JS and use Alpine (`x-data`). The layout must:

```blade
<title>@yield('title')</title>
...
@yield('content')
@stack('scripts')   {{-- after Alpine is loaded --}}
```

Override a view by copying only that file:

- `affiliate::X` goes to `resources/views/vendor/affiliate/X.blade.php`, e.g. `vendor/affiliate/partials/state-approved.blade.php`.
- `affiliate-livewire::X` goes to `resources/views/vendor/affiliate-livewire/X.blade.php`.
- Publish tags: `affiliate-views` (both), `affiliate-views-blade`, `affiliate-views-livewire`. Files under `vendor/affiliate/blade/` or `vendor/affiliate/livewire/`, from the pre-1.3.0 publish tag, are ignored. Move them.

The views use Tailwind classes. Tailwind v4, in `resources/css/app.css`:

```css
@source "../../vendor/a2zwebltd/laravel-affiliate/resources/views";
```

For Tailwind v3, add `./vendor/a2zwebltd/laravel-affiliate/resources/views/**/*.blade.php` to `content`.

A host with its own component layout (e.g. `<x-dashboard-layout>`) can point `AFFILIATE_PARTNER_STATEMENT_VIEW` at its own view. That view receives `$statement` (with `lines` loaded) and `$snapshot` (the partner billing snapshot array).

Livewire (optional) registers the `affiliate.partner-dashboard`, `affiliate.application-form`, `affiliate.payout-details-form` and `affiliate.payout-request-form` components.

### Statements and payouts

- Flow: the partner requests a payout (`PayoutRequestService::create`), an admin approves it and then marks it paid (Nova `Mark as paid`, which runs `PayoutCompletionWorkflow::complete()`). That generates the statement, issues it, records payment and queues `GenerateCommissionStatementPdf`.
- The issuing entity is snapshotted when the draft is generated. `code` and `legal_name` are required. Empty values, or the pre-1.3.0 "Acme Ltd." placeholders (also in a published old `affiliate_statements.php`), make generation, issuing and payout completion throw `A2ZWeb\Affiliate\Exceptions\IssuingEntityNotConfigured` and log an error. `A2ZWeb\Affiliate\Support\IssuingEntity::problems(array)` lists the reasons.
- PDFs are written to `affiliate_statements.pdf.disk` under `affiliate-statements/{entity}/{year}/{number}.pdf` by a queued job on `affiliate_statements.pdf.queue`. Without a worker the partner sees "PDF generating…". Use a private disk.
- Issued statements are immutable. Correct them with `A2ZWeb\Affiliate\Models\AffiliateAdjustment` rows (addition or subtraction, applied at the partner rate or `commission_rate_bp`), which the next payout and statement pick up.
- Admin mail: `affiliate_statements.admin_notification_email` falls back to `affiliate.admin_notification_email`.

### Nova

```php
use A2ZWeb\Affiliate\Nova\NovaIntegration;

protected function resources(): void
{
    Nova::resources(NovaIntegration::resources());
}

// inside Nova::mainMenu(fn (Request $request) => [...])
NovaIntegration::menuSection(),
```

`BelongsTo` user fields use `affiliate.nova.user_resource`.

### Scheduling

```php
// routes/console.php
Schedule::command('affiliate:close-month')->monthlyOn(1, '02:00')->withoutOverlapping()->onOneServer();
```

## Gotchas

- An unbound `RevenueResolver` fails silently, because every commission is 0. Both methods must return **cents**, not decimal amounts.
- `revenueForUserInMonth()` is only meant for closed months. Returning month-to-date values there closes partial revenue.
- Attribution needs `Registered`. A custom signup that skips the event records no referral. Fix it with `event(new \Illuminate\Auth\Events\Registered($user))`. The capture middleware only runs on GET requests that carry `?aff=`, and only for pending or approved partner codes.
- `affiliateLink()` / `affiliateCode()` insert a partner row per user. The dashboard controller already calls `affiliateLink()`. Don't call them in bulk loops, Nova index fields or exports.
- The referral threshold (`min_referred_users`) counts all referrals, paying or not.
- `PartnerStatistics::for()` is cached for `cache_ttl_seconds`. Call `PartnerStatistics::forget($partnerUserId)` after manual data fixes.
- `MonthlyCloser::closeMonth()` throws `InvalidArgumentException` for the current or a future month. A partner only earns for months ending after `program_joined_at` (falling back to `decided_at`, then `created_at`).
- Config must stay closure-free: resolvers are class-strings, never closures.

## Testing

Bind stub resolvers in the test and drive the services directly:

```php
use A2ZWeb\Affiliate\Contracts\RevenueResolver;
use A2ZWeb\Affiliate\Services\MonthlyCloser;

beforeEach(function () {
    config()->set('affiliate_statements.issuing_entity.code', 'test_ltd');
    config()->set('affiliate_statements.issuing_entity.legal_name', 'Test Ltd');

    app()->instance(RevenueResolver::class, new class implements RevenueResolver {
        public function revenueForUserInMonth(int $userId, int $year, int $month): int { return 10_000; }
        public function currentMonthRunningRevenueCents(int $userId): int { return 0; }
    });
});

it('closes last month', function () {
    // First create an approved AffiliatePartner with program_joined_at in the past,
    // plus an AffiliateReferral pointing at it.
    $month = now()->subMonthNoOverflow();
    $touched = app(MonthlyCloser::class)->closeMonth($month->year, $month->month);
    expect($touched)->toBeGreaterThan(0);
});
```

- Use `Bus::fake()` to stop the PDF job, and `Event::fake([StatementIssued::class])` rather than a bare `Event::fake()`, because a bare fake also silences Eloquent model events.
- Test attribution by sending a `GET /?aff=CODE`, then firing `Registered` on a request that carries the `affiliate.cookie_name` cookie.

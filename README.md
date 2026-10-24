# Laravel Affiliate

Generic affiliate / revenue-share engine for Laravel — opt-in workflow, monthly commission closure, admin adjustments, partner-initiated payout requests, immutable commission statements, Blade+Alpine and Livewire dashboards.

[![Packagist Version](https://img.shields.io/packagist/v/a2zwebltd/laravel-affiliate.svg)](https://packagist.org/packages/a2zwebltd/laravel-affiliate)
[![Downloads](https://img.shields.io/packagist/dt/a2zwebltd/laravel-affiliate.svg)](https://packagist.org/packages/a2zwebltd/laravel-affiliate)
![PHP](https://img.shields.io/badge/PHP-%5E8.2-blue)
![Laravel](https://img.shields.io/badge/Laravel-12%20%7C%2013-blue)

## Features

- 🔗 **Cookie + query-param attribution**: First-touch or last-touch, configurable lifetime.
- 📅 **Monthly closure**: Idempotent close-month job snapshots commission rates per row; recalculation safe.
- 🧾 **Immutable commission statements**: Auto-generated, PDF-rendered, stamped with issuing-entity snapshot.
- 💸 **Partner-initiated payouts**: Self-serve payout requests with PDF invoice upload + admin approval flow.
- ⚖️ **Admin adjustments**: Internal-only revenue corrections, applied at the partner's commission rate.
- 🎨 **Drop-in UI**: Blade + Alpine partner dashboard, plus optional Livewire forms.
- 🛠️ **Nova-ready**: Resources, actions, and a single-line `NovaIntegration::resources()` registration.
- 🌍 **i18n**: Every user-facing string is wrapped in `__()` and ready for the host app's translation pipeline.

## Requirements

- PHP 8.2+
- Laravel 12 or 13
- `barryvdh/laravel-dompdf` ^3.1 (PDF statements)
- `rinvex/countries` ^9.1 (country dropdown on the apply form)
- *(optional)* `laravel/nova` ^5 — admin resources & actions
- *(optional)* `livewire/livewire` ^3 / ^4 — reactive partner forms

## Installation

```bash
composer require a2zwebltd/laravel-affiliate
```

The service provider is auto-discovered. Publish migrations and run them:

```bash
php artisan vendor:publish --tag=affiliate-migrations
php artisan migrate
```

Optionally publish config and views:

```bash
php artisan vendor:publish --tag=affiliate-config
php artisan vendor:publish --tag=affiliate-statements-config
php artisan vendor:publish --tag=affiliate-views            # both view namespaces
php artisan vendor:publish --tag=affiliate-views-blade      # affiliate:: only
php artisan vendor:publish --tag=affiliate-views-livewire   # affiliate-livewire:: only
```

### Overriding views

The package registers two view namespaces. Copy only the files you change into the matching host directory:

| Namespace | Package source | Host override path |
|---|---|---|
| `affiliate::` | `resources/views/blade/` | `resources/views/vendor/affiliate/` |
| `affiliate-livewire::` | `resources/views/livewire/` | `resources/views/vendor/affiliate-livewire/` |

So `affiliate::partner.dashboard` is overridden by `resources/views/vendor/affiliate/partner/dashboard.blade.php`, and `affiliate-livewire::payout-request-form` by `resources/views/vendor/affiliate-livewire/payout-request-form.blade.php`. Before 1.3.0 the `affiliate-views` tag published into `vendor/affiliate/blade/` and `vendor/affiliate/livewire/`, where Laravel never looks. Move files from there into the paths above.

The partner pages `@extends(config('affiliate.layout'))`, fill `@section('content')` and `@section('title')`, push inline scripts to `@stack('scripts')`, and use Alpine.js (`x-data`). Your layout must yield `content`, render the `scripts` stack and load Alpine. The views use Tailwind utility classes, so add the package views to your Tailwind sources (v4: `@source "../../vendor/a2zwebltd/laravel-affiliate/resources/views";`, v3: add the path to `content`).

## Quick Start

### 1. Implement `RevenueResolver` in your app

The package never queries your billing system directly. You implement a thin adapter with two methods, both returning **minor units (cents)**:

```php
namespace App\Affiliate;

use A2ZWeb\Affiliate\Contracts\RevenueResolver;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

class InvoiceRevenueResolver implements RevenueResolver
{
    // Realised revenue for a fully closed past month. Called by affiliate:close-month.
    public function revenueForUserInMonth(int $userId, int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();

        return $this->paidCents($userId, $start, $start->copy()->endOfMonth());
    }

    // Month-to-date revenue, shown as the in-progress estimate on the partner dashboard.
    // Return 0 if you have no live data source.
    public function currentMonthRunningRevenueCents(int $userId): int
    {
        return $this->paidCents($userId, Carbon::now()->startOfMonth(), Carbon::now());
    }

    private function paidCents(int $userId, Carbon $from, Carbon $to): int
    {
        return (int) Invoice::query()
            ->where('user_id', $userId)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_cents');
    }
}
```

Bind it either as a class-string in `config/affiliate.php` (keeps the config cacheable):

```php
'resolvers' => [
    'revenue' => \App\Affiliate\InvoiceRevenueResolver::class,
    'referred_user_info' => null, // optional, see ReferredUserInfoResolver
],
```

or in a service provider's `register()`:

```php
$this->app->bind(\A2ZWeb\Affiliate\Contracts\RevenueResolver::class, \App\Affiliate\InvoiceRevenueResolver::class);
```

Without a binding the package falls back to `NullRevenueResolver`, which always returns 0, so no commissions are ever earned.

### 2. Add the affiliate concern to your User model

```php
use A2ZWeb\Affiliate\Concerns\HasAffiliateProgram;

class User extends Authenticatable
{
    use HasAffiliateProgram;
}
```

This adds `affiliatePartner()`, `affiliateCode()`, `affiliateLink()`, `affiliateReferralsAsPartner()` and `affiliateApplyDefaults()` (override it to pre-fill the apply form from your profile fields). `affiliateCode()` and `affiliateLink()` create a share-only partner row the first time they are called for a user, so avoid calling them in loops over many users.

### 3. Attribution is automatic

- The package pushes `CaptureAffiliateReferral` onto the global middleware stack. On any `GET` request carrying `?aff=CODE` (the `affiliate.query_param`) for a pending or approved partner, it sets the attribution cookie (`affiliate.cookie_name`, `affiliate.cookie_ttl_days`). Set `capture_middleware_enabled` to `false` in the published config to turn it off.
- The package listens for `Illuminate\Auth\Events\Registered` and calls `ReferralAttributor::attributeNewUser()`, which writes the `affiliate_referrals` row.

Laravel's starter kits fire `Registered` on signup. If your signup flow is custom (social login, invitations, API signup), fire it yourself:

```php
event(new \Illuminate\Auth\Events\Registered($user));
```

There is no need to call `attributeNewUser()` yourself.

### 4. Schedule monthly closure

In `routes/console.php` (or `app/Console/Kernel.php`):

```php
Schedule::command('affiliate:close-month')
    ->monthlyOn(1, '02:00')
    ->onOneServer();
```

That's it — once a partner is approved and you ship referral codes, the engine will close commissions on the 1st of each month.

## Usage Examples

### Manual referral attachment (Nova action)

When a referral was missed (cookie cleared, signup race, manual import), use the **Attach referral** action on the AffiliatePartner Nova resource:

1. Open the partner detail page in Nova.
2. Run `Attach referral` — provide an email *or* user ID, optional `attributed_at` (for backdating), and tick "Recalculate" to trigger the monthly closer for past months.
3. The action enforces the `referred_user_id` UNIQUE constraint and config-driven self-referral / first-touch rules.

Programmatic equivalent:

```php
use A2ZWeb\Affiliate\Services\ReferralAttributor;
use Illuminate\Support\Carbon;

$referral = app(ReferralAttributor::class)->manuallyAttach(
    $partner,
    $referredUser,
    Carbon::parse('2026-02-15'),
);
```

### Recalculate a single partner

```bash
php artisan affiliate:recalc-partner 1234
```

Re-runs `MonthlyCloser` for partner user `1234` — picks up new referrals, adjustments, or rate overrides without touching other partners.

### Issue a commission statement

Statements are generated when an admin marks a payout request as paid (or via the `Generate statement for period` Nova action). Once issued they are **immutable** — corrections happen via `AffiliateAdjustment` rows, never by editing the statement.

```php
use A2ZWeb\Affiliate\Services\CommissionStatementGenerator;

$statement = app(CommissionStatementGenerator::class)->generateForPartner(
    $partner,
    Carbon::parse('2026-02-01')->startOfMonth(),
    Carbon::parse('2026-02-01')->endOfMonth(),
);
```

## Nova Integration

If `laravel/nova` is installed, register the resources in your `NovaServiceProvider`:

```php
use A2ZWeb\Affiliate\Nova\NovaIntegration;

protected function resources(): void
{
    Nova::resources(NovaIntegration::resources());
}

protected function gates(): void
{
    // ...your existing setup...
    Nova::mainMenu(fn (Request $request) => [
        // ...
        NovaIntegration::menuSection(),
    ]);
}
```

Resources registered:
- `AffiliatePartner` — applications, status, payout details, audit trail.
- `AffiliateCommission` — read-only monthly rows.
- `AffiliateReferral` — read-only attribution log.
- `AffiliatePayoutRequest` — partner-initiated payouts with approval/reject/mark-paid actions.
- `AffiliateCommissionStatement` (+ `AffiliateCommissionStatementLine`) — issued statements with PDF download.
- `AffiliateAdjustment` — admin-only revenue corrections.

Bundled actions: `Approve`, `Reject`, `Suspend`, `Recalculate commissions`, `Generate statement for period`, `Attach referral`, `Approve payout`, `Reject payout`, `Mark as paid`, `Issue statement`, `Mark statement paid`, `Cancel statement`.

## Configuration

All keys in `config/affiliate.php` can be overridden by environment variables. A few highlights:

| Env | Default | Description |
|---|---|---|
| `AFFILIATE_REVENUE_SHARE_BP` | `3000` | Default commission rate in basis points (3000 = 30%). |
| `AFFILIATE_MIN_REFERRED_USERS` | `2` | Number of referred signups (paying or not) required before a user can apply. |
| `AFFILIATE_MIN_PAYOUT_CENTS` | `5000` | Minimum balance required to request a payout. |
| `AFFILIATE_ATTRIBUTION` | `first_touch` | `first_touch` or `last_touch`. |
| `AFFILIATE_WINDOW_MODE` | `lifetime` | `lifetime` or `windowed`. |
| `AFFILIATE_WINDOW_MONTHS` | `12` | When `windowed`, how many months a referral keeps generating commissions. |
| `AFFILIATE_COOKIE_TTL_DAYS` | `60` | Attribution cookie lifetime. |
| `AFFILIATE_ADMIN_EMAIL` | — | Address that receives admin notifications. |
| `AFFILIATE_LAYOUT` | `layouts.app` | Blade layout used by partner pages. |
| `AFFILIATE_DASHBOARD_TAGLINE` | null | The line under the partner dashboard heading. Null keeps the bundled sentence. Goes through `__()`. |
| `AFFILIATE_SHARE_PRODUCT_NAME` | `app.name` | The product named in the share-button texts (`:product`). |
| `AFFILIATE_PARTNER_STATEMENT_VIEW` | `affiliate::partner-statement` | View for the partner-facing statement page. The bundled one extends `AFFILIATE_LAYOUT`. |
| `AFFILIATE_ROUTES_MIDDLEWARE` | `web,auth` | Middleware on the partner route group (comma-separated). |
| `AFFILIATE_NOVA_USER_RESOURCE` | `App\Nova\User` | Class used for `BelongsTo` user fields. |

### Statement issuing entity

`config/affiliate_statements.php` holds the legal entity printed on every commission statement. It is snapshotted onto each statement when the draft is generated and never changes afterwards, so set it before the first payout:

```dotenv
AFFILIATE_STATEMENTS_ENTITY_CODE=uk_ltd          # required
AFFILIATE_STATEMENTS_LEGAL_NAME="Your Company Ltd" # required
AFFILIATE_STATEMENTS_COMPANY_NUMBER=
AFFILIATE_STATEMENTS_ADDRESS=
AFFILIATE_STATEMENTS_COUNTRY=
AFFILIATE_STATEMENTS_TAX_STATUS_NOTE=
AFFILIATE_STATEMENTS_PREFIX=ACS
AFFILIATE_STATEMENTS_DISK=local                  # where PDFs are written
AFFILIATE_STATEMENTS_QUEUE=default               # queue for GenerateCommissionStatementPdf
```

Generating a statement, issuing one, or marking a payout paid throws `A2ZWeb\Affiliate\Exceptions\IssuingEntityNotConfigured` (and logs an error) while the code or legal name is empty, or while any field still holds the "Acme Ltd." placeholder that versions before 1.3.0 shipped as defaults. If you published `affiliate_statements.php` from an older version, replace its placeholder defaults or delete it.

Per-partner rate overrides live on `affiliate_partners.revenue_share_bp` and take precedence over the global default for *future* commissions; historical rows keep their snapshot rate.

## Architecture

### Data model

```
users ──< affiliate_partners
  │            │
  │            ├──< affiliate_referrals ──> users (referred)
  │            ├──< affiliate_commissions   (one per partner+referral+month)
  │            ├──< affiliate_adjustments   (admin-only revenue correction rows)
  │            └──< affiliate_payout_requests
  │                     │
  │                     └──< affiliate_commission_statements
  │                                 │
  │                                 └──< affiliate_commission_statement_lines
  └──< affiliate_terms_acceptances    (versioned ToS audit log)
```

### Lifecycle

1. **Visit** — the global `CaptureAffiliateReferral` middleware reads `?aff=CODE` on GET requests and sets a cookie.
2. **Signup** — the package's `Registered` listener calls `ReferralAttributor::attributeNewUser()`, which writes an `affiliate_referrals` row (subject to UNIQUE on `referred_user_id`).
3. **Application** — qualifying user submits the apply form; `AffiliatePartner` row is created with `status=pending`.
4. **Decision** — admin approves/rejects via Nova; partner email is dispatched.
5. **Monthly close** — `affiliate:close-month` iterates approved partners, sums revenue from `RevenueResolver`, computes commission rows.
6. **Payout request** — partner self-serves; admin approves; `MarkPayoutRequestPaid` action runs the `PayoutCompletionWorkflow` which issues an immutable PDF statement.

### Key services

| Service | Responsibility |
|---|---|
| `ReferralAttributor` | Cookie/code attribution; `manuallyAttach()` for admin tools. |
| `MonthlyCloser` | Idempotent close-month + per-partner recalc. |
| `CommissionCalculator` | Per-partner-month → commission cents. |
| `CommissionStatementGenerator` / `CommissionStatementIssuer` | Draft → issue lifecycle. |
| `PayoutRequestService` / `PayoutCompletionWorkflow` | Approve/reject/pay flows. |
| `EligibilityChecker` | "Can this user apply?" gate. |
| `PartnerStatistics` | Cached KPI feed for the partner dashboard. |

## Routes

By default the package mounts a `dashboard/affiliate` route group with `web,auth` middleware:

| Route | Name | Purpose |
|---|---|---|
| `GET /` | `affiliate.dashboard` | Partner dashboard (state-aware: no-partner / pending / approved / rejected / suspended). |
| `GET /apply` | `affiliate.apply.show` | Apply form. |
| `POST /apply` | `affiliate.apply.store` | Submit application. |
| `PATCH /payout-details` | `affiliate.payout-details.update` | Edit payout details after approval. |
| `POST /payouts` | `affiliate.payouts.store` | Request payout. |
| `DELETE /payouts/{payoutRequest}` | `affiliate.payouts.cancel` | Cancel pending payout request. |
| `GET /statements/{statement}` | `affiliate.statements.show` | Partner-facing statement detail. |
| `GET /statements/{statement}/download` | `affiliate.statements.download` | PDF download (statement owner only). |

Disable the default routes by setting `affiliate.routes.enabled = false` and registering your own.

## AI agents (Laravel Boost)

The package ships [Laravel Boost](https://github.com/laravel/boost) resources: an always-loaded guideline (`resources/boost/guidelines/core.blade.php`) and an on-demand `affiliate-integration` skill covering resolvers, attribution, views, statements, Nova, commands and testing. Boost 2 or newer is required. In the host app:

```bash
composer require laravel/boost --dev
php artisan boost:install          # first time
php artisan boost:update --discover # already using Boost
```

Select `a2zwebltd/laravel-affiliate` when Boost lists the packages it found.

## Localization

Every user-facing string passes through `__()` — including Nova labels, notification subjects, Livewire flash messages, and the PDF statement template. The package ships no language files; the host app is expected to extract keys via its own pipeline (e.g. a `translate:extract` artisan command). Run your extractor over the package's `src/` and `resources/views/` paths to harvest all keys.

## Testing

```bash
composer test
```

The test suite uses Pest + Orchestra Testbench and exercises the full closure lifecycle with stub revenue resolvers.

## Contributing

Issues and PRs are welcome. Please open a discussion before tackling large changes — the engine has a few non-obvious invariants (idempotent closure, immutable statements) that are easy to break.

## Security

Found a vulnerability? Please email **contact@a2zweb.co** rather than opening a public issue.

## Credits

- [A2Z Web Ltd](https://a2zweb.co)
- [Dawid Makowski](https://github.com/makowskid) (maintainer)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). See the `license` field in `composer.json`.

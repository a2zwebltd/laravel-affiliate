# Laravel Affiliate

Generic affiliate / revenue-share engine for Laravel — opt-in workflow, monthly commission closure, admin adjustments, partner-initiated payout requests, immutable commission statements, Blade+Alpine and Livewire dashboards.

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
php artisan vendor:publish --tag=affiliate-views
```

## Quick Start

### 1. Implement `RevenueResolver` in your app

The package never queries your billing system directly — you implement a thin adapter:

```php
use A2ZWeb\Affiliate\Contracts\RevenueResolver;

class StripeRevenueResolver implements RevenueResolver
{
    public function revenueCentsForUserMonth(int $userId, int $year, int $month): int
    {
        // Sum realised revenue (paid invoices) for $userId in (year, month).
        return (int) Invoice::query()
            ->where('user_id', $userId)
            ->whereYear('paid_at', $year)
            ->whereMonth('paid_at', $month)
            ->sum('amount_cents');
    }
}
```

Bind it in `config/affiliate.php`:

```php
'resolvers' => [
    'revenue' => \App\Affiliate\StripeRevenueResolver::class,
    'referred_user_info' => null, // optional
],
```

### 2. Add the affiliate concern to your User model

```php
use A2ZWeb\Affiliate\Concerns\HasAffiliateProgram;

class User extends Authenticatable
{
    use HasAffiliateProgram;
}
```

This adds `affiliatePartner()` and `affiliateLink()`.

### 3. Capture attribution on signup

In your registration controller (or a `Registered` listener):

```php
use A2ZWeb\Affiliate\Services\ReferralAttributor;

app(ReferralAttributor::class)->attributeNewUser($user, $request);
```

The cookie is set automatically by the `affiliate.capture` middleware whenever a visitor lands on a page with `?aff=CODE`.

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

Statements are generated when an admin marks a payout request as approved (or via the `Generate statement for period` Nova action). Once issued they are **immutable** — corrections happen via `AffiliateAdjustment` rows, never by editing the statement.

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
| `AFFILIATE_MIN_REFERRED_USERS` | `2` | Number of paying referrals required before a user can apply. |
| `AFFILIATE_MIN_PAYOUT_CENTS` | `5000` | Minimum balance required to request a payout. |
| `AFFILIATE_ATTRIBUTION` | `first_touch` | `first_touch` or `last_touch`. |
| `AFFILIATE_WINDOW_MODE` | `lifetime` | `lifetime` or `windowed`. |
| `AFFILIATE_WINDOW_MONTHS` | `12` | When `windowed`, how many months a referral keeps generating commissions. |
| `AFFILIATE_COOKIE_TTL_DAYS` | `60` | Attribution cookie lifetime. |
| `AFFILIATE_ADMIN_EMAIL` | — | Address that receives admin notifications. |
| `AFFILIATE_LAYOUT` | `layouts.app` | Blade layout used by partner pages. |
| `AFFILIATE_NOVA_USER_RESOURCE` | `App\Nova\User` | Class used for `BelongsTo` user fields. |

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

1. **Visit** — `affiliate.capture` middleware reads `?aff=CODE` and sets a cookie.
2. **Signup** — host app calls `ReferralAttributor::attributeNewUser()` which writes an `affiliate_referrals` row (subject to UNIQUE on `referred_user_id`).
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
| `DELETE /payouts/{request}` | `affiliate.payouts.cancel` | Cancel pending payout request. |
| `GET /statements/{statement}` | `affiliate.statements.show` | Partner-facing statement detail. |
| `GET /statements/{statement}/download` | `affiliate.statements.download` | Signed PDF download. |

Disable the default routes by setting `affiliate.routes.enabled = false` and registering your own.

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

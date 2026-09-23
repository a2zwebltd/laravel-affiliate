# Changelog

All notable changes to `a2zwebltd/laravel-affiliate` will be documented in this file.

## [1.3.0] - 2026-09-23

### Fixed
- `vendor:publish --tag=affiliate-views` now publishes `resources/views/blade` to `resources/views/vendor/affiliate` and `resources/views/livewire` to `resources/views/vendor/affiliate-livewire`, the directories Laravel reads overrides from. Previously it copied into `vendor/affiliate/{blade,livewire}`, so published overrides were silently ignored. Move any files from those old folders.
- The default `affiliate.partner_statement_view` (`affiliate::partner-statement`) now ships with the package, so the partner statement page works without a host view. An empty configured value falls back to it.
- Statements can no longer be issued in the name of the placeholder "Acme Ltd." entity. The `affiliate_statements.issuing_entity` defaults are now `null`. Generating, issuing or completing a payout throws `IssuingEntityNotConfigured` and logs an error while `code` or `legal_name` is empty, or while any field still holds a pre-1.3.0 placeholder, which also covers a previously published config file. Payout completion checks before marking the request paid.
- README: the resolver example now uses the real `RevenueResolver` methods, attribution is documented as automatic (global middleware plus `Registered` listener, no `affiliate.capture` alias), and the referral threshold counts all referrals.

### Added
- `affiliate-views-blade` and `affiliate-views-livewire` publish tags.
- `A2ZWeb\Affiliate\Support\IssuingEntity` validator and `A2ZWeb\Affiliate\Exceptions\IssuingEntityNotConfigured`.
- Laravel Boost resources: the `resources/boost/guidelines/core.blade.php` guideline and the `affiliate-integration` skill.

### Upgrade notes
- Set `AFFILIATE_STATEMENTS_ENTITY_CODE` and `AFFILIATE_STATEMENTS_LEGAL_NAME` (plus address, country, company number and tax note as needed) before the next payout. If you published `config/affiliate_statements.php` from an older version, remove its Acme defaults or delete the file.
- Move published views from `resources/views/vendor/affiliate/blade/*` to `resources/views/vendor/affiliate/*`, and from `resources/views/vendor/affiliate/livewire/*` to `resources/views/vendor/affiliate-livewire/*`. They will then take effect, so delete any that are unmodified copies.

## [1.2.0] - 2026-09-01

### Added
- Nova `AffiliatePartner` resource now shows referral count and referred revenue (attributed spend from `source_amount_cents`, excluding reversed commissions) on index and detail, shows `Total Earned (cents)` on the index, and lists the partner's referrals via a `HasMany` panel.
- Nova `AffiliateReferral` resource shows per-referral attributed revenue.
- `nova.laravel.com` composer repository declared for the `laravel/nova` dev dependency.

## [1.0.0] - 2026-04-30

### Added
- Engine: `AffiliatePartner`, `AffiliateReferral`, `AffiliateCommission`, `AffiliateCommissionStatement`, `AffiliateCommissionStatementLine`, `AffiliateAdjustment`, `AffiliatePayoutRequest`, `AffiliateTermsAcceptance` models with 13 timestamped migrations.
- Opt-in partner workflow: application form, terms acceptance, eligibility check (`EligibilityChecker`), affiliate code generation (`AffiliateCodeGenerator`).
- Referral attribution via `CaptureAffiliateReferral` middleware and the `HasAffiliateProgram` model concern, with cookie-based attribution and pluggable resolvers.
- Pluggable contracts: `RevenueResolver` (your billing source of truth) and `ReferredUserInfoResolver` (display data for the partner dashboard), with null defaults shipped.
- Monthly commission closure (`CloseMonthCommand`, `MonthlyCloser`, `CommissionCalculator`) producing immutable statements that snapshot partner billing and rates at issue time.
- Commission statement generation (`CommissionStatementGenerator`, `CommissionStatementIssuer`, `CommissionStatementPaymentRecorder`, `StatementNumberGenerator`) with PDF export via `barryvdh/laravel-dompdf`.
- Admin adjustments via `AffiliateAdjustment`, including `commission_rate_bp` overrides per adjustment.
- Partner-initiated payout requests with admin completion workflow (`PayoutRequestService`, `PayoutCompletionWorkflow`).
- Events: `ApplicationSubmitted`, `ApplicationApproved`, `ApplicationRejected`, `MonthClosed`, `StatementIssued`, `StatementCancelled`, `StatementPaid`, `PayoutRequested`, `PayoutApproved`, `PayoutPaid` — with admin and partner notifications.
- Blade + Alpine partner dashboard (routes opt-in via config) and supporting views: partner detail, statements list, payout request flow, emails.
- Optional Nova 5 layer: resources, actions, admin menu — registered automatically when `laravel/nova` is installed.
- Optional Livewire layer (auto-registered only when `livewire/livewire` is installed): `PartnerDashboard`, `ApplicationForm`, `PayoutDetailsForm`, `PayoutRequestForm`.
- Artisan commands: `affiliate:close-month`, `affiliate:recalc-partner`, `affiliate:migrate-from-jijunair` (legacy migration helper).
- Auto-discovered service provider with publishable config, migrations, views, and translations.
- Two config files: `affiliate.php` (engine, attribution, routes, defaults) and `affiliate_statements.php` (issuing entity snapshot, PDF settings).
- Translation scaffolding under `resources/lang/`.

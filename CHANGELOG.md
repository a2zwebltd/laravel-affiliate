# Changelog

All notable changes to `a2zwebltd/laravel-affiliate` will be documented in this file.

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

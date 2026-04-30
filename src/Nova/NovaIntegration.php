<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova;

use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;

/**
 * Static helpers for wiring the package's Nova resources into a host app.
 *
 * Usage in NovaServiceProvider:
 *   Nova::resources(NovaIntegration::resources());
 *   Nova::mainMenu(fn () => [..., NovaIntegration::menuSection(), ...]);
 */
class NovaIntegration
{
    /** @return array<int, class-string> */
    public static function resources(): array
    {
        return [
            AffiliatePartner::class,
            AffiliateReferral::class,
            AffiliateCommission::class,
            AffiliateAdjustment::class,
            AffiliatePayoutRequest::class,
            AffiliateCommissionStatement::class,
            AffiliateCommissionStatementLine::class,
        ];
    }

    public static function menuSection(): MenuSection
    {
        $label = (string) config('affiliate.nova.menu_label', 'Affiliate Program');
        $icon = (string) config('affiliate.nova.menu_icon', 'currency-dollar');

        return MenuSection::make(__($label), [
            MenuItem::resource(AffiliatePartner::class)->name(__('Partners')),
            MenuItem::resource(AffiliateReferral::class)->name(__('Referrals')),
            MenuItem::resource(AffiliateCommission::class)->name(__('Commissions')),
            MenuItem::resource(AffiliateAdjustment::class)->name(__('Adjustments')),
            MenuItem::resource(AffiliatePayoutRequest::class)->name(__('Payout Requests')),
            MenuItem::resource(AffiliateCommissionStatement::class)->name(__('Commission Statements')),
            MenuItem::resource(AffiliateCommissionStatementLine::class)->name(__('Statement Lines')),
        ])->icon($icon)->collapsable();
    }
}

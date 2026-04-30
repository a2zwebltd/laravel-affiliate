<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova;

use A2ZWeb\Affiliate\Models\AffiliateCommission as AffiliateCommissionModel;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

class AffiliateCommission extends Resource
{
    /**
     * @var class-string<AffiliateCommissionModel>
     */
    public static string $model = AffiliateCommissionModel::class;

    public static $title = 'id';

    public static function label(): string
    {
        return __('Affiliate Commissions');
    }

    public function fields(NovaRequest $request): array
    {
        $userResource = (string) config('affiliate.nova.user_resource');

        return [
            ID::make()->sortable(),
            BelongsTo::make(__('Partner'), 'partner', $userResource)->sortable(),
            BelongsTo::make(__('Referred user'), 'referredUser', $userResource)->onlyOnDetail(),
            Number::make(__('Year'), 'period_year')->sortable(),
            Number::make(__('Month'), 'period_month')->sortable(),
            Number::make(__('Source $'), function (AffiliateCommissionModel $c): string {
                return number_format($c->source_amount_cents / 100, 2);
            }),
            Number::make(__('Commission $'), function (AffiliateCommissionModel $c): string {
                return number_format($c->commission_amount_cents / 100, 2);
            }),
            Number::make(__('Rate (bp)'), 'commission_rate_bp')->onlyOnDetail(),
            Badge::make(__('Status'))->map([
                'closed' => 'info',
                'requested' => 'warning',
                'paid' => 'success',
                'reversed' => 'danger',
            ])->sortable(),
            DateTime::make(__('Closed at'))->onlyOnDetail(),
            DateTime::make(__('Paid at'))->onlyOnDetail(),
            BelongsTo::make(__('Payout request'), 'payoutRequest', AffiliatePayoutRequest::class)->nullable()->onlyOnDetail(),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }
}

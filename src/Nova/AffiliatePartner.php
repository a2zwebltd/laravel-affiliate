<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova;

use A2ZWeb\Affiliate\Models\AffiliatePartner as AffiliatePartnerModel;
use A2ZWeb\Affiliate\Nova\Actions\ApproveAffiliatePartner;
use A2ZWeb\Affiliate\Nova\Actions\AttachReferralToPartner;
use A2ZWeb\Affiliate\Nova\Actions\GenerateStatementForPartner;
use A2ZWeb\Affiliate\Nova\Actions\RecalculateAffiliatePartner;
use A2ZWeb\Affiliate\Nova\Actions\RejectAffiliatePartner;
use A2ZWeb\Affiliate\Nova\Actions\SuspendAffiliatePartner;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Laravel\Nova\Resource;

class AffiliatePartner extends Resource
{
    /**
     * @var class-string<AffiliatePartnerModel>
     */
    public static string $model = AffiliatePartnerModel::class;

    public static $title = 'code';

    public static $search = ['code', 'paypal_email', 'bank_iban', 'company_name'];

    public static function label(): string
    {
        return __('Affiliate Partners');
    }

    public function fields(NovaRequest $request): array
    {
        $userResource = (string) config('affiliate.nova.user_resource');

        return [
            ID::make()->sortable(),

            BelongsTo::make(__('User'), 'user', $userResource)->searchable(),

            Text::make(__('Code'))->sortable()->copyable(),

            Badge::make(__('Status'))->map([
                'pending' => 'warning',
                'approved' => 'success',
                'rejected' => 'danger',
                'suspended' => 'info',
            ])->sortable(),

            Date::make(__('Program Joined At'))->help(__('Statistics floor — commissions only count from this month onwards.'))->nullable(),

            Number::make(__('Revenue share override (bp)'), 'revenue_share_bp')
                ->help(__('Leave empty to use global default (:bp bp = :pct%). Per-partner override applies to commissions for closed months going forward; historical rows keep their snapshot rate.', [
                    'bp' => config('affiliate.revenue_share_bp'),
                    'pct' => config('affiliate.revenue_share_bp') / 100,
                ]))
                ->nullable()
                ->min(0)
                ->max(10000)
                ->hideFromIndex(),

            Number::make(__('Total Earned (cents)'), function (AffiliatePartnerModel $partner): int {
                return (int) $partner->commissions()
                    ->whereIn('status', ['closed', 'requested', 'paid'])
                    ->sum('commission_amount_cents');
            })->onlyOnDetail(),

            Number::make(__('Pending Payout (cents)'), function (AffiliatePartnerModel $partner): int {
                return (int) $partner->commissions()->where('status', 'closed')->sum('commission_amount_cents');
            })->onlyOnDetail(),

            new Panel(__('Payout details'), [
                Select::make(__('Payout method'), 'payout_method')->options([
                    'paypal' => __('PayPal'),
                    'bank_transfer' => __('Bank transfer'),
                ])->displayUsingLabels(),
                Text::make(__('PayPal email'), 'paypal_email')->hideFromIndex(),
                Text::make(__('Bank account holder'), 'bank_account_holder')->hideFromIndex(),
                Text::make(__('IBAN'), 'bank_iban')->hideFromIndex(),
                Text::make(__('SWIFT'), 'bank_swift')->hideFromIndex(),
                Text::make(__('Bank address'), 'bank_address')->hideFromIndex(),
                Boolean::make(__('Is company'), 'is_company')->hideFromIndex(),
                Text::make(__('Company name'), 'company_name')->hideFromIndex(),
                Text::make(__('Tax ID'), 'tax_id')->hideFromIndex(),
            ]),

            new Panel(__('Application audit'), [
                DateTime::make(__('Applied at'))->onlyOnDetail(),
                DateTime::make(__('Decided at'))->onlyOnDetail(),
                BelongsTo::make(__('Decided by'), 'decidedBy', $userResource)->onlyOnDetail()->nullable(),
                Textarea::make(__('Rejection reason'))->onlyOnDetail()->nullable(),
                Text::make(__('General terms version'), 'accepted_general_terms_version')->onlyOnDetail(),
                Text::make(__('Affiliate terms version'), 'accepted_affiliate_terms_version')->onlyOnDetail(),
                DateTime::make(__('Accepted terms at'))->onlyOnDetail(),
                Text::make(__('Accepted terms IP'), 'accepted_terms_ip')->onlyOnDetail(),
            ]),

            HasMany::make(__('Commissions'), 'commissions', AffiliateCommission::class),
            HasMany::make(__('Adjustments'), 'adjustments', AffiliateAdjustment::class),
            HasMany::make(__('Payout requests'), 'payoutRequests', AffiliatePayoutRequest::class),
        ];
    }

    public function actions(NovaRequest $request): array
    {
        return [
            new ApproveAffiliatePartner,
            new RejectAffiliatePartner,
            new SuspendAffiliatePartner,
            new RecalculateAffiliatePartner,
            new GenerateStatementForPartner,
            new AttachReferralToPartner,
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatementLine as AffiliateCommissionStatementLineModel;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

class AffiliateCommissionStatementLine extends Resource
{
    /**
     * @var class-string<AffiliateCommissionStatementLineModel>
     */
    public static string $model = AffiliateCommissionStatementLineModel::class;

    public static $title = 'id';

    public static function label(): string
    {
        return __('Statement Lines');
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(__('Statement'), 'statement', AffiliateCommissionStatement::class),
            Date::make(__('Transaction date')),
            Text::make(__('Customer reference'))->copyable(),
            Text::make(__('Subscription / invoice ref'), 'subscription_or_invoice_reference'),
            Currency::make(__('Gross'), 'gross_amount'),
            Number::make(__('Rate %'), fn () => number_format(((float) $this->commission_rate) * 100, 2)),
            Currency::make(__('Line commission'), 'line_commission'),
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

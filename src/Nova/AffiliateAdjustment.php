<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova;

use A2ZWeb\Affiliate\Models\AffiliateAdjustment as AffiliateAdjustmentModel;
use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest as AffiliatePayoutRequestModel;
use Illuminate\Support\Carbon;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

class AffiliateAdjustment extends Resource
{
    /**
     * @var class-string<AffiliateAdjustmentModel>
     */
    public static string $model = AffiliateAdjustmentModel::class;

    public static $title = 'id';

    public static function label(): string
    {
        return __('Affiliate Adjustments');
    }

    public function fields(NovaRequest $request): array
    {
        $userResource = (string) config('affiliate.nova.user_resource');

        return [
            ID::make()->sortable(),

            BelongsTo::make(__('Partner'), 'partner', $userResource)
                ->searchable()
                ->withoutTrashed()
                ->default(function (NovaRequest $request): ?int {
                    if ($request->viaResource === 'affiliate-payout-requests' && $request->viaResourceId) {
                        return AffiliatePayoutRequestModel::query()
                            ->whereKey($request->viaResourceId)
                            ->value('partner_user_id');
                    }

                    return null;
                })
                ->help(__('Adjustments are NEVER visible to partners — internal admin only.'))
                ->required(),

            Date::make(__('Period'), 'period')
                ->resolveUsing(function ($value, $resource): ?string {
                    if (! $resource?->period_year || ! $resource?->period_month) {
                        return null;
                    }

                    return Carbon::create((int) $resource->period_year, (int) $resource->period_month, 1)->toDateString();
                })
                ->fillUsing(function (NovaRequest $request, $model, string $attribute, string $requestAttribute): void {
                    if (! $request->filled($requestAttribute)) {
                        return;
                    }
                    $date = Carbon::parse((string) $request->input($requestAttribute));
                    $model->period_year = (int) $date->year;
                    $model->period_month = (int) $date->month;
                })
                ->rules('required', 'date', 'before:'.now()->startOfMonth()->toDateString())
                ->help(__('Pick any day in the target month — only year/month are stored. Must be a closed past month.')),

            Select::make(__('Type'))->options([
                'addition' => __('Addition (+)'),
                'subtraction' => __('Subtraction (−)'),
            ])->displayUsingLabels()->required(),

            Number::make(__('Amount (cents)'), 'amount_cents')
                ->required()
                ->help(__('Always positive — sign comes from `type`.')),

            Select::make(__('Currency'))
                ->options(collect(config('affiliate.currencies', ['usd']))
                    ->mapWithKeys(fn (string $code): array => [$code => strtoupper($code)])
                    ->all())
                ->displayUsingLabels()
                ->default((string) config('affiliate.currency', 'usd'))
                ->required(),

            Textarea::make(__('Reason'))->required()->help(__('Internal note. Hidden from partner.')),

            Badge::make(__('Status'))->map([
                'closed' => 'info',
                'requested' => 'warning',
                'paid' => 'success',
            ])->onlyOnIndex(),

            BelongsTo::make(__('Payout request'), 'payoutRequest', AffiliatePayoutRequest::class)
                ->nullable()->onlyOnDetail(),

            Hidden::make('admin_user_id')->default(fn (NovaRequest $req) => $req->user()?->getKey()),
        ];
    }
}

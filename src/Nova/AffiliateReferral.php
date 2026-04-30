<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova;

use A2ZWeb\Affiliate\Models\AffiliateReferral as AffiliateReferralModel;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

class AffiliateReferral extends Resource
{
    /**
     * @var class-string<AffiliateReferralModel>
     */
    public static string $model = AffiliateReferralModel::class;

    public static $title = 'code_used';

    public static $search = ['code_used'];

    public static function label(): string
    {
        return __('Affiliate Referrals');
    }

    public function fields(NovaRequest $request): array
    {
        $userResource = (string) config('affiliate.nova.user_resource');

        return [
            ID::make()->sortable(),
            BelongsTo::make(__('Partner'), 'partner', $userResource),
            BelongsTo::make(__('Referred user'), 'referredUser', $userResource),
            Text::make(__('Code used'))->sortable(),
            Text::make(__('IP'))->onlyOnDetail(),
            Text::make(__('User agent'))->onlyOnDetail(),
            DateTime::make(__('Attributed at'))->sortable(),
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

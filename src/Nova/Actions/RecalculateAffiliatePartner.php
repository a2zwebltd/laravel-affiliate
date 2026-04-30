<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova\Actions;

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Services\MonthlyCloser;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class RecalculateAffiliatePartner extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public function name(): string
    {
        return __('Recalculate commissions');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $closer = app(MonthlyCloser::class);
        $touched = 0;

        $models->each(function (AffiliatePartner $partner) use ($closer, &$touched): void {
            $touched += $closer->recalcPartner((int) $partner->user_id);
        });

        return Action::message(__('Recalculated. Commissions touched: :count.', ['count' => $touched]));
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova\Actions;

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class SuspendAffiliatePartner extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public function name(): string
    {
        return __('Suspend');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $count = $models->each(fn (AffiliatePartner $p) => $p->update(['status' => AffiliatePartner::STATUS_SUSPENDED]))->count();

        return Action::message(__('Suspended :count partner(s).', ['count' => $count]));
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}

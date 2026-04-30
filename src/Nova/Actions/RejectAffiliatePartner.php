<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova\Actions;

use A2ZWeb\Affiliate\Events\ApplicationRejected;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Notifications\ApplicationRejected as ApplicationRejectedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class RejectAffiliatePartner extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public function name(): string
    {
        return __('Reject');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $admin = auth()->user();
        $reason = (string) ($fields->reason ?? '');
        $count = 0;

        $models->each(function (AffiliatePartner $partner) use ($admin, $reason, &$count): void {
            $partner->update([
                'status' => AffiliatePartner::STATUS_REJECTED,
                'decided_at' => Carbon::now(),
                'decided_by_user_id' => $admin?->getKey(),
                'rejection_reason' => $reason,
            ]);

            $partner->user?->notify(new ApplicationRejectedMail($partner));
            event(new ApplicationRejected($partner, $reason));
            $count++;
        });

        return Action::message(__('Rejected :count partner(s).', ['count' => $count]));
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Textarea::make(__('Reason'))->help(__('Visible to the partner in the rejection email.')),
        ];
    }
}

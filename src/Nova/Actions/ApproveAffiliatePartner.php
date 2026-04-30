<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova\Actions;

use A2ZWeb\Affiliate\Events\ApplicationApproved;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Notifications\ApplicationApproved as ApplicationApprovedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class ApproveAffiliatePartner extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public function name(): string
    {
        return __('Approve');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $admin = auth()->user();
        $count = 0;

        $models->each(function (AffiliatePartner $partner) use ($admin, &$count): void {
            if ($partner->status === AffiliatePartner::STATUS_APPROVED) {
                return;
            }

            $now = Carbon::now();
            $partner->update([
                'status' => AffiliatePartner::STATUS_APPROVED,
                'decided_at' => $now,
                'decided_by_user_id' => $admin?->getKey(),
                'program_joined_at' => $partner->program_joined_at ?? $now->toDateString(),
            ]);

            $partner->user?->notify(new ApplicationApprovedMail($partner));
            event(new ApplicationApproved($partner));
            $count++;
        });

        return Action::message(__('Approved :count partner(s).', ['count' => $count]));
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}

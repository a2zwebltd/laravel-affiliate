<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova\Actions;

use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;
use A2ZWeb\Affiliate\Notifications\PayoutRequestRejected;
use A2ZWeb\Affiliate\Services\PayoutRequestService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class RejectPayoutRequest extends Action
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
        $service = app(PayoutRequestService::class);
        $count = 0;

        $models->each(function (AffiliatePayoutRequest $request) use ($admin, $reason, $service, &$count): void {
            try {
                $service->reject($request, $admin, $reason);
                $request->partner?->notify(new PayoutRequestRejected($request));
                $count++;
            } catch (\Throwable) {
            }
        });

        return Action::message(__('Rejected :count payout request(s).', ['count' => $count]));
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Textarea::make(__('Reason'))->required(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova\Actions;

use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;
use A2ZWeb\Affiliate\Notifications\PayoutRequestApproved;
use A2ZWeb\Affiliate\Services\PayoutRequestService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class ApprovePayoutRequest extends Action
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
        $service = app(PayoutRequestService::class);
        $count = 0;
        $errors = [];

        $models->each(function (AffiliatePayoutRequest $request) use ($admin, $service, &$count, &$errors): void {
            try {
                $service->approve($request, $admin);
                $request->partner?->notify(new PayoutRequestApproved($request));
                $count++;
            } catch (\Throwable $e) {
                $errors[] = "#{$request->id}: ".$e->getMessage();
            }
        });

        if ($errors !== []) {
            return Action::danger(__('Approved :count — errors: :errors', ['count' => $count, 'errors' => implode('; ', $errors)]));
        }

        return Action::message(__('Approved :count payout request(s).', ['count' => $count]));
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova\Actions;

use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest;
use A2ZWeb\Affiliate\Notifications\PayoutRequestPaid;
use A2ZWeb\Affiliate\Services\PayoutCompletionWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class MarkPayoutRequestPaid extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public function name(): string
    {
        return __('Mark as paid');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $admin = auth()->user();
        $reference = (string) ($fields->payment_reference ?? '');
        if ($reference === '') {
            return Action::danger(__('Payment reference is required.'));
        }

        $workflow = app(PayoutCompletionWorkflow::class);
        $count = 0;
        $errors = [];

        $models->each(function (AffiliatePayoutRequest $request) use ($admin, $reference, $workflow, &$count, &$errors): void {
            try {
                $workflow->complete($request, $admin, $reference, now());
                $request->partner?->notify(new PayoutRequestPaid($request));
                $count++;
            } catch (\Throwable $e) {
                $errors[] = "#{$request->id}: ".$e->getMessage();
            }
        });

        if ($errors !== []) {
            return Action::danger(__('Paid :count — errors: :errors', ['count' => $count, 'errors' => implode('; ', $errors)]));
        }

        return Action::message(__('Marked :count request(s) as paid.', ['count' => $count]));
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(__('Payment reference'))->required()->help(__('e.g. PayPal transaction ID or bank wire reference.')),
        ];
    }
}

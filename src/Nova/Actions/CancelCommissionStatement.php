<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova\Actions;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use A2ZWeb\Affiliate\Services\CommissionStatementIssuer;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class CancelCommissionStatement extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public function name(): string
    {
        return __('Cancel statement');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $issuer = app(CommissionStatementIssuer::class);
        $reason = (string) ($fields->reason ?? '');
        $count = 0;

        $models->each(function (AffiliateCommissionStatement $s) use ($issuer, $reason, &$count): void {
            try {
                $issuer->cancel($s, $reason);
                $count++;
            } catch (\Throwable) {
            }
        });

        return Action::message(__('Cancelled :count draft statement(s).', ['count' => $count]));
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Textarea::make(__('Reason'))->help(__('Internal note. Saved on the statement.')),
        ];
    }
}

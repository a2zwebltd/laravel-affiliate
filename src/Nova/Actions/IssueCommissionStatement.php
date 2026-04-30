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
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Http\Requests\NovaRequest;

class IssueCommissionStatement extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public function name(): string
    {
        return __('Issue statement');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $issuer = app(CommissionStatementIssuer::class);
        $allowZero = (bool) ($fields->allow_zero ?? false);
        $count = 0;
        $errors = [];

        $models->each(function (AffiliateCommissionStatement $s) use ($issuer, $allowZero, &$count, &$errors): void {
            try {
                $issuer->issue($s, $allowZero);
                $count++;
            } catch (\Throwable $e) {
                $errors[] = '#'.$s->id.': '.$e->getMessage();
            }
        });

        if ($errors !== []) {
            return Action::danger(__('Issued :count — errors: :errors', ['count' => $count, 'errors' => implode('; ', $errors)]));
        }

        return Action::message(__('Issued :count statement(s). PDFs are being generated in the background.', ['count' => $count]));
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Boolean::make(__('Allow zero amount'))
                ->help(__('Issue even if commission amount is 0 (override).'))
                ->default(false),
        ];
    }
}

<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova\Actions;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use A2ZWeb\Affiliate\Services\CommissionStatementPaymentRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class MarkCommissionStatementPaid extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public function name(): string
    {
        return __('Mark statement paid');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $reference = (string) ($fields->payment_reference ?? '');
        $method = (string) ($fields->payment_method ?? 'bank_transfer');
        $date = $fields->payment_date ? Carbon::parse((string) $fields->payment_date) : Carbon::now();

        if ($reference === '') {
            return Action::danger(__('Payment reference is required.'));
        }

        $recorder = app(CommissionStatementPaymentRecorder::class);
        $count = 0;
        $errors = [];

        $models->each(function (AffiliateCommissionStatement $s) use ($recorder, $reference, $date, $method, &$count, &$errors): void {
            try {
                $recorder->markPaid($s, $reference, $date, $method);
                $count++;
            } catch (\Throwable $e) {
                $errors[] = '#'.$s->id.': '.$e->getMessage();
            }
        });

        if ($errors !== []) {
            return Action::danger(__('Paid :count — errors: :errors', ['count' => $count, 'errors' => implode('; ', $errors)]));
        }

        return Action::message(__('Marked :count statement(s) as paid.', ['count' => $count]));
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(__('Payment reference'))->required()->help(__('Bank wire ref, PayPal txn id, etc.')),
            Select::make(__('Payment method'))->options([
                'bank_transfer' => __('Bank transfer'),
                'wise' => __('Wise'),
                'paypal' => __('PayPal'),
                'other' => __('Other'),
            ])->default('bank_transfer')->required(),
            Date::make(__('Payment date'))->default(fn () => now()->toDateString()),
        ];
    }
}

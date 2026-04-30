<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova\Actions;

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Services\CommissionStatementGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Http\Requests\NovaRequest;

class GenerateStatementForPartner extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public function name(): string
    {
        return __('Generate statement for period');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $start = Carbon::parse((string) $fields->period_start)->startOfDay();
        $end = Carbon::parse((string) $fields->period_end)->endOfDay();

        $generator = app(CommissionStatementGenerator::class);
        $count = 0;

        $models->each(function (AffiliatePartner $partner) use ($generator, $start, $end, &$count): void {
            $generator->generateForPartner($partner, $start, $end);
            $count++;
        });

        return Action::message(__('Generated draft statement(s) for :count partner(s). Issue them via the Statements resource to send PDFs.', ['count' => $count]));
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Date::make(__('Period start'))->default(fn () => now()->subMonthNoOverflow()->startOfMonth()->toDateString())->required(),
            Date::make(__('Period end'))->default(fn () => now()->subMonthNoOverflow()->endOfMonth()->toDateString())->required(),
        ];
    }
}

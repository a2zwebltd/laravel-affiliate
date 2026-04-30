<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova\Actions;

use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Services\MonthlyCloser;
use A2ZWeb\Affiliate\Services\ReferralAttributor;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class AttachReferralToPartner extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public function name(): string
    {
        return __('Attach referral');
    }

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        if ($models->count() !== 1) {
            return Action::danger(__('Run this action on exactly one partner at a time.'));
        }

        /** @var AffiliatePartner $partner */
        $partner = $models->first();

        $userClass = (string) config('affiliate.user_model');
        $lookup = trim((string) ($fields->referred_user ?? ''));
        if ($lookup === '') {
            return Action::danger(__('Provide an email or user ID.'));
        }

        $user = is_numeric($lookup)
            ? $userClass::query()->whereKey((int) $lookup)->first()
            : $userClass::query()->where('email', $lookup)->first();

        if (! $user) {
            return Action::danger(__('User ":lookup" not found.', ['lookup' => $lookup]));
        }

        $attributedAt = filled($fields->attributed_at)
            ? Carbon::parse((string) $fields->attributed_at)
            : null;
        $codeUsed = filled($fields->code_used) ? (string) $fields->code_used : null;

        try {
            $referral = app(ReferralAttributor::class)->manuallyAttach(
                $partner,
                $user,
                $attributedAt,
                $codeUsed,
            );
        } catch (\DomainException $e) {
            return Action::danger($e->getMessage());
        }

        $touched = 0;
        if ((bool) ($fields->recalc ?? false)) {
            $touched = app(MonthlyCloser::class)->recalcPartner((int) $partner->user_id);
        }

        return Action::message(__(
            'Attached referral #:id (user #:user). Commissions touched: :touched.',
            [
                'id' => $referral->id,
                'user' => $user->getKey(),
                'touched' => $touched,
            ],
        ));
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(__('Referred user (email or ID)'), 'referred_user')
                ->required()
                ->help(__('Email address or numeric ID of the user to attach as a referral. The user must not already be referred by another partner.')),

            DateTime::make(__('Attributed at'), 'attributed_at')
                ->nullable()
                ->help(__('Leave empty to use the current time. Backdating an attribution makes the referral eligible for commissions in past closed months once you recalculate.')),

            Text::make(__('Code used'), 'code_used')
                ->nullable()
                ->help(__('Code stored on the referral row. Defaults to the partner code if left empty.')),

            Boolean::make(__('Recalculate commissions afterwards'), 'recalc')
                ->default(true)
                ->help(__('Re-runs the monthly closer for this partner so commissions for the new referral are picked up immediately.')),
        ];
    }
}

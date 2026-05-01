<?php

namespace A2ZWeb\Affiliate\Console\Commands;

use A2ZWeb\Affiliate\Models\AffiliateReferral;
use A2ZWeb\Affiliate\Notifications\PartnerEligibleToApply;
use A2ZWeb\Affiliate\Services\EligibilityChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

use function Laravel\Prompts\confirm;

class NotifyEligiblePartnersCommand extends Command
{
    protected $signature = 'affiliate:notify-eligible
        {--dry-run : List recipients without sending mail.}
        {--force : Skip the confirmation prompt.}';

    protected $description = 'One-shot backfill: email every user who is currently eligible to apply for the affiliate program. Designed to run once after upgrading. Re-running will resend emails — guard with --dry-run first.';

    public function handle(EligibilityChecker $eligibility): int
    {
        $userModel = config('affiliate.user_model');
        if (! $userModel || ! class_exists($userModel)) {
            $this->error('affiliate.user_model is not configured.');

            return self::FAILURE;
        }

        $partnerUserIds = AffiliateReferral::query()
            ->select('partner_user_id')
            ->distinct()
            ->pluck('partner_user_id');

        $eligibleUsers = $userModel::query()
            ->whereIn($userModel::make()->getKeyName(), $partnerUserIds)
            ->get()
            ->filter(fn ($user) => $eligibility->isEligibleToApply($user))
            ->values();

        $skipped = $partnerUserIds->count() - $eligibleUsers->count();
        $count = $eligibleUsers->count();

        if ($count === 0) {
            $this->info('No currently-eligible partners to notify (skipped '.$skipped.').');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — would notify '.$count.' user(s):');
            foreach ($eligibleUsers as $user) {
                $this->line(' - #'.$user->getKey().' '.($user->email ?? '(no email)'));
            }

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $proceed = confirm(
                label: 'Send PartnerEligibleToApply email to '.$count.' user(s)?',
                default: false,
            );
            if (! $proceed) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }
        }

        foreach ($eligibleUsers as $user) {
            Notification::send($user, new PartnerEligibleToApply);
        }

        $this->info('Notified '.$count.' user(s); '.$skipped.' skipped (open application or below threshold).');

        return self::SUCCESS;
    }
}

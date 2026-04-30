<?php

namespace A2ZWeb\Affiliate\Console\Commands;

use A2ZWeb\Affiliate\Models\AffiliateReferral;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateFromJijunairCommand extends Command
{
    protected $signature = 'affiliate:migrate-from-jijunair {--dry-run : Print summary without writing.}';

    protected $description = 'One-shot copy of referral data from jijunair/laravel-referral tables into the affiliate_referrals table.';

    public function handle(): int
    {
        if (! Schema::hasTable('referrals')) {
            $this->warn('Source `referrals` table not found — nothing to migrate.');

            return self::SUCCESS;
        }

        $rows = DB::table('referrals')
            ->whereNotNull('user_id')
            ->whereNotNull('referrer_id')
            ->get();

        $this->info('Found '.$rows->count().' rows in jijunair `referrals`.');

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $migrated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $exists = AffiliateReferral::query()
                ->where('referred_user_id', $row->user_id)
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            AffiliateReferral::create([
                'partner_user_id' => $row->referrer_id,
                'referred_user_id' => $row->user_id,
                'code_used' => $row->referral_code ?? '',
                'ip' => null,
                'user_agent' => null,
                'attributed_at' => $row->created_at ? Carbon::parse($row->created_at) : Carbon::now(),
            ]);

            $migrated++;
        }

        $this->info('Migrated: '.$migrated.', skipped (already present): '.$skipped);

        return self::SUCCESS;
    }
}

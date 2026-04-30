<?php

namespace A2ZWeb\Affiliate\Jobs;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateCommissionStatementPdf implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public int $statementId) {}

    public function handle(): void
    {
        $statement = AffiliateCommissionStatement::query()
            ->with(['lines', 'partner.affiliatePartner'])
            ->findOrFail($this->statementId);

        $partner = $statement->partner;
        $affiliatePartner = $partner?->affiliatePartner;

        $html = view('affiliate::statements.pdf', [
            'statement' => $statement,
            'partner' => $partner,
            'affiliatePartner' => $affiliatePartner,
            'lines' => $statement->lines,
            'agreement' => config('affiliate_statements.agreement'),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        $disk = config('affiliate_statements.pdf.disk', 'local');
        $path = sprintf(
            'affiliate-statements/%s/%d/%s.pdf',
            $statement->issuing_entity,
            $statement->period_end?->year ?? date('Y'),
            $statement->statement_number,
        );

        Storage::disk($disk)->put($path, $pdf->output());

        $statement->update([
            'pdf_disk' => $disk,
            'pdf_path' => $path,
        ]);
    }
}

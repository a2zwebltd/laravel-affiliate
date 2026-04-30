<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Http\Controllers;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatementController extends Controller
{
    public function show(Request $request, AffiliateCommissionStatement $statement): Response
    {
        abort_unless($request->user()->can('view', $statement), 404);

        $view = (string) config('affiliate.partner_statement_view', 'affiliate::partner-statement');

        return response()->view($view, [
            'statement' => $statement->load('lines'),
            'snapshot' => $statement->affiliate_snapshot ?? [],
        ]);
    }

    public function download(Request $request, AffiliateCommissionStatement $statement): StreamedResponse|Response
    {
        abort_unless($request->user()->can('downloadPdf', $statement), 404);

        $disk = $statement->pdf_disk ?: config('affiliate_statements.pdf.disk', 'local');
        $path = $statement->pdf_path;
        if (! $path || ! Storage::disk($disk)->exists($path)) {
            return response('PDF still being generated. Please try again in a moment.', 202);
        }

        return Storage::disk($disk)->download($path, $statement->statement_number.'.pdf');
    }
}

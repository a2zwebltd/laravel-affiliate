<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova;

use A2ZWeb\Affiliate\Models\AffiliatePayoutRequest as AffiliatePayoutRequestModel;
use A2ZWeb\Affiliate\Nova\Actions\ApprovePayoutRequest;
use A2ZWeb\Affiliate\Nova\Actions\MarkPayoutRequestPaid;
use A2ZWeb\Affiliate\Nova\Actions\RejectPayoutRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Laravel\Nova\Resource;

class AffiliatePayoutRequest extends Resource
{
    /**
     * @var class-string<AffiliatePayoutRequestModel>
     */
    public static string $model = AffiliatePayoutRequestModel::class;

    public static $title = 'id';

    public static function label(): string
    {
        return __('Affiliate Payout Requests');
    }

    public function fields(NovaRequest $request): array
    {
        $userResource = (string) config('affiliate.nova.user_resource');

        return [
            ID::make()->sortable(),

            BelongsTo::make(__('Partner'), 'partner', $userResource)->searchable(),

            Badge::make(__('Status'))->map([
                'pending' => 'warning',
                'approved' => 'info',
                'paid' => 'success',
                'rejected' => 'danger',
                'cancelled' => 'danger',
            ])->sortable(),

            Date::make(__('Period start'))->sortable(),
            Date::make(__('Period end'))->sortable(),

            Number::make(__('Gross $'), function (AffiliatePayoutRequestModel $r): string {
                return number_format($r->gross_amount_cents / 100, 2);
            }),
            Number::make(__('Adjustments $'), function (AffiliatePayoutRequestModel $r): string {
                return number_format($r->adjustments_amount_cents / 100, 2);
            }),
            Number::make(__('Net $'), function (AffiliatePayoutRequestModel $r): string {
                return number_format($r->net_amount_cents / 100, 2);
            })->sortable(),
            Text::make(__('Currency'))->onlyOnDetail(),

            DateTime::make(__('Requested at'))->sortable(),
            DateTime::make(__('Decided at'))->onlyOnDetail(),
            BelongsTo::make(__('Decided by'), 'decidedBy', $userResource)->nullable()->onlyOnDetail(),
            DateTime::make(__('Paid at'))->onlyOnDetail(),
            Text::make(__('Payment reference'))->onlyOnDetail()->copyable(),
            Text::make(__('Statement PDF'), function (AffiliatePayoutRequestModel $r): string {
                $statement = $r->statements()->latest('id')->first();
                if (! $statement) {
                    return '<span class="text-zinc-400">'.e(__('— no statement yet —')).'</span>';
                }
                if (! $statement->pdf_path) {
                    return '<span class="text-zinc-400">'.e(__('— PDF generating, refresh in a moment —')).'</span>';
                }
                $disk = $statement->pdf_disk ?: config('affiliate_statements.pdf.disk', 'local');
                try {
                    $url = Storage::disk($disk)->temporaryUrl($statement->pdf_path, now()->addMinutes(15));
                } catch (\Throwable) {
                    $url = Storage::disk($disk)->url($statement->pdf_path);
                }

                return '<a class="text-emerald-600 underline" target="_blank" href="'.e($url).'">'.e(__('Download :statement.pdf ↗', ['statement' => $statement->statement_number])).'</a>';
            })->asHtml()->onlyOnDetail(),
            Textarea::make(__('Rejection reason'))->onlyOnDetail()->nullable(),
            Textarea::make(__('Admin notes'))->onlyOnDetail()->nullable(),

            new Panel(__('Partner-supplied (optional)'), [
                Text::make(__('Purchase order'), 'purchase_order_id')->onlyOnDetail()->copyable(),
                Text::make(__('Invoice'), function (AffiliatePayoutRequestModel $r): string {
                    if (! $r->invoice_file_path) {
                        return '—';
                    }
                    $disk = $r->invoice_disk ?: config('affiliate.invoice_disk', 'local');
                    try {
                        $url = Storage::disk($disk)->temporaryUrl($r->invoice_file_path, now()->addMinutes(15));
                    } catch (\Throwable) {
                        $url = null;
                    }
                    $name = e($r->invoice_original_filename ?? basename($r->invoice_file_path));

                    return $url
                        ? '<a class="text-emerald-600 underline" target="_blank" href="'.$url.'">'.$name.' ↗</a>'
                        : $name;
                })->asHtml()->onlyOnDetail(),
            ]),

            new Panel(__('Payout snapshot (frozen at request)'), [
                Code::make(__('Snapshot'), 'payout_method_snapshot')->json()->onlyOnDetail(),
            ]),

            HasMany::make(__('Commissions'), 'commissions', AffiliateCommission::class),
            HasMany::make(__('Adjustments'), 'adjustments', AffiliateAdjustment::class),
            HasMany::make(__('Statements'), 'statements', AffiliateCommissionStatement::class),
        ];
    }

    public function actions(NovaRequest $request): array
    {
        return [
            new ApprovePayoutRequest,
            new MarkPayoutRequestPaid,
            new RejectPayoutRequest,
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }
}

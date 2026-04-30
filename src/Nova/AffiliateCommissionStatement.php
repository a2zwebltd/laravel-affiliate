<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Nova;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement as AffiliateCommissionStatementModel;
use A2ZWeb\Affiliate\Nova\Actions\CancelCommissionStatement;
use A2ZWeb\Affiliate\Nova\Actions\IssueCommissionStatement;
use A2ZWeb\Affiliate\Nova\Actions\MarkCommissionStatementPaid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Laravel\Nova\Resource;

class AffiliateCommissionStatement extends Resource
{
    /**
     * @var class-string<AffiliateCommissionStatementModel>
     */
    public static string $model = AffiliateCommissionStatementModel::class;

    public static $title = 'statement_number';

    public static $search = ['statement_number', 'payment_reference'];

    public static function label(): string
    {
        return __('Affiliate Commission Statements');
    }

    public function fields(NovaRequest $request): array
    {
        $userResource = (string) config('affiliate.nova.user_resource');

        return [
            ID::make()->sortable(),
            Text::make(__('Number'), 'statement_number')->copyable()->sortable(),
            BelongsTo::make(__('Partner'), 'partner', $userResource)->searchable(),

            Badge::make(__('Status'), 'payment_status')->map([
                'draft' => 'info',
                'issued' => 'warning',
                'paid' => 'success',
                'cancelled' => 'danger',
            ])->sortable(),

            Date::make(__('Period start'))->sortable(),
            Date::make(__('Period end'))->sortable(),

            Currency::make(__('Gross'), 'gross_revenue_total')->currency(strtoupper((string) ($this->currency ?? 'USD'))),
            Number::make(__('Rate %'), fn () => number_format(((float) $this->commission_rate) * 100, 2))->onlyOnDetail(),
            Currency::make(__('Commission'), 'commission_amount')->currency(strtoupper((string) ($this->currency ?? 'USD')))->sortable(),

            new Panel(__('Issuing entity (snapshot)'), [
                Text::make(__('Entity code'), 'issuing_entity')->onlyOnDetail(),
                Text::make(__('Legal name'), 'issuing_entity_legal_name')->onlyOnDetail(),
                Text::make(__('Company number'), fn () => ($this->issuing_entity_company_number_label ?: __('Company Number')).': '.$this->issuing_entity_company_number)->onlyOnDetail(),
                Textarea::make(__('Address'), 'issuing_entity_address')->onlyOnDetail(),
                Text::make(__('Country'), 'issuing_entity_country')->onlyOnDetail(),
                Textarea::make(__('Tax status note'), 'issuing_entity_tax_status_note')->onlyOnDetail(),
                Text::make(__('Statement prefix'), 'issuing_entity_statement_prefix')->onlyOnDetail(),
            ]),

            new Panel(__('Affiliate snapshot (frozen at draft creation)'), [
                Code::make(__('Snapshot'), 'affiliate_snapshot')->json()->onlyOnDetail(),
            ]),

            new Panel(__('Payment'), [
                Select::make(__('Method'), 'payment_method')->options([
                    'bank_transfer' => __('Bank transfer'),
                    'wise' => __('Wise'),
                    'paypal' => __('PayPal'),
                    'other' => __('Other'),
                ])->displayUsingLabels()->onlyOnDetail(),
                Text::make(__('Reference'), 'payment_reference')->onlyOnDetail()->copyable(),
                Date::make(__('Date'), 'payment_date')->onlyOnDetail(),
            ]),

            new Panel(__('Audit'), [
                DateTime::make(__('Issued at'))->onlyOnDetail(),
                DateTime::make(__('Paid at'))->onlyOnDetail(),
                DateTime::make(__('Sent to affiliate at'))->onlyOnDetail(),
                Text::make(__('PDF'), function (AffiliateCommissionStatementModel $s): string {
                    if (! $s->pdf_path) {
                        return '<span class="text-zinc-400">'.e(__('— PDF not generated yet —')).'</span>';
                    }
                    $disk = $s->pdf_disk ?: config('affiliate_statements.pdf.disk', 'local');
                    try {
                        $url = Storage::disk($disk)->temporaryUrl($s->pdf_path, now()->addMinutes(15));
                    } catch (\Throwable) {
                        $url = Storage::disk($disk)->url($s->pdf_path);
                    }

                    return '<a class="text-emerald-600 underline" target="_blank" href="'.e($url).'">'.e(__('Download :statement.pdf ↗', ['statement' => $s->statement_number])).'</a>';
                })->asHtml()->onlyOnDetail(),
                Text::make(__('PDF path'), 'pdf_path')->onlyOnDetail()->copyable(),
                Text::make(__('PDF disk'), 'pdf_disk')->onlyOnDetail(),
                BelongsTo::make(__('Source payout request'), 'payoutRequest', AffiliatePayoutRequest::class)->nullable()->onlyOnDetail(),
                Textarea::make(__('Notes'))->onlyOnDetail()->nullable(),
            ]),

            HasMany::make(__('Lines'), 'lines', AffiliateCommissionStatementLine::class),
        ];
    }

    public function actions(NovaRequest $request): array
    {
        return [
            new IssueCommissionStatement,
            new MarkCommissionStatementPaid,
            new CancelCommissionStatement,
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }
}

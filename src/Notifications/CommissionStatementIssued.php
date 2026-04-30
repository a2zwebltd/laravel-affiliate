<?php

namespace A2ZWeb\Affiliate\Notifications;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class CommissionStatementIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AffiliateCommissionStatement $statement) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $period = $this->statement->period_start->format('M Y').' – '.$this->statement->period_end->format('M Y');
        $amount = number_format((float) $this->statement->commission_amount, 2);
        $cur = strtoupper($this->statement->currency);

        $message = (new MailMessage)
            ->subject(__('Your commission statement for :period — :amount :currency', [
                'period' => $period,
                'amount' => $amount,
                'currency' => $cur,
            ]))
            ->greeting(__('Hello,'))
            ->line(__('Your affiliate commission statement is ready.'))
            ->line(__('Statement: **:number**', ['number' => $this->statement->statement_number]))
            ->line(__('Period: :period', ['period' => $period]))
            ->line(__('Total commission: :amount :currency', ['amount' => $amount, 'currency' => $cur]));

        if ($this->statement->payment_reference) {
            $message->line(__('Payment reference: :reference', ['reference' => $this->statement->payment_reference]));
        }

        $this->attachPdf($message);

        return $message;
    }

    private function attachPdf(MailMessage $message): void
    {
        if (! $this->statement->pdf_path || ! $this->statement->pdf_disk) {
            return;
        }

        try {
            $bytes = Storage::disk($this->statement->pdf_disk)->get($this->statement->pdf_path);
            if (is_string($bytes) && $bytes !== '') {
                $message->attachData(
                    $bytes,
                    $this->statement->statement_number.'.pdf',
                    ['mime' => 'application/pdf'],
                );
            }
        } catch (\Throwable) {
            // PDF still generating — recipient can re-fetch from dashboard
        }
    }
}

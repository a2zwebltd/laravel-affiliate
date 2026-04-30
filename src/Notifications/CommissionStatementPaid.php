<?php

namespace A2ZWeb\Affiliate\Notifications;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class CommissionStatementPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AffiliateCommissionStatement $statement) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format((float) $this->statement->commission_amount, 2);
        $cur = strtoupper($this->statement->currency);

        $message = (new MailMessage)
            ->subject(__('Payment confirmation — :number', ['number' => $this->statement->statement_number]))
            ->greeting(__('Payment sent!'))
            ->line(__('We just paid out :amount :currency for statement :number.', [
                'amount' => $amount,
                'currency' => $cur,
                'number' => $this->statement->statement_number,
            ]))
            ->line(__('Payment reference: :reference', ['reference' => $this->statement->payment_reference]))
            ->line(__('Payment method: :method', ['method' => $this->statement->payment_method]))
            ->line(__('Payment date: :date', ['date' => $this->statement->payment_date?->toDateString()]));

        if ($this->statement->pdf_path && $this->statement->pdf_disk) {
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
            }
        }

        return $message;
    }
}

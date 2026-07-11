<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseSubmittedNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Notifications\Expense;

use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpenseSubmittedNotification extends Notification {
    use Queueable;

    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly Expense $expense,
        public readonly array $channels = ['mail', 'database'],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array {
        return array_values(array_filter($this->channels, fn(string $c): bool => in_array($c, ['mail', 'database'], true)));
    }

    public function toMail(object $notifiable): MailMessage {
        $amount = number_format((float) $this->expense->amount_gross, 2, ',', '.') . ' ' . $this->expense->currency->value;
        $owner = $this->expense->user !== null ? $this->expense->user->name : '';

        return (new MailMessage)
            ->subject(__('Neue Spese zur Genehmigung: :amount', ['amount' => $amount]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name ?? '']))
            ->line(__(':owner hat eine neue Spese eingereicht.', ['owner' => \App\Support\MailText::plain($owner)]))
            ->line(\App\Support\MailText::plain($this->expense->description))
            ->line(__('Betrag: :amount', ['amount' => $amount]))
            ->action(__('Spese prüfen'), route('expense-approvals.inbox'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        return [
            'expense_id' => $this->expense->getKey(),
            'owner' => $this->expense->user?->name,
            'amount_gross' => $this->expense->amount_gross,
            'currency' => $this->expense->currency->value,
            'description' => $this->expense->description,
            'url' => route('expense-approvals.inbox'),
            'icon' => 'receipt_long',
        ];
    }
}

<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseDecidedNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Notifications\Expense;

use App\Enums\Expense\ExpenseStatus;
use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Wird an den Spesen-Eigentümer geschickt, sobald ein Admin entscheidet
 * (Approved / Rejected / Reimbursed). Trägt den neuen Status, ggf. den
 * Ablehnungsgrund und einen Link zur Spese.
 */
class ExpenseDecidedNotification extends Notification {
    use Queueable;

    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly Expense $expense,
        public readonly array $channels = ['mail', 'database'],
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array {
        return array_values(array_filter($this->channels, fn(string $c): bool => in_array($c, ['mail', 'database'], true)));
    }

    public function toMail(object $notifiable): MailMessage {
        $amount = number_format((float) $this->expense->amount_gross, 2, ',', '.') . ' ' . $this->expense->currency;
        $status = $this->expense->status->label();

        $mail = (new MailMessage)
            ->subject(__('Spese :status: :amount', ['status' => $status, 'amount' => $amount]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name ?? '']))
            ->line(__('Deine Spese wurde :status.', ['status' => $status]))
            ->line($this->expense->description);

        if ($this->expense->status === ExpenseStatus::Rejected && $this->expense->reject_reason) {
            $mail->line(__('Begründung: :reason', ['reason' => $this->expense->reject_reason]));
        }

        return $mail->action(__('Spese öffnen'), route('expenses.index'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        return [
            'expense_id' => $this->expense->getKey(),
            'status' => $this->expense->status->value,
            'amount_gross' => $this->expense->amount_gross,
            'currency' => $this->expense->currency,
            'description' => $this->expense->description,
            'reject_reason' => $this->expense->reject_reason,
            'url' => route('expenses.index'),
            'icon' => 'receipt_long',
        ];
    }
}

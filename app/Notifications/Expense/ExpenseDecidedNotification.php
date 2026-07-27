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
use App\Notifications\DirectNotification;
use App\Support\NotificationText;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Wird an den Spesen-Eigentümer geschickt, sobald ein Admin entscheidet
 * (Approved / Rejected / Reimbursed). Trägt den neuen Status, ggf. den
 * Ablehnungsgrund und einen Link zur Spese.
 */
class ExpenseDecidedNotification extends DirectNotification {
    private const TITLE_KEY = 'Spese :status: :amount';

    private const MESSAGE_KEY = 'Deine Spese wurde :status.';

    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly Expense $expense,
        array $channels = ['mail', 'database'],
    ) {
        parent::__construct($channels);
    }

    public function toMail(object $notifiable): MailMessage {
        $amount = $this->formattedAmount();
        $status = $this->expense->status->label();

        $mail = (new MailMessage)
            ->subject(__('Spese :status: :amount', ['status' => $status, 'amount' => $amount]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name ?? '']))
            ->line(__('Deine Spese wurde :status.', ['status' => $status]))
            ->line(\App\Support\MailText::plain($this->expense->description));

        if ($this->expense->status === ExpenseStatus::Rejected && $this->expense->reject_reason) {
            $mail->line(__('Begründung: :reason', ['reason' => \App\Support\MailText::plain($this->expense->reject_reason)]));
        }

        return $mail->action(__('Spese öffnen'), route('expenses.index'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        // Status als Key+Fallback → NotificationText/Trans::or übersetzt ihn
        // erst beim Anzeigen in der Sprache des Betrachters.
        $statusParam = [
            'key' => 'enums.expense.status.' . $this->expense->status->value,
            'fallback' => $this->expense->status->label(),
        ];
        $titleParams = ['status' => $statusParam, 'amount' => $this->formattedAmount()];
        $messageParams = ['status' => $statusParam];

        return [
            'expense_id' => $this->expense->getKey(),
            'status' => $this->expense->status->value,
            'amount_gross' => $this->expense->amount_gross,
            'currency' => $this->expense->currency->value,
            'description' => $this->expense->description,
            'reject_reason' => $this->expense->reject_reason,
            'title' => NotificationText::render(self::TITLE_KEY, $titleParams),
            'title_key' => self::TITLE_KEY,
            'title_params' => $titleParams,
            'message' => NotificationText::render(self::MESSAGE_KEY, $messageParams),
            'message_key' => self::MESSAGE_KEY,
            'message_params' => $messageParams,
            'url' => route('expenses.index'),
            'icon' => 'receipt_long',
        ];
    }

    private function formattedAmount(): string {
        return NumberHelper::toGermanFormat(($this->expense->amount_gross?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) . ' ' . $this->expense->currency->value;
    }
}

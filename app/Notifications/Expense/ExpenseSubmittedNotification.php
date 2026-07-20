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
use App\Notifications\DirectNotification;
use App\Support\NotificationText;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Notifications\Messages\MailMessage;

class ExpenseSubmittedNotification extends DirectNotification {
    private const TITLE_KEY = 'Neue Spese zur Genehmigung: :amount';

    private const MESSAGE_KEY = ':owner hat eine neue Spese eingereicht.';

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
        $titleParams = ['amount' => $this->formattedAmount()];
        $messageParams = ['owner' => (string) ($this->expense->user->name ?? '')];

        return [
            'expense_id' => $this->expense->getKey(),
            'owner' => $this->expense->user?->name,
            'amount_gross' => $this->expense->amount_gross,
            'currency' => $this->expense->currency->value,
            'description' => $this->expense->description,
            'title' => NotificationText::render(self::TITLE_KEY, $titleParams),
            'title_key' => self::TITLE_KEY,
            'title_params' => $titleParams,
            'message' => NotificationText::render(self::MESSAGE_KEY, $messageParams),
            'message_key' => self::MESSAGE_KEY,
            'message_params' => $messageParams,
            'url' => route('expense-approvals.inbox'),
            'icon' => 'receipt_long',
        ];
    }

    private function formattedAmount(): string {
        return NumberHelper::toGermanFormat((float) $this->expense->amount_gross, 2, withThousandsSeparator: true) . ' ' . $this->expense->currency->value;
    }
}

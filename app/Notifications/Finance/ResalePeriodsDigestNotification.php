<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePeriodsDigestNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Notifications\Finance;

use App\Notifications\DirectNotification;
use App\Support\NotificationText;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Reselling-Digest (Feature 152, Prozesse 6 / Review 2026-09-10 A5): geht nur
 * bei Befund raus — fällige Perioden, unbestätigte Vorschläge, Abos ohne
 * Halter, Verlängerungen in Kürze, Abos ohne Rechnung seit langem, noch nicht
 * abgeschlossene Rechnungsentwürfe der letzten Woche (Serienlauf), Abos mit
 * geändertem Katalog-Einkaufspreis (neue Preisliste). Empfänger sind die
 * Nutzer mit `reselling.manage`.
 */
class ResalePeriodsDigestNotification extends DirectNotification {
    private const TITLE_KEY = 'Abos & Lizenzen: :count Punkte warten auf Bearbeitung';

    private const MESSAGE_KEY = ':due fällige Perioden (offen :amount), :proposed unbestätigte Vorschläge, :unassigned Abos ohne Halter, :renewals Verlängerungen oder Enden in :renewal_days Tagen, :stale Abos ohne Rechnung seit über :stale_days Tagen, :drafts Rechnungsentwürfe der letzten :draft_days Tage noch nicht abgeschlossen, :catalog Abos mit geändertem Katalog-Einkaufspreis. Bitte die Periodenseite „Abos & Lizenzen" prüfen.';

    public function __construct(
        public readonly int $dueCount,
        public readonly string $dueAmount,
        public readonly int $proposedCount,
        public readonly int $unassignedCount,
        public readonly int $renewalCount,
        public readonly int $staleCount,
        public readonly int $renewalDays,
        public readonly int $staleAfterDays,
        public readonly int $draftCount = 0,
        public readonly int $draftDays = 7,
        public readonly int $catalogChanges = 0,
    ) {
        parent::__construct(['mail', 'database']);
    }

    /** Summe der Befunde — der Betreff. */
    public function total(): int {
        return $this->dueCount + $this->proposedCount + $this->unassignedCount + $this->renewalCount + $this->staleCount + $this->draftCount + $this->catalogChanges;
    }

    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)
            ->subject(__(self::TITLE_KEY, $this->titleParams()))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name ?? '']))
            ->line(__(self::MESSAGE_KEY, $this->messageParams()))
            ->action(__('Abo-Perioden öffnen'), route('finance.resale.periods.index'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        $titleParams = $this->titleParams();
        $messageParams = $this->messageParams();

        return [
            'title' => NotificationText::render(self::TITLE_KEY, $titleParams),
            'title_key' => self::TITLE_KEY,
            'title_params' => $titleParams,
            'message' => NotificationText::render(self::MESSAGE_KEY, $messageParams),
            'message_key' => self::MESSAGE_KEY,
            'message_params' => $messageParams,
            'due_count' => $this->dueCount,
            'proposed_count' => $this->proposedCount,
            'unassigned_count' => $this->unassignedCount,
            'renewal_count' => $this->renewalCount,
            'stale_count' => $this->staleCount,
            'draft_count' => $this->draftCount,
            'catalog_changes' => $this->catalogChanges,
            'icon' => 'subscriptions',
            'url' => route('finance.resale.periods.index'),
        ];
    }

    /** @return array<string, int> */
    private function titleParams(): array {
        return ['count' => $this->total()];
    }

    /** @return array<string, int|string> */
    private function messageParams(): array {
        return [
            'due' => $this->dueCount,
            'amount' => $this->dueAmount !== '' ? $this->dueAmount : '–',
            'proposed' => $this->proposedCount,
            'unassigned' => $this->unassignedCount,
            'renewals' => $this->renewalCount,
            'renewal_days' => $this->renewalDays,
            'stale' => $this->staleCount,
            'stale_days' => $this->staleAfterDays,
            'drafts' => $this->draftCount,
            'draft_days' => $this->draftDays,
            'catalog' => $this->catalogChanges,
        ];
    }
}

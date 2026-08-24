<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentExpiryScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\{Document, User};
use App\Services\Notification\NotificationDispatcher;

/**
 * Ablaufende/abgelaufene Dokumente (MVP-018): Vorlauf --expiring-days,
 * Empfänger ist der Ersteller + Eskalation für Abgelaufenes.
 */
class DocumentExpiryScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'documents';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $expiringDays = $options->expiringDays;

        return $this->runScan($dispatcher, [
            'affected' => fn(Document $document): ?User => $this->documentAffected($document),
            'due' => [
                'query' => fn() => Document::query()->expiringWithin($expiringDays),
                'event' => NotificationEvent::DocumentExpiringSoon,
                'payload' => fn(Document $document): array => $this->documentPayload($document, 'expiring_soon'),
            ],
            'overdue' => [
                'query' => fn() => Document::query()->expired(),
                'event' => NotificationEvent::DocumentExpired,
                'payload' => fn(Document $document): array => $this->documentPayload($document, 'expired'),
            ],
        ]);
    }

    private function documentAffected(Document $document): ?User {
        return User::query()->find((int) $document->getAttribute('created_by_user_id'));
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function documentPayload(Document $document, string $messageKey): array {
        $validUntil = $document->getAttribute('valid_until');

        return [
            'title' => (string) $document->getAttribute('title'),
            'message' => (string) __('notification.message.' . $messageKey, [
                'date' => $validUntil?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => ['date' => $validUntil instanceof \Illuminate\Support\Carbon ? $validUntil->toDateString() : '–'],
            'url' => route('documents.index'),
            'due_at' => $validUntil instanceof \Illuminate\Support\Carbon ? $validUntil : null,
        ];
    }
}

<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainExpiryScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Domain\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\Domain\DomainProjection;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;

/**
 * Vollaudit 2026-07 (H12): ablaufende Domains und gescheiterte
 * Verlängerungen an die Admins — Feature 083 hatte keinerlei
 * Benachrichtigungen.
 *
 * `failure_at` ist das FAILUREDATE des Anbieters: der Tag, an dem die
 * Domain ohne Verlängerung in den Fehlzustand geht — ein Lebenszyklusdatum,
 * das jede gesunde Domain trägt und das in der Zukunft liegt. Gescheitert
 * ist eine Verlängerung erst, wenn dieser Tag erreicht ist und der
 * Detailabgleich kein neues Datum gebracht hat (Befund 2026-10-27: nach
 * `domain:refresh-details` meldete der Scan je Charge „Verlängerung
 * fehlgeschlagen am 07.03.2027“, obwohl beim Anbieter alles in Ordnung war).
 */
class DomainExpiryScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'domains';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $expiringDays = $options->expiringDays;

        return $this->runScan($dispatcher, [
            'due' => [
                'query' => fn() => DomainProjection::query()
                    ->withoutGlobalScopes()
                    ->where(fn($q) => $q
                        ->where('failure_at', '<=', now())
                        ->orWhere(fn($qq) => $qq
                            ->whereNotNull('expiration_at')
                            ->whereBetween('expiration_at', [now(), now()->addDays($expiringDays)]))),
                'event' => NotificationEvent::DomainExpiring,
                'payload' => function (DomainProjection $domain): array {
                    $failed = $domain->failure_at !== null && ! $domain->failure_at->isFuture();
                    $titleKey = $failed ? 'notification.message.domain_renewal_failed_title' : 'notification.message.domain_expiring_title';

                    return [
                        'title' => (string) __($titleKey, ['domain' => (string) $domain->external_domain]),
                        'title_key' => $titleKey,
                        'title_params' => ['domain' => (string) $domain->external_domain],
                        'message' => $failed
                            ? (string) __('Verlängerung fehlgeschlagen am :date.', ['date' => $domain->failure_at->format('d.m.Y')])
                            : (string) __('Läuft ab am :date.', ['date' => $domain->expiration_at?->format('d.m.Y') ?? '—']),
                        'url' => route('domains.show', $domain),
                        'due_at' => $domain->expiration_at?->toIso8601String(),
                    ];
                },
            ],
        ]);
    }
}

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

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\Domain\DomainProjection;
use App\Services\Notification\NotificationDispatcher;

/**
 * Vollaudit 2026-07 (H12): ablaufende Domains bzw. fehlgeschlagene
 * Verlängerungen (failure_at) an die Admins — Feature 083 hatte keinerlei
 * Benachrichtigungen.
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
                        ->whereNotNull('failure_at')
                        ->orWhere(fn($qq) => $qq
                            ->whereNotNull('expiration_at')
                            ->whereBetween('expiration_at', [now(), now()->addDays($expiringDays)]))),
                'event' => NotificationEvent::DomainExpiring,
                'payload' => fn(DomainProjection $domain): array => [
                    'title' => (string) __('notification.message.domain_expiring_title', ['domain' => (string) $domain->external_domain]),
                    'title_key' => 'notification.message.domain_expiring_title',
                    'title_params' => ['domain' => (string) $domain->external_domain],
                    'message' => $domain->failure_at !== null
                        ? (string) __('Verlängerung fehlgeschlagen am :date.', ['date' => $domain->failure_at->format('d.m.Y')])
                        : (string) __('Läuft ab am :date.', ['date' => $domain->expiration_at?->format('d.m.Y') ?? '—']),
                    'url' => route('domains.show', $domain),
                    'due_at' => $domain->expiration_at?->toIso8601String(),
                ],
            ],
        ]);
    }
}

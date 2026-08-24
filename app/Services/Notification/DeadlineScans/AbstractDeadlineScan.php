<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractDeadlineScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\{Organization, User};
use App\Services\Notification\NotificationDispatcher;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Gemeinsames Skelett der Fristen-Scans (C18, aus dem ScanDeadlinesCommand
 * gezogen — B11): Zwei-Phasen-Schleife (Phase 'due' → notify(dedup), Phase
 * 'overdue' → notify(dedup)+escalateIfDue) sowie das Org-Refetch-Skelett der
 * delegierenden Scans. Payloads tragen `due_at` (MVP-331) für den
 * Kalender-Kanal. Läuft ohne Mandantenkontext → sieht alle Organisationen;
 * Regel-Auflösung pro Datensatz über organization_id.
 *
 * @phpstan-type TNotifyPayload array{title: string, message?: string|null, url?: string|null, icon?: string|null, due_at?: \DateTimeInterface|string|null}
 */
abstract class AbstractDeadlineScan implements DeadlineScan {
    /**
     * Generische Fristen-Schleife: je Phase lazyById(200) über die Query;
     * Phase 'due' → notify(dedup: true), Phase 'overdue' → notify(dedup: true)
     * + escalateIfDue. `require_affected` überspringt Zeilen ohne auflösbaren
     * Empfänger (heutiges continue-Verhalten einzelner Scans).
     *
     * @template TModel of Model
     *
     * @param array{
     *     affected?: Closure(TModel): ?User,
     *     require_affected?: bool,
     *     due?: array{query: Closure(): \Illuminate\Database\Eloquent\Builder<TModel>, event: NotificationEvent, payload: Closure(TModel): TNotifyPayload},
     *     overdue?: array{query: Closure(): \Illuminate\Database\Eloquent\Builder<TModel>, event: NotificationEvent, payload: Closure(TModel): TNotifyPayload},
     * } $scan
     */
    protected function runScan(NotificationDispatcher $dispatcher, array $scan): int {
        $affected = $scan['affected'] ?? static fn(Model $row): ?User => null;
        $requireAffected = (bool) ($scan['require_affected'] ?? false);
        $sent = 0;

        foreach (['due' => false, 'overdue' => true] as $phase => $escalate) {
            if (! isset($scan[$phase])) {
                continue;
            }

            ['query' => $query, 'event' => $event, 'payload' => $payload] = $scan[$phase];

            foreach ($query()->lazyById(200) as $row) {
                $user = $affected($row);
                if ($requireAffected && $user === null) {
                    continue;
                }

                $data = $payload($row);
                $sent += $dispatcher->notify($event, $row, $user, $data, dedup: true);
                if ($escalate) {
                    $sent += $dispatcher->escalateIfDue($event, $row, $data);
                }
            }
        }

        return $sent;
    }

    /**
     * Org-Refetch-Skelett der delegierenden Scans: distinct organization_id aus
     * der (ungescopten) Query, Organisation laden, Handler je Organisation —
     * Nummernkreis-/Audit-Kontext liegt im jeweiligen Fach-Service.
     *
     * @param \Illuminate\Database\Eloquent\Builder<covariant Model> $query
     * @param Closure(Organization): int $handler
     */
    protected function sumPerOrganization(\Illuminate\Database\Eloquent\Builder $query, Closure $handler): int {
        $sent = 0;

        foreach ($query->distinct()->pluck('organization_id') as $organizationId) {
            $organization = Organization::query()->whereKey($organizationId)->first();
            if ($organization !== null) {
                $sent += $handler($organization);
            }
        }

        return $sent;
    }

    protected function safeRoute(string $name): ?string {
        try {
            return route($name);
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityFeedService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Timeline;

use App\Models\Audit\AuditLog;
use App\Models\Journal\JournalEntry;
use App\Support\{EntityUrl, MorphMap};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Aktivitäts-Feed (Feature 183): wer hat wann was geändert — aus der
 * Audit-Kette und den Ereignisjournalen, rein lesend. Fachliche Zeitachsen
 * (Aufträge, Zeiten, Tickets) bleiben bei ihren Timeline-Diensten.
 */
final class ActivityFeedService {
    /** Puffer je Quelle für Rauschfilter und Zusammenführung. */
    private const BUFFER = 4;

    /**
     * Änderungen und Journal-Ereignisse eines Objekts; sichtbar für alle, die
     * das Objekt sehen (wie zuvor der Änderungsverlauf der Akten).
     *
     * @return array{items: list<TimelineItem>, hasMore: bool}
     */
    public function forSubject(Model $subject, int $limit = 20, int $offset = 0): array {
        $window = ($offset + $limit) * self::BUFFER;
        $logs = AuditLog::query()
            ->where('auditable_type', MorphMap::stableKey($subject::class))
            ->where('auditable_id', $subject->getKey())
            ->with('user:id,name')
            ->latest('created_at')->latest('id')
            ->limit($window)
            ->get();

        $items = array_map(fn (AuditLog $log): TimelineItem => $this->auditItem($log, withSubject: false), $this->withoutNoise($logs));
        $truncated = $logs->count() >= $window;
        if (method_exists($subject, 'journal')) {
            /** @var Collection<int, JournalEntry> $entries */
            $entries = $subject->journal()->reorder()->latest((string) $subject->journal()->getRelated()::column('occurred_at'))->latest('id')->with('actor:id,name')->limit($window)->get();
            $truncated = $truncated || $entries->count() >= $window;
            foreach ($entries as $entry) {
                $items[] = new TimelineItem(
                    id: 'journal:' . $entry->getTable() . ':' . $entry->getKey(),
                    type: 'journal',
                    icon: 'event_note',
                    occurredAt: $entry->occurredAt(),
                    actor: $entry->actor?->name,
                    title: $entry->label(),
                );
            }
        }

        return $this->page($items, $limit, $offset, $truncated);
    }

    /**
     * Organisationsweite Änderungen — nur für Personen, die das Audit sehen
     * dürfen (der Aufrufer prüft `viewAny` auf `AuditLog`).
     *
     * @param  class-string<Model>|null  $type
     * @return array{items: list<TimelineItem>, hasMore: bool}
     */
    public function global(int $organizationId, CarbonImmutable $from, CarbonImmutable $to, ?string $type = null, ?int $actorId = null, int $limit = 50, int $offset = 0): array {
        $query = AuditLog::query()
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', $from->startOfDay())
            ->where('created_at', '<=', $to->endOfDay())
            ->when($actorId !== null, fn ($q) => $q->where('user_id', $actorId));
        if ($type !== null) {
            $query->where('auditable_type', MorphMap::stableKey($type));
        }
        $logs = $query
            ->with('user:id,name')
            ->latest('created_at')->latest('id')
            ->limit($window = ($offset + $limit) * self::BUFFER)
            ->get();

        return $this->page(array_map(fn (AuditLog $log): TimelineItem => $this->auditItem($log, withSubject: true), $this->withoutNoise($logs)), $limit, $offset, $logs->count() >= $window);
    }

    private function auditItem(AuditLog $log, bool $withSubject): TimelineItem {
        $class = MorphMap::classFor($log->auditable_type);
        $subject = $withSubject
            ? __('entity-types.' . MorphMap::basename($log->auditable_type)) . ' #' . $log->auditable_id
            : null;

        return new TimelineItem(
            id: 'audit:' . $log->id,
            type: 'audit',
            icon: match ($log->event) {
                'created' => 'add_circle',
                'deleted' => 'delete',
                'updated' => 'edit',
                default => 'history',
            },
            occurredAt: $log->created_at,
            actor: $log->user?->name,
            title: $withSubject ? $subject . ' · ' . $log->eventLabel() : $log->eventLabel(),
            url: $withSubject && $class !== null ? EntityUrl::byType($class, (int) $log->auditable_id) : null,
        );
    }

    /**
     * Ein generisches „Geändert“ neben einem Fachereignis desselben Akteurs
     * innerhalb einer Minute ist Rauschen: das Fachereignis erklärt die Änderung.
     *
     * @param  Collection<int, AuditLog>  $logs
     * @return list<AuditLog>
     */
    private function withoutNoise(Collection $logs): array {
        $specific = $logs->reject(fn (AuditLog $log): bool => in_array($log->event, ['updated', 'created'], true));

        return array_values($logs->reject(fn (AuditLog $log): bool => $log->event === 'updated'
            && $specific->contains(fn (AuditLog $other): bool => $other->auditable_type === $log->auditable_type
                && (int) $other->auditable_id === (int) $log->auditable_id
                && (int) $other->user_id === (int) $log->user_id
                && abs(($other->created_at?->getTimestamp() ?? 0) - ($log->created_at?->getTimestamp() ?? 0)) <= 60))->all());
    }

    /**
     * @param  list<TimelineItem>  $items
     * @param  bool  $truncated  eine Quelle hat ihr Abfragefenster gefüllt — es gibt ältere Einträge
     * @return array{items: list<TimelineItem>, hasMore: bool}
     */
    private function page(array $items, int $limit, int $offset, bool $truncated): array {
        usort($items, static fn (TimelineItem $a, TimelineItem $b): int => ($b->occurredAt?->getTimestamp() ?? PHP_INT_MIN) <=> ($a->occurredAt?->getTimestamp() ?? PHP_INT_MIN) ?: strcmp($b->id, $a->id));

        return [
            'items' => array_slice($items, $offset, $limit),
            'hasMore' => $truncated || count($items) > $offset + $limit,
        ];
    }
}

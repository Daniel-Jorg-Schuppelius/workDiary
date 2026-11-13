<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\WorkQueue\Dto;

use App\Enums\WorkQueue\WorkItemUrgency;
use App\Support\Tz;
use Carbon\CarbonImmutable;

/**
 * Ein offener Vorgang im Arbeitsvorrat. `$count` > 1 bündelt einen geteilten
 * Eingang („12 offene Zuordnungsfälle“) statt hunderter Einzelzeilen;
 * `$personal` heißt: dem Nutzer ausdrücklich zugewiesen (nicht über Rolle).
 * `$onBehalfOf` nennt die vertretene Person, wenn der Vorgang über eine
 * Vertretung in den Vorrat kam (Feature 202).
 * `$rank` ordnet Vorgänge gleicher Dringlichkeit vor der Fälligkeit (kleiner
 * zuerst, Vorgabe 0) — etwa „Meine Aufträge“ in Abarbeitungsreihenfolge
 * (Feature 198).
 * `$movedAt` ist „zuletzt bewegt“ (Feature 179, MVP-1670): letzte
 * Statusänderung, Notiz oder Zeitbuchung am Gegenstand — optional; ohne Wert
 * zeigt der Vorrat keine Liegezeit (etwa gebündelte Eingänge).
 * Im Cache liegt nur die Array-Form ({@see toArray()}/{@see fromArray()}).
 *
 * @phpstan-type WorkItemArray array{source: string, key: string, title: string, url: string|null, detail: string|null, due_at: string|null, personal: bool, count: int, on_behalf_of: string|null, rank: int, moved_at: string|null}
 */
final readonly class WorkItem {
    public function __construct(
        public string $source,
        public string $key,
        public string $title,
        public ?string $url,
        public ?string $detail = null,
        public ?CarbonImmutable $dueAt = null,
        public bool $personal = false,
        public int $count = 1,
        public ?string $onBehalfOf = null,
        public int $rank = 0,
        public ?CarbonImmutable $movedAt = null,
    ) {}

    /** Derselbe Vorgang, gekennzeichnet „in Vertretung für …“ (Feature 202). */
    public function delegatedFor(string $name): self {
        return new self($this->source, $this->key, $this->title, $this->url, $this->detail, $this->dueAt, $this->personal, $this->count, $name, $this->rank, $this->movedAt);
    }

    public function urgency(CarbonImmutable $now): WorkItemUrgency {
        return WorkItemUrgency::for($this->dueAt, $now);
    }

    /**
     * Liegezeit (MVP-1670): Kalendertage seit der letzten Bewegung, null ohne
     * „zuletzt bewegt“. Gezählt wird in der Zeitzone der Organisation, damit
     * „heute bewegt“ nicht über Mitternacht UTC zu „gestern“ wird.
     */
    public function idleDays(CarbonImmutable $now): ?int {
        if ($this->movedAt === null) {
            return null;
        }
        $zone = Tz::current();

        return max(0, (int) round($this->movedAt->setTimezone($zone)->startOfDay()->diffInDays($now->setTimezone($zone)->startOfDay())));
    }

    /** Ruhend: mindestens `$threshold` Tage ohne Bewegung (Organisationseinstellung, MVP-1670). */
    public function isDormant(CarbonImmutable $now, int $threshold): bool {
        $days = $this->idleDays($now);

        return $days !== null && $days >= $threshold;
    }

    /**
     * „Zuletzt bewegt“ aus mehreren Stempeln (Status, Notiz, Zeitbuchung) —
     * der jüngste gewinnt; Zeichenketten kommen aus `withMax()`-Spalten.
     */
    public static function lastMoved(\DateTimeInterface|string|null ...$stamps): ?CarbonImmutable {
        $latest = null;
        foreach ($stamps as $stamp) {
            if ($stamp === null || $stamp === '') {
                continue;
            }
            $at = $stamp instanceof \DateTimeInterface ? CarbonImmutable::instance($stamp) : CarbonImmutable::parse($stamp);
            if ($latest === null || $at->greaterThan($latest)) {
                $latest = $at;
            }
        }

        return $latest;
    }

    /** @return WorkItemArray */
    public function toArray(): array {
        return [
            'source' => $this->source,
            'key' => $this->key,
            'title' => $this->title,
            'url' => $this->url,
            'detail' => $this->detail,
            'due_at' => $this->dueAt?->toIso8601String(),
            'personal' => $this->personal,
            'count' => $this->count,
            'on_behalf_of' => $this->onBehalfOf,
            'rank' => $this->rank,
            'moved_at' => $this->movedAt?->toIso8601String(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self {
        $dueAt = $data['due_at'] ?? null;
        $movedAt = $data['moved_at'] ?? null;

        return new self(
            source: (string) ($data['source'] ?? ''),
            key: (string) ($data['key'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            url: is_string($data['url'] ?? null) ? $data['url'] : null,
            detail: is_string($data['detail'] ?? null) ? $data['detail'] : null,
            dueAt: is_string($dueAt) && $dueAt !== '' ? CarbonImmutable::parse($dueAt) : null,
            personal: (bool) ($data['personal'] ?? false),
            count: max(1, (int) ($data['count'] ?? 1)),
            onBehalfOf: is_string($data['on_behalf_of'] ?? null) ? $data['on_behalf_of'] : null,
            rank: (int) ($data['rank'] ?? 0),
            movedAt: is_string($movedAt) && $movedAt !== '' ? CarbonImmutable::parse($movedAt) : null,
        );
    }
}

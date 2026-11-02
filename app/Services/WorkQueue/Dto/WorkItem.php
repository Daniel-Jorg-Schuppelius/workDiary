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
use Carbon\CarbonImmutable;

/**
 * Ein offener Vorgang im Arbeitsvorrat. `$count` > 1 bündelt einen geteilten
 * Eingang („12 offene Zuordnungsfälle“) statt hunderter Einzelzeilen;
 * `$personal` heißt: dem Nutzer ausdrücklich zugewiesen (nicht über Rolle).
 * Im Cache liegt nur die Array-Form ({@see toArray()}/{@see fromArray()}).
 *
 * @phpstan-type WorkItemArray array{source: string, key: string, title: string, url: string|null, detail: string|null, due_at: string|null, personal: bool, count: int}
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
    ) {}

    public function urgency(CarbonImmutable $now): WorkItemUrgency {
        return WorkItemUrgency::for($this->dueAt, $now);
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
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self {
        $dueAt = $data['due_at'] ?? null;

        return new self(
            source: (string) ($data['source'] ?? ''),
            key: (string) ($data['key'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            url: is_string($data['url'] ?? null) ? $data['url'] : null,
            detail: is_string($data['detail'] ?? null) ? $data['detail'] : null,
            dueAt: is_string($dueAt) && $dueAt !== '' ? CarbonImmutable::parse($dueAt) : null,
            personal: (bool) ($data['personal'] ?? false),
            count: max(1, (int) ($data['count'] ?? 1)),
        );
    }
}

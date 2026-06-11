<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimelineItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Timeline;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Einheitlicher Read-Model-Eintrag der Auftrags-Timeline (MVP-010,
 * docs/auftrags-timeline.md §4). Read-only — die Timeline schreibt nichts.
 *
 * @implements Arrayable<string, mixed>
 */
final class TimelineItem implements Arrayable {
    public const VISIBILITY_INTERNAL = 'internal';

    public const VISIBILITY_CUSTOMER = 'customer';

    public function __construct(
        /** Stabile Kennung, z. B. "audit:1234" oder "time:99" (Anker/Idempotenz). */
        public readonly string $id,
        /** Filtergruppe, siehe {@see DiaryEntryTimelineService::TYPES}. */
        public readonly string $type,
        /** Material-Symbol-Name für die Anzeige. */
        public readonly string $icon,
        public readonly ?CarbonInterface $occurredAt,
        /** Anzeigename des Auslösers (User/System/Kunde). */
        public readonly ?string $actor,
        /** Eine Zeile, lokalisiert, ohne HTML. */
        public readonly string $title,
        /** Optionale 1–3 Zeilen Detail (z. B. Diff „Offen → In Bearbeitung"). */
        public readonly ?string $summary = null,
        /** Drill-down-Link auf das Originalobjekt (URL, ggf. mit Fragment). */
        public readonly ?string $url = null,
        public readonly string $visibility = self::VISIBILITY_INTERNAL,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'icon' => $this->icon,
            'occurred_at' => $this->occurredAt?->toISOString(),
            'actor' => $this->actor,
            'title' => $this->title,
            'summary' => $this->summary,
            'url' => $this->url,
            'visibility' => $this->visibility,
        ];
    }
}

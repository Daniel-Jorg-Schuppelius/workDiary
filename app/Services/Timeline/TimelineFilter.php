<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimelineFilter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Timeline;

use Carbon\CarbonInterface;

/** Filter der Auftrags-Timeline neben dem Typ (MVP-1596): Zeitraum, Person, „nur kundenrelevant“. */
final class TimelineFilter {
    public function __construct(
        /** Beginn des ersten Tages */
        public readonly ?CarbonInterface $from = null,
        /** Ende des letzten Tages */
        public readonly ?CarbonInterface $to = null,
        public readonly ?int $actorId = null,
        public readonly bool $customerOnly = false,
    ) {}

    public function matches(TimelineItem $item, bool $withActor = true): bool {
        if ($this->customerOnly && $item->visibility !== TimelineItem::VISIBILITY_CUSTOMER) {
            return false;
        }
        if ($this->from !== null || $this->to !== null) {
            // Ein Eintrag ohne Zeitpunkt gehört in keinen Zeitraum.
            if ($item->occurredAt === null
                || ($this->from !== null && $item->occurredAt->lt($this->from))
                || ($this->to !== null && $item->occurredAt->gt($this->to))) {
                return false;
            }
        }

        return ! $withActor || $this->actorId === null || $item->actorId === $this->actorId;
    }
}

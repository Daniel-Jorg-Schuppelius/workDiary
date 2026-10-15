<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceDurationSuggester.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing;

use App\Models\Diary\TourStop;
use App\Services\Diary\Contracts\ServiceDurationSource;

/**
 * Erfahrungswert der Servicedauer (MVP-1184): Median der Ist-Verweildauern
 * (Abfahrt − Ankunft) erledigter Stopps je Auftragsart; je Kunde erst ab
 * fünf Werten. Nur Vorschlag — nie stilles Überschreiben.
 */
class ServiceDurationSuggester implements ServiceDurationSource {
    public const MIN_SAMPLES = 3;

    public const MIN_CUSTOMER_SAMPLES = 5;

    public const MAX_MINUTES = 12 * 60;

    /** @return array{minutes: int, samples: int, scope: 'customer'|'type'}|null */
    public function suggest(?int $entryTypeId, ?int $customerId = null): ?array {
        if ($entryTypeId === null) {
            return null;
        }
        if ($customerId !== null) {
            $customer = $this->median($entryTypeId, $customerId);
            if ($customer !== null && $customer['samples'] >= self::MIN_CUSTOMER_SAMPLES) {
                return $customer + ['scope' => 'customer'];
            }
        }
        $type = $this->median($entryTypeId, null);

        return $type !== null && $type['samples'] >= self::MIN_SAMPLES ? $type + ['scope' => 'type'] : null;
    }

    public function suggestMinutes(?int $entryTypeId, ?int $customerId = null): ?int {
        return $this->suggest($entryTypeId, $customerId)['minutes'] ?? null;
    }

    /** @return array{minutes: int, samples: int}|null */
    private function median(int $entryTypeId, ?int $customerId): ?array {
        $query = TourStop::query()
            ->join('diary_entries', 'diary_entries.id', '=', 'tour_stops.diary_entry_id')
            ->where('diary_entries.entry_type_id', $entryTypeId)
            ->where('tour_stops.outcome', TourStop::OUTCOME_DONE)
            ->whereNotNull('tour_stops.arrived_at')
            ->whereNotNull('tour_stops.departed_at')
            ->when($customerId !== null, fn($q) => $q->where('diary_entries.customer_id', $customerId))
            ->orderByDesc('tour_stops.departed_at')
            ->limit(200);
        $minutes = [];
        foreach ($query->get(['tour_stops.arrived_at', 'tour_stops.departed_at']) as $stop) {
            if ($stop->arrived_at === null || $stop->departed_at === null) {
                continue;
            }
            $value = (int) round($stop->arrived_at->diffInMinutes($stop->departed_at, true));
            if ($value > 0 && $value <= self::MAX_MINUTES) {
                $minutes[] = $value;
            }
        }
        if ($minutes === []) {
            return null;
        }
        sort($minutes);
        $count = count($minutes);
        $median = $count % 2 === 1 ? $minutes[intdiv($count, 2)] : (int) round(($minutes[$count / 2 - 1] + $minutes[$count / 2]) / 2);

        return ['minutes' => (int) (ceil($median / 5) * 5), 'samples' => $count];
    }
}

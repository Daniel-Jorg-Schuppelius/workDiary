<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleTimelineImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Location;

use App\Models\Location\LocationPoint;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Importiert eine rückwirkende Google-Standort-Historie (on-device Timeline-
 * Export bzw. klassisches Records.json) und speist sie in dieselbe Engine wie
 * der laufende Ingest. Da Google die Cloud-Timeline abgeschaltet hat, ist der
 * Upload eines manuell exportierten JSON der einzige verbleibende Weg.
 *
 * Unterstützte Formate:
 *  - Records.json:  {"locations":[{"latitudeE7","longitudeE7","timestamp"|"timestampMs","accuracy"}]}
 *  - Semantic:      {"semanticSegments":[{"timelinePath":[{"point":"52.52°, 13.40°","time":...}]}]}
 */
class GoogleTimelineImporter {
    public function __construct(private readonly LocationIngestService $ingest) {}

    public function import(User $user, string $json): int {
        $points = $this->parse($json);

        return $this->ingest->ingest($user, $points, LocationPoint::SOURCE_GOOGLE);
    }

    /**
     * @return array<int, array{lat: float, lng: float, recorded_at: Carbon, accuracy_m: int|null}>
     */
    public function parse(string $json): array {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return [];
        }

        $points = [];

        // Format A: Records.json
        foreach ($this->arr($data, 'locations') as $loc) {
            $p = $this->fromRecord($loc);
            if ($p !== null) {
                $points[] = $p;
            }
        }

        // Format B: on-device Semantic Timeline
        foreach ($this->arr($data, 'semanticSegments') as $segment) {
            foreach ($this->arr($segment, 'timelinePath') as $node) {
                $p = $this->fromSemantic($node);
                if ($p !== null) {
                    $points[] = $p;
                }
            }
        }

        return $points;
    }

    /**
     * @param array<string, mixed> $loc
     *
     * @return array{lat: float, lng: float, recorded_at: Carbon, accuracy_m: int|null}|null
     */
    private function fromRecord(array $loc): ?array {
        if (! isset($loc['latitudeE7'], $loc['longitudeE7'])) {
            return null;
        }

        $time = $loc['timestamp'] ?? null;
        if ($time === null && isset($loc['timestampMs']) && is_numeric($loc['timestampMs'])) {
            $recordedAt = Carbon::createFromTimestampMsUTC((int) $loc['timestampMs']);
        } elseif (is_string($time) && $time !== '') {
            $recordedAt = $this->safeParse($time);
        } else {
            return null;
        }

        if ($recordedAt === null) {
            return null;
        }

        $accuracy = $loc['accuracy'] ?? null;

        return [
            'lat' => (int) $loc['latitudeE7'] / 1e7,
            'lng' => (int) $loc['longitudeE7'] / 1e7,
            'recorded_at' => $recordedAt,
            'accuracy_m' => is_numeric($accuracy) ? (int) round((float) $accuracy) : null,
        ];
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array{lat: float, lng: float, recorded_at: Carbon, accuracy_m: int|null}|null
     */
    private function fromSemantic(array $node): ?array {
        $raw = $node['point'] ?? null; // z. B. "52.5200000°, 13.4050000°"
        $time = $node['time'] ?? null;
        if (! is_string($raw) || ! is_string($time)) {
            return null;
        }

        if (! preg_match('/(-?\d+(?:\.\d+)?)\D+(-?\d+(?:\.\d+)?)/', $raw, $m)) {
            return null;
        }
        $recordedAt = $this->safeParse($time);
        if ($recordedAt === null) {
            return null;
        }

        return [
            'lat' => (float) $m[1],
            'lng' => (float) $m[2],
            'recorded_at' => $recordedAt,
            'accuracy_m' => null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<int, array<string, mixed>>
     */
    private function arr(array $data, string $key): array {
        $value = $data[$key] ?? null;

        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    private function safeParse(string $value): ?Carbon {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}

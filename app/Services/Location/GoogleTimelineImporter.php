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
 * Importiert eine rückwirkende Google-Standort-Historie und speist sie in
 * dieselbe Engine wie der laufende Ingest. Da Google die Cloud-Timeline
 * abgeschaltet hat, ist der Upload eines manuell exportierten JSON der einzige
 * verbleibende Weg (Maps → Zeitachse → Zeitachsendaten exportieren).
 *
 * Unterstützte Formate:
 *  - Records.json:  {"locations":[{"latitudeE7","longitudeE7","timestamp"|"timestampMs","accuracy"}]}
 *  - on-device:     {"semanticSegments":[{"startTime","endTime","visit"|"activity"|"timelinePath"}]}
 *                   – iOS liefert dieselben Segmente als Top-Level-Liste.
 *  - Takeout:       {"timelineObjects":[{"placeVisit"|"activitySegment"}]}
 *  - Rohsignale:    {"rawSignals":[{"position":{"LatLng","accuracyMeters","timestamp"}}]}
 *
 * Aufenthalte (visit/placeVisit) liefern zwei Punkte – Beginn und Ende –, weil
 * der {@see VisitBuilder} die Verweildauer aus dem Abstand zweier Punkte
 * ableitet; ein Einzelpunkt würde als Durchfahrt verworfen.
 *
 * @phpstan-type ParsedPoint array{lat: float, lng: float, recorded_at: Carbon, accuracy_m: int|null}
 */
class GoogleTimelineImporter {
    /**
     * Koordinatenpaar in allen von Google verwendeten Schreibweisen:
     * "52.5200000°, 13.4050000°" ebenso wie "geo:51.507400,-0.127800".
     * Das Komma trennt – nicht "irgendein Nicht-Ziffern-Zeichen", sonst
     * verschluckt der Trenner das Minus der westlichen Länge. /u ist Pflicht:
     * ohne Unicode-Modus wäre nur das zweite Byte des Gradzeichens optional.
     */
    private const LAT_LNG = '/(-?\d+(?:\.\d+)?)\s*°?\s*,\s*(-?\d+(?:\.\d+)?)/u';

    /** Schlüsselpaare, unter denen Koordinaten je nach Format stehen. */
    private const COORD_KEYS = [
        ['latitudeE7', 'longitudeE7', 1e7],
        ['latE7', 'lngE7', 1e7],
        ['latitude', 'longitude', 1.0],
        ['lat', 'lng', 1.0],
    ];

    public function __construct(private readonly LocationIngestService $ingest) {}

    public function import(User $user, string $json): int {
        $points = $this->parse($json);

        return $this->ingest->ingest($user, $points, LocationPoint::SOURCE_GOOGLE);
    }

    /**
     * @return array<int, ParsedPoint>
     */
    public function parse(string $json): array {
        /** @var array<mixed>|null $data */
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return [];
        }

        $points = [];

        foreach ($this->arr($data, 'locations') as $loc) {
            $this->push($points, $this->point($this->coords($loc), $loc['timestamp'] ?? $loc['timestampMs'] ?? null, $loc['accuracy'] ?? null));
        }

        foreach ($this->segments($data) as $segment) {
            $this->collectSegment($segment, $points);
        }

        foreach ($this->arr($data, 'timelineObjects') as $object) {
            $this->collectTimelineObject($object, $points);
        }

        foreach ($this->arr($data, 'rawSignals') as $signal) {
            $position = $signal['position'] ?? null;
            if (is_array($position)) {
                $this->push($points, $this->point($this->coords($position), $position['timestamp'] ?? null, $position['accuracyMeters'] ?? null));
            }
        }

        return $this->finish($points);
    }

    /**
     * Segmente des on-device-Exports. Android verschachtelt sie unter
     * "semanticSegments", iOS legt dieselbe Struktur als Top-Level-Liste ab.
     *
     * @param array<mixed> $data
     *
     * @return array<int, array<string, mixed>>
     */
    private function segments(array $data): array {
        $segments = $this->arr($data, 'semanticSegments');
        if ($segments !== [] || ! array_is_list($data)) {
            return $segments;
        }

        return array_values(array_filter($data, is_array(...)));
    }

    /**
     * @param array<string, mixed>    $segment
     * @param array<int, ParsedPoint> $points
     */
    private function collectSegment(array $segment, array &$points): void {
        $startedAt = $segment['startTime'] ?? null;
        $endedAt = $segment['endTime'] ?? null;

        // Bewegungsspur: eigener Zeitstempel je Stützpunkt – neuere Exporte
        // geben statt der Uhrzeit den Minutenversatz zum Segmentbeginn an.
        foreach ($this->arr($segment, 'timelinePath') as $node) {
            $offset = $node['durationMinutesOffsetFromStartTime'] ?? null;
            $at = $this->time($node['time'] ?? $startedAt);
            if ($at !== null && ! isset($node['time']) && is_numeric($offset)) {
                $at = $at->copy()->addMinutes((int) $offset);
            }

            $this->push($points, $this->point($this->coords($node['point'] ?? $node), $at, null));
        }

        // Aufenthalt: Ort steckt im wahrscheinlichsten Kandidaten.
        $visit = $segment['visit'] ?? null;
        if (is_array($visit)) {
            $candidate = $visit['topCandidate'] ?? null;
            $coords = $this->coords(is_array($candidate) ? ($candidate['placeLocation'] ?? null) : null);
            $this->push($points, $this->point($coords, $startedAt, null));
            $this->push($points, $this->point($coords, $endedAt, null));
        }

        // Fahrt/Weg: belegt das Verlassen des vorherigen Aufenthalts.
        $activity = $segment['activity'] ?? null;
        if (is_array($activity)) {
            $this->push($points, $this->point($this->coords($activity['start'] ?? null), $startedAt, null));
            $this->push($points, $this->point($this->coords($activity['end'] ?? null), $endedAt, null));
        }
    }

    /**
     * Klassischer Takeout-Export ("Semantic Location History"), den Archive von
     * vor der on-device-Umstellung enthalten.
     *
     * @param array<string, mixed>    $object
     * @param array<int, ParsedPoint> $points
     */
    private function collectTimelineObject(array $object, array &$points): void {
        $visit = $object['placeVisit'] ?? null;
        if (is_array($visit)) {
            [$startedAt, $endedAt] = $this->duration($visit);
            $coords = $this->coords($visit['location'] ?? null);
            $this->push($points, $this->point($coords, $startedAt, null));
            $this->push($points, $this->point($coords, $endedAt, null));
        }

        $activity = $object['activitySegment'] ?? null;
        if (! is_array($activity)) {
            return;
        }

        [$startedAt, $endedAt] = $this->duration($activity);
        $this->push($points, $this->point($this->coords($activity['startLocation'] ?? null), $startedAt, null));
        $this->push($points, $this->point($this->coords($activity['endLocation'] ?? null), $endedAt, null));

        $rawPath = $activity['simplifiedRawPath'] ?? null;
        foreach (is_array($rawPath) ? $this->arr($rawPath, 'points') : [] as $node) {
            $this->push($points, $this->point($this->coords($node), $node['timestamp'] ?? $node['timestampMs'] ?? null, $node['accuracyMeters'] ?? null));
        }
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array{0: mixed, 1: mixed} Beginn/Ende als noch ungeparste Rohwerte
     */
    private function duration(array $node): array {
        $duration = $node['duration'] ?? null;
        if (! is_array($duration)) {
            return [null, null];
        }

        return [
            $duration['startTimestamp'] ?? $duration['startTimestampMs'] ?? null,
            $duration['endTimestamp'] ?? $duration['endTimestampMs'] ?? null,
        ];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function coords(mixed $raw): ?array {
        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                // "latLng" (on-device) bzw. "LatLng" (rawSignals) – Groß-/Kleinschreibung wechselt.
                if (is_string($key) && strcasecmp($key, 'latLng') === 0) {
                    return $this->coords($value);
                }
            }

            foreach (self::COORD_KEYS as [$latKey, $lngKey, $divisor]) {
                if (isset($raw[$latKey], $raw[$lngKey]) && is_numeric($raw[$latKey]) && is_numeric($raw[$lngKey])) {
                    return $this->validated((float) $raw[$latKey] / $divisor, (float) $raw[$lngKey] / $divisor);
                }
            }

            return null;
        }

        if (! is_string($raw) || ! preg_match(self::LAT_LNG, $raw, $m)) {
            return null;
        }

        return $this->validated((float) $m[1], (float) $m[2]);
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function validated(float $lat, float $lng): ?array {
        if (abs($lat) > 90.0 || abs($lng) > 180.0) {
            return null;
        }

        return [$lat, $lng];
    }

    /**
     * @param array{0: float, 1: float}|null $coords
     *
     * @return ParsedPoint|null
     */
    private function point(?array $coords, mixed $time, mixed $accuracy): ?array {
        $recordedAt = $this->time($time);
        if ($coords === null || $recordedAt === null) {
            return null;
        }

        return [
            'lat' => $coords[0],
            'lng' => $coords[1],
            'recorded_at' => $recordedAt,
            'accuracy_m' => is_numeric($accuracy) ? (int) round((float) $accuracy) : null,
        ];
    }

    /**
     * ISO-8601 ebenso wie Unix-Stempel in Sekunden oder Millisekunden
     * (timestampMs kommt als String).
     */
    private function time(mixed $value): ?Carbon {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_int($value) || is_float($value) || (is_string($value) && ctype_digit($value))) {
            $number = (float) $value;

            return $number >= 1e12
                ? Carbon::createFromTimestampMsUTC((int) $number)
                : Carbon::createFromTimestampUTC((int) $number);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, ParsedPoint> $points
     * @param ParsedPoint|null        $point
     */
    private function push(array &$points, ?array $point): void {
        if ($point !== null) {
            $points[] = $point;
        }
    }

    /**
     * Doppelte Punkte entfernen – Aufenthalts- und Fahrt-Segmente teilen sich
     * die Nahtstelle – und chronologisch sortieren, damit der Zustandsautomat
     * des VisitBuilder eine saubere Spur sieht.
     *
     * @param array<int, ParsedPoint> $points
     *
     * @return array<int, ParsedPoint>
     */
    private function finish(array $points): array {
        $unique = [];
        foreach ($points as $point) {
            $key = $point['recorded_at']->getTimestamp() . '|' . round($point['lat'], 6) . '|' . round($point['lng'], 6);
            // Bei gleichem Punkt gewinnt die Variante mit Genauigkeitsangabe.
            if (! isset($unique[$key]) || ($unique[$key]['accuracy_m'] === null && $point['accuracy_m'] !== null)) {
                $unique[$key] = $point;
            }
        }

        $points = array_values($unique);
        usort($points, fn(array $a, array $b): int => $a['recorded_at']->getTimestamp() <=> $b['recorded_at']->getTimestamp());

        return $points;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<int, array<string, mixed>>
     */
    private function arr(array $data, string $key): array {
        $value = $data[$key] ?? null;

        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }
}

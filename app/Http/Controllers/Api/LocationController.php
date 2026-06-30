<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location\LocationDeviceToken;
use App\Models\Location\LocationPoint;
use App\Models\Organization;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Location\LocationIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Nimmt Standort-Pushes von Geräte-Apps (OwnTracks, Traccar/OsmAnd) sowie
 * generische Batches entgegen. Authentifizierung über ein widerrufbares
 * Pro-Gerät-Token im Pfad – diese Apps können sich nicht interaktiv anmelden.
 *
 * Vor der Annahme werden zwei Schranken geprüft: das Lizenzmodul der
 * Organisation und das ausdrückliche Per-User-Opt-in (DSGVO-Einwilligung).
 */
class LocationController extends Controller {
    public const MODULE = 'module.standorterfassung';

    public const OPT_IN_PREFERENCE = 'location_tracking_enabled';

    public function __construct(
        private readonly LocationIngestService $ingest,
        private readonly FeatureFlagResolver $features,
    ) {}

    public function ingest(Request $request, string $token): JsonResponse {
        $device = LocationDeviceToken::query()
            ->where('token_hash', LocationDeviceToken::hashToken($token))
            ->whereNull('revoked_at')
            ->first();

        if (! $device instanceof LocationDeviceToken) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $user = $device->user;
        if ($user === null) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        // Org-Kontext binden, damit Lizenzauflösung und Scopes greifen.
        $org = $user->organization;
        if ($org instanceof Organization) {
            app()->instance('currentOrganization', $org);
        }

        // Lizenzmodul der Organisation.
        if (! $this->features->isEnabled(self::MODULE)) {
            return response()->json(['error' => 'module_disabled'], 403);
        }

        // Ausdrückliches Opt-in des Nutzers (DSGVO-Einwilligung).
        if (! $user->getPreference(self::OPT_IN_PREFERENCE, false)) {
            return response()->json(['error' => 'tracking_not_consented'], 403);
        }

        $points = $this->extractPoints($request);
        $this->ingest->ingest($user, $points, $this->resolveSource($request));

        $device->forceFill(['last_used_at' => Carbon::now()])->save();

        // OwnTracks erwartet ein JSON-Array (Freundes-Positionen) als Antwort.
        return response()->json([]);
    }

    /**
     * Punktueller Browser-Stempel: nimmt EINEN aktuellen Standort des
     * angemeldeten Nutzers (navigator.geolocation) entgegen. Auth über die
     * Session/Sanctum, nicht über ein Geräte-Token.
     */
    public function stamp(Request $request): JsonResponse {
        $user = $this->authUser();

        if (! $this->features->isEnabled(self::MODULE)) {
            return response()->json(['error' => 'module_disabled'], 403);
        }
        if (! $user->getPreference(self::OPT_IN_PREFERENCE, false)) {
            return response()->json(['error' => 'tracking_not_consented'], 403);
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ]);

        $count = $this->ingest->ingest($user, [[
            'lat' => (float) $data['lat'],
            'lng' => (float) $data['lng'],
            'recorded_at' => Carbon::now(),
            'accuracy_m' => isset($data['accuracy_m']) ? (int) round((float) $data['accuracy_m']) : null,
        ]], LocationPoint::SOURCE_BROWSER);

        return response()->json(['stored' => $count]);
    }

    /**
     * @return array<int, array{lat: float, lng: float, recorded_at: Carbon, accuracy_m: int|null}>
     */
    private function extractPoints(Request $request): array {
        $raw = $request->all();

        // Generischer Batch unter "points" oder als reine Liste.
        $candidates = match (true) {
            isset($raw['points']) && is_array($raw['points']) => $raw['points'],
            array_is_list($raw) && $raw !== [] => $raw,
            default => [$raw],
        };

        $points = [];
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            $normalized = $this->normalizeOne($candidate);
            if ($normalized !== null) {
                $points[] = $normalized;
            }
        }

        return $points;
    }

    /**
     * @param array<string, mixed> $p
     *
     * @return array{lat: float, lng: float, recorded_at: Carbon, accuracy_m: int|null}|null
     */
    private function normalizeOne(array $p): ?array {
        $lat = $p['lat'] ?? $p['latitude'] ?? null;
        $lng = $p['lng'] ?? $p['lon'] ?? $p['longitude'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $accuracy = $p['accuracy_m'] ?? $p['acc'] ?? $p['accuracy'] ?? null;

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'recorded_at' => $this->resolveTimestamp($p),
            'accuracy_m' => is_numeric($accuracy) ? (int) round((float) $accuracy) : null,
        ];
    }

    /** @param array<string, mixed> $p */
    private function resolveTimestamp(array $p): Carbon {
        // OwnTracks: tst (Unix-Sekunden). Traccar/OsmAnd: timestamp. Sonst ISO.
        $tst = $p['tst'] ?? $p['timestamp'] ?? null;
        if (is_numeric($tst)) {
            return Carbon::createFromTimestampUTC((int) $tst);
        }

        $iso = $p['recorded_at'] ?? (is_string($tst) ? $tst : null);
        if (is_string($iso) && $iso !== '') {
            try {
                return Carbon::parse($iso);
            } catch (\Throwable) {
                // Fällt unten auf "jetzt" zurück.
            }
        }

        return Carbon::now();
    }

    private function resolveSource(Request $request): string {
        $type = $request->input('_type');
        if ($request->has('tst') || $type === 'location') {
            return LocationPoint::SOURCE_OWNTRACKS;
        }
        if ($request->has('timestamp')) {
            return LocationPoint::SOURCE_TRACCAR;
        }

        return (string) $request->input('source', LocationPoint::SOURCE_OWNTRACKS);
    }
}

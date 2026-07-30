<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImpossibleTravelDetector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Security\SecurityEventType;
use App\Models\{User, UserKnownDevice};
use App\Notifications\GenericEventNotification;
use CommonToolkit\Helper\Geo\{GeoHelper, IpLocationHelper};
use Illuminate\Support\Facades\Notification;

/**
 * Impossible-Travel-Erkennung (Feature 097, MVP-449): vergleicht die grobe
 * Geoposition einer neuen Anmeldung mit der letzten bekannten Position des
 * Nutzers (Known-Device-Historie, Feature 096). Ist die daraus abgeleitete
 * Reisegeschwindigkeit physisch unplausibel, entsteht ein
 * `auth.impossible_travel`-Event plus Benachrichtigung an Nutzer **und**
 * Plattform-Admins.
 *
 * Leitplanken:
 *  - Ohne lokale `.mmdb` still deaktiviert (Degradation wie Feature 085).
 *  - Mindestdistanz (Default 300 km) unterdrückt Pendel-/Mobilfunk-Rauschen;
 *    Geschwindigkeitsschwelle Default 900 km/h (Linienflug-Niveau).
 *  - **Keine** Auto-Abmeldung im MVP — nur Signal + Alarm; Step-up-2FA
 *    bleibt bewusst „Später".
 *  - Muss VOR {@see KnownDeviceService::touch()} laufen, sonst ist die
 *    „letzte bekannte Position" bereits die aktuelle.
 */
class ImpossibleTravelDetector {
    public function __construct(
        private readonly SecurityEventLogger $security,
    ) {}

    /**
     * @return array{distance_km: float, hours: float, speed_kmh: float}|null
     *                                                                       `null` = kein Treffer / nicht auswertbar
     */
    public function check(User $user, ?string $ip): ?array {
        if (! (bool) config('security.impossible_travel.enabled', true) || ! $this->geoAvailable()) {
            return null;
        }

        $current = $this->coordinates($ip);
        if ($current === null) {
            return null;
        }

        $previous = UserKnownDevice::query()
            ->where('user_id', $user->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->first();
        if ($previous === null) {
            return null; // keine Referenzposition — erste geo-fähige Anmeldung
        }

        $distance = GeoHelper::haversineKm(
            (float) $previous->latitude,
            (float) $previous->longitude,
            $current['lat'],
            $current['lon'],
        );
        $minDistance = (float) config('security.impossible_travel.min_distance_km', 300);
        if ($distance < $minDistance) {
            return null;
        }

        // Mindest-Zeitfenster: identische Zeitstempel würden gegen unendlich
        // laufen; eine Minute ist die kleinste sinnvolle Auflösung.
        $hours = max(1 / 60, abs(now()->diffInSeconds($previous->last_seen_at)) / 3600);
        $speed = $distance / $hours;
        $maxSpeed = (float) config('security.impossible_travel.max_speed_kmh', 900);
        if ($speed <= $maxSpeed) {
            return null;
        }

        $result = [
            'distance_km' => round($distance, 1),
            'hours' => round($hours, 2),
            'speed_kmh' => round($speed),
        ];

        // Datensparsam: nur Kennzahlen und grobe Ortslabels, keine Koordinaten.
        $this->security->log(SecurityEventType::ImpossibleTravel, [
            'user_id' => $user->id,
            'distance_km' => $result['distance_km'],
            'speed_kmh' => $result['speed_kmh'],
        ]);
        $this->notify($user, $ip, $previous, $result);

        return $result;
    }

    /** @param array{distance_km: float, hours: float, speed_kmh: float} $result */
    private function notify(User $user, ?string $ip, UserKnownDevice $previous, array $result): void {
        $params = [
            'from' => $previous->country ?? '—',
            'to' => $this->label($ip) ?? '—',
            'distance' => $result['distance_km'],
            'hours' => $result['hours'],
            'speed' => $result['speed_kmh'],
        ];

        $payload = [
            'title' => (string) __('notification.message.impossible_travel_title', $params),
            'title_key' => 'notification.message.impossible_travel_title',
            'title_params' => $params,
            'message' => (string) __('notification.message.impossible_travel_message', $params),
            'message_key' => 'notification.message.impossible_travel_message',
            'message_params' => $params,
            'url' => route('account.2fa.show'),
        ];

        $user->notify(new GenericEventNotification(NotificationEvent::SecurityNewDevice, $payload, ['database', 'mail']));

        $admins = User::query()->where('is_platform_admin', true)->whereKeyNot($user->id)->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new GenericEventNotification(
                NotificationEvent::SecurityThreat,
                [...$payload, 'url' => route('admin.security-events.index')],
                ['database', 'mail'],
            ));
        }
    }

    // Geo-Zugriffe als protected Test-Nähte (Muster MVP-448): der statische
    // Toolkit-Helper ist im Test nicht container-fakebar, eine .mmdb-Fixture
    // unverhältnismäßig.

    protected function geoAvailable(): bool {
        return IpLocationHelper::isAvailable();
    }

    /** @return array{lat: float, lon: float}|null */
    protected function coordinates(?string $ip): ?array {
        return IpLocationHelper::coordinates($ip);
    }

    /** Grobes Ortslabel „Stadt, Land" für die Benachrichtigung. */
    protected function label(?string $ip): ?string {
        $location = IpLocationHelper::lookup($ip);
        if ($location === null) {
            return null;
        }
        $parts = array_values(array_filter(
            [$location['city'], $location['country']],
            static fn(?string $value): bool => $value !== null && $value !== '',
        ));

        return $parts === [] ? null : implode(', ', $parts);
    }
}

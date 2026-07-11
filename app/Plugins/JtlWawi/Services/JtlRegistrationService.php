<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlRegistrationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Services;

use App\Models\JtlConnection;
use App\Plugins\JtlWawi\Api\JtlGatewayFactory;
use App\Plugins\JtlWawi\JtlWawiPlugin;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OnPremise-App-Registrierung (Feature 078, MVP-317):
 *
 * 1. Händler öffnet in JTL-Wawi „Admin > App-Registrierung“.
 * 2. {@see start()} sendet die App-Metadaten mit frisch erzeugtem
 *    Challenge-Code → `registrationRequestId`, Status `pending`.
 * 3. Händler bestätigt die App in der Wawi.
 * 4. {@see check()} fragt den Status ab; bei Freigabe kommt der API-Key
 *    EINMALIG zurück und wird sofort verschlüsselt gespeichert.
 *
 * Status-Enum der API ist numerisch [0,1,2]; die Zuordnung
 * pending/rejected/accepted ist Annahme aus der Doku — deshalb gilt
 * defensiv: ein nicht-leerer `token.apiKey` bedeutet IMMER `accepted`
 * (Abweichungsregister MVP-316).
 */
class JtlRegistrationService {
    private const STATUS_MAP = [
        0 => JtlConnection::REGISTRATION_PENDING,
        1 => JtlConnection::REGISTRATION_REJECTED,
        2 => JtlConnection::REGISTRATION_ACCEPTED,
    ];

    /** 1×1 transparentes PNG — die API-Doku führt appIcon als Registrierungsfeld. */
    private const APP_ICON = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function __construct(
        private readonly JtlGatewayFactory $gateways,
        private readonly JtlScopePreflight $preflight,
    ) {}

    /** Registrierung anstoßen; setzt Verbindung auf `pending_registration`. */
    public function start(JtlConnection $connection): void {
        if (! $connection->isOnPremise()) {
            throw new RuntimeException('JTL-Wawi: Die App-Registrierung gilt nur für die OnPremise-Betriebsart.');
        }

        $challengeCode = Str::lower(Str::random(24)); // ≤ 30 Zeichen laut Doku
        $connection->forceFill(['challenge_code' => $challengeCode])->save();

        $configKey = 'plugins.' . JtlWawiPlugin::ID;
        $result = $this->gateways->for($connection)->registerApp([
            'appId' => (string) config($configKey . '.app_id'),
            'displayName' => 'WorkDiary',
            'description' => 'WorkDiary bindet JTL-Wawi als führende Warenwirtschaft an (Artikel, Lager, Bestände).',
            'version' => (string) config('app.version', '1.0.0'),
            'providerName' => (string) config($configKey . '.provider_name'),
            'providerWebsite' => (string) config($configKey . '.provider_website'),
            'mandatoryApiScopes' => $this->preflight->mandatoryScopes(),
            'optionalApiScopes' => $this->preflight->optionalScopes(),
            'appIcon' => self::APP_ICON,
        ], $challengeCode);

        $registrationId = (string) ($result['registrationRequestId'] ?? '');
        if ($registrationId === '') {
            throw new RuntimeException('JTL-Wawi: Registrierungsantwort ohne registrationRequestId — ist „App-Registrierung“ in der Wawi geöffnet?');
        }

        $connection->forceFill([
            'registration_id' => $registrationId,
            'registration_status' => self::STATUS_MAP[(int) ($result['status'] ?? 0)] ?? JtlConnection::REGISTRATION_PENDING,
            'status' => JtlConnection::STATUS_PENDING_REGISTRATION,
            'last_error' => null,
        ])->save();
    }

    /**
     * Registrierungsstatus abholen; bei Freigabe API-Key + Scopes speichern
     * und Scope-Preflight anwenden. Gibt den Registrierungsstatus zurück.
     */
    public function check(JtlConnection $connection): string {
        if ($connection->registration_id === null || trim((string) $connection->challenge_code) === '') {
            throw new RuntimeException('JTL-Wawi: Keine laufende Registrierung — zuerst die Registrierung starten.');
        }

        $result = $this->gateways->for($connection)->fetchRegistration(
            $connection->registration_id,
            (string) $connection->challenge_code,
        );

        $apiKey = trim((string) data_get($result, 'token.apiKey', ''));
        $status = self::STATUS_MAP[(int) data_get($result, 'requestStatusInfo.status', 0)] ?? JtlConnection::REGISTRATION_PENDING;
        if ($apiKey !== '') {
            $status = JtlConnection::REGISTRATION_ACCEPTED;
        }

        if ($status === JtlConnection::REGISTRATION_ACCEPTED && $apiKey !== '') {
            $scopes = array_values(array_map('strval', (array) ($result['grantedScopes'] ?? [])));
            $connection->forceFill([
                'api_key' => $apiKey,
                'granted_scopes' => $scopes,
                'registration_status' => $status,
            ])->save();

            $this->applyScopePreflight($connection);
        } else {
            $connection->forceFill([
                'registration_status' => $status,
                'status' => $status === JtlConnection::REGISTRATION_REJECTED
                    ? JtlConnection::STATUS_BLOCKED
                    : JtlConnection::STATUS_PENDING_REGISTRATION,
                'blocked_reason' => $status === JtlConnection::REGISTRATION_REJECTED ? 'registration_rejected' : null,
            ])->save();
        }

        return $status;
    }

    /** Wendet den Scope-Preflight an: aktiv oder sichtbar blockiert — nie teilweise. */
    public function applyScopePreflight(JtlConnection $connection): void {
        $check = $this->preflight->check($connection);

        if ($check['ok'] || $check['unknown']) {
            $connection->forceFill([
                'status' => JtlConnection::STATUS_ACTIVE,
                'blocked_reason' => null,
            ])->save();

            return;
        }

        $connection->forceFill([
            'status' => JtlConnection::STATUS_BLOCKED,
            'blocked_reason' => 'missing_scopes',
        ])->save();
    }
}

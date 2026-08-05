<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainConnectionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Enums\Domain\{DomainCapabilityArea, DomainConnectionStatus};
use App\Models\Domain\DomainProviderConnection;
use App\Plugins\Support\Domain\{DomainCapabilityMatrix, DomainProviderException};
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Verbindungsprüfung und Fähigkeitserkennung des DomainReselling-Kontos
 * (Feature 083, MVP-385). `StatusUser` ohne `subuser`-Parameter (= eigenes
 * Konto) ist der Health-Ping; nur bei erfolgreichem, vollständigem Ergebnis
 * wird die Verbindung aktiv. `CheckAuthentication` eignet sich NICHT: es
 * prüft laut Handbuch das Passwort eines Subusers (Pflichtparameter
 * `subuser`+`password`). Die Fähigkeitsmatrix bleibt konservativ
 * (Rechnungen gesperrt), bis ein realer Pilot mehr belegt.
 */
class DomainConnectionService {
    public function __construct(private readonly DomainProviderResolver $resolver) {}

    /**
     * Prüft die Zugangsdaten und aktiviert die Verbindung bei Erfolg. Bei
     * Fehler wird der Health-Zustand redigiert gespeichert (Auto-Disable).
     */
    public function test(DomainProviderConnection $connection): bool {
        try {
            $adapter = $this->resolver->for($connection);
            $response = $adapter->execute('StatusUser', [], DomainCapabilityArea::Authentication);

            if (! $response->isSuccess()) {
                $connection->recordConnectionFailure('auth_code_' . $response->code);
                $connection->forceFill(['status' => DomainConnectionStatus::Blocked->value])->save();

                return false;
            }

            // Konservative Default-Matrix speichern (Rechnungen bleiben gesperrt).
            $connection->forceFill([
                'status' => DomainConnectionStatus::Active->value,
                'capabilities' => DomainCapabilityMatrix::default()->toArray(),
                'last_sync_at' => $connection->last_sync_at,
            ])->save();
            $connection->recordConnectionSuccess();

            return true;
        } catch (DomainProviderException $e) {
            $connection->recordConnectionFailure($e->incomplete ? 'incomplete' : 'transport');
            $connection->forceFill(['status' => DomainConnectionStatus::Blocked->value])->save();

            return false;
        } catch (Throwable $e) {
            $connection->recordConnectionFailure(class_basename($e));

            return false;
        }
    }

    /** Bestätigt einen bestandenen realen Pilot (hebt „Pilot offen" auf). */
    public function confirmPilot(DomainProviderConnection $connection): void {
        $connection->forceFill(['pilot_confirmed_at' => Carbon::now()])->save();
    }
}

<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerDispatchProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Models\Organization;
use App\Models\Passenger\PassengerRide;

/**
 * Externe Fahrtvermittlung (MVP-456, Konzept §9): Annahme und Statusabgleich
 * vermittelter Aufträge.
 *
 * Leitplanken:
 *  - Idempotenz über die Vermittlungsreferenz (`passenger_rides` hält dazu
 *    den Unique-Index organization/mediator_plugin/mediator_reference) plus
 *    `payload_hash` des Originalpayloads.
 *  - Provider dürfen keine Fahrt, Zahlung oder Kassenbuchung still
 *    überschreiben; Konflikte und unklare Zuordnungen landen als Vorschlag in
 *    der Integrations-Inbox (Feature 053), nie als Blind-Write.
 */
interface PassengerDispatchProvider {
    /**
     * Offene/aktualisierte Vermittlungsaufträge seit dem Aufholpunkt.
     *
     * @return list<array{
     *     reference: string,
     *     operation_mode: string,
     *     requested_at: string,
     *     pickup_address: string,
     *     destination_address: string|null,
     *     window_start: string|null,
     *     window_end: string|null,
     *     passenger_count: int,
     *     requirements: array<string, mixed>,
     *     payload_hash: string
     * }>
     */
    public function dispatchOrders(Organization $organization, ?string $since = null): array;

    /** Statusmeldung an den Vermittler (angenommen/disponiert/abgeschlossen …). */
    public function pushRideStatus(Organization $organization, PassengerRide $ride): void;
}

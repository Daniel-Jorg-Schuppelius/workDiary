<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FareMeterProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Models\Organization;

/**
 * Taxameter-/Wegstreckenzähler-Import (MVP-456, Konzept §9).
 *
 * Leitplanken:
 *  - Gerätedaten werden UNVERÄNDERLICH importiert: Geräte-, Tarif-,
 *    Transaktions-, Prüfwert- und Sicherheitsmodulreferenzen bleiben, wie
 *    geliefert; workDiary rechnet nicht um und erhebt keine
 *    Konformitätsbehauptung (kein Taxameter-/TSE-Ersatz).
 *  - Idempotenz über die `transaction_ref` des Geräts/Providers; eine
 *    Wiederanlieferung darf keine Fahrt, Zahlung oder Kassenbuchung
 *    überschreiben. Unklare Zuordnungen gehören in die Integrations-Inbox.
 *  - App-basierte Systeme mit eigener Taxameterfunktion sind nur über einen
 *    ausdrücklich zertifizierten Provider zulässig.
 */
interface FareMeterProvider {
    /**
     * Neue Geräte-Transaktionen seit dem Aufholpunkt.
     *
     * @return list<array{
     *     transaction_ref: string,
     *     device_serial: string,
     *     device_kind: string,
     *     tariff_ref: string|null,
     *     started_at: string,
     *     ended_at: string,
     *     distance_km: string|null,
     *     waiting_seconds: int|null,
     *     net_amount: string,
     *     gross_amount: string|null,
     *     currency: string,
     *     payment_method: string|null,
     *     signature_ref: string|null,
     *     tse_reference: string|null,
     *     payload_hash: string
     * }>
     */
    public function meterTransactions(Organization $organization, ?string $since = null): array;
}

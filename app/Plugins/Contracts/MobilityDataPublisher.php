<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MobilityDataPublisher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Models\Organization;

/**
 * Mobilitätsdaten-Bereitstellung nach § 3a PBefG / MDV (MVP-456, Konzept §9):
 * statische und dynamische Daten über den vorgeschriebenen Zugangspunkt.
 *
 * Leitplanken:
 *  - Ob eine Organisation verpflichtet ist (bzw. die
 *    Einzelunternehmer-Ausnahme greift), wird KONFIGURIERT
 *    (`config('passenger.mobility_data.obligated')`), nie geraten.
 *  - Veröffentlichte Daten enthalten keine Fahrgastidentität und keine
 *    individuelle Fahrthistorie über den gesetzlich erforderlichen Umfang
 *    hinaus (Konzept §11).
 */
interface MobilityDataPublisher {
    /**
     * Statische Unternehmens-/Angebotsdaten (Betriebsarten, Bediengebiet,
     * Barrierefreiheit, Flottenmerkmale) veröffentlichen.
     *
     * @param  array<string, mixed>  $payload
     * @return string Referenz/Quittung des Zugangspunkts
     */
    public function publishStatic(Organization $organization, array $payload): string;

    /**
     * Dynamische Daten (Verfügbarkeit) veröffentlichen — aggregiert, ohne
     * Fahrgastbezug.
     *
     * @param  array<string, mixed>  $payload
     * @return string Referenz/Quittung des Zugangspunkts
     */
    public function publishDynamic(Organization $organization, array $payload): string;
}

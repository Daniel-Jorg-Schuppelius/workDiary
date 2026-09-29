<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalPurchaseSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Services\Billing\Dto\{ExternalCategorySpend, ExternalPurchase};
use Carbon\CarbonInterface;

/**
 * Einkaufsbelege mit Lieferantenbezug aus dem Buchhaltungsprogramm (MVP-1036)
 * für Lieferantenwert und -analyse. Nur gültige Ausgabebelege: keine
 * Entwürfe, keine Stornos, nicht archiviert.
 */
interface ExternalPurchaseSource {
    public function key(): string;

    /**
     * @param  list<int>|null  $supplierIds  null = alle Lieferanten
     * @return list<ExternalPurchase> ohne Zeitraum: der ganze Bestand
     */
    public function purchases(?CarbonInterface $from, ?CarbonInterface $to, ?array $supplierIds = null): array;

    /**
     * Ausgaben je Buchungskategorie; `pending` zählt Belege, deren
     * Kategorien noch nicht geladen sind.
     *
     * @return array{rows: list<ExternalCategorySpend>, pending: int}
     */
    public function categorySpend(CarbonInterface $from, CarbonInterface $to): array;
}

<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceLineSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Contracts;

use App\Services\Reselling\Marketplace\InvoiceLine;
use Carbon\CarbonImmutable;

/**
 * Quelle der eigenen Ausgangsrechnungen für den Reselling-Abgleich. Heute
 * Lexoffice; ein späteres orgaMAX/sevDesk/lokales Fakturamodul hängt sich hier
 * ein, ohne dass der Abgleich davon weiß.
 */
interface InvoiceLineSource {
    /**
     * Prüft den Zugang zur Quelle, bevor irgendetwas gelesen wird; wirft bei
     * abgelehntem Schlüssel oder unerreichbarem Dienst. Ohne diese Vorprüfung
     * sähe ein 401 im Bericht wie „alle Perioden fehlen" aus.
     *
     * @throws \Throwable
     */
    public function verifyAccess(): void;

    /**
     * Alle nicht stornierten Rechnungspositionen eines Kontakts im Datumsbereich.
     *
     * @return list<InvoiceLine>
     */
    public function linesForContact(string $externalContactId, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * @return list<array{id: string, name: string}>
     */
    public function findContactsByName(string $name): array;

    /**
     * @return list<array{id: string, name: string}>
     */
    public function findContactsByNumber(string $number): array;
}

<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EbicsGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Ebics\Contracts;

use App\Enums\Finance\PaymentRunKind;
use App\Models\Finance\EbicsConnection;
use App\Services\Finance\Ebics\Exceptions\EbicsException;
use Carbon\CarbonImmutable;

/**
 * Protokollschicht EBICS 3.0 (MVP-124). Die Implementierung hält den
 * Schlüsselbund am Zugang aktuell; Abläufe und Status liegen in den Diensten.
 * Fehler der Bank kommen als {@see EbicsException} mit EBICS-Rückgabecode.
 */
interface EbicsGateway {
    /** Signatur-, Authentifikations- und Verschlüsselungsschlüssel samt Zertifikaten erzeugen. */
    public function createKeys(EbicsConnection $connection): void;

    /** Öffentliche Schlüssel an die Bank senden (INI und HIA). */
    public function sendInitialization(EbicsConnection $connection): void;

    /**
     * Daten des Initialisierungsbriefs (INI/HIA): je Schlüssel Art, Version und Hashwert.
     *
     * @return list<array{type: string, version: string, hash: string, certificate_created_at: ?string}>
     */
    public function letterKeys(EbicsConnection $connection): array;

    /** Öffentliche Schlüssel der Bank abrufen (HPB) — gelingt erst nach der Freischaltung. */
    public function fetchBankKeys(EbicsConnection $connection): void;

    /**
     * Tagesauszüge (camt.053) des Zeitraums abrufen.
     *
     * @return list<string> camt.053-Dokumente; leer, wenn die Bank nichts bereithält
     */
    public function downloadStatements(EbicsConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array;

    /** Zahllauf (pain.001 bzw. pain.008) einreichen; liefert die Auftragsnummer der Bank. */
    public function uploadPayment(EbicsConnection $connection, PaymentRunKind $kind, string $xml, string $fileName): string;

    /** Zugang bei der Bank sperren (SPR). */
    public function suspend(EbicsConnection $connection): void;
}

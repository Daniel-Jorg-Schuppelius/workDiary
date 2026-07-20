<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchemaReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane\Schema;

use DateTimeImmutable;

/**
 * Nur-lesender Zugriff auf ein InvoicePlane-Schema (Feature 086, MVP-419).
 *
 * Bewusst als Interface: der reale Adapter liest über eine getrennte,
 * verschlüsselte `SELECT`-Verbindung (parametrisierte Queries, Query-Budget) —
 * er hängt an einer echten InvoicePlane-Instanz. Der {@see InvoicePlanePreflight}
 * arbeitet ausschließlich gegen dieses Interface und ist damit ohne
 * Pilotinstanz testbar.
 */
interface SchemaReader {
    /** Roh-Versionsstring aus InvoicePlane (z. B. `ip_versions`); null = unbekannt. */
    public function version(): ?string;

    /** Tabellenpräfix der Instanz (Default `ip_`). */
    public function tablePrefix(): string;

    /** Zeichensatz der Verbindung/Datenbank (z. B. `utf8mb4`); null = unbekannt. */
    public function charset(): ?string;

    /** Serverzeit der InvoicePlane-DB (für die Zeitdifferenzprüfung); null = unbekannt. */
    public function serverTime(): ?DateTimeImmutable;

    /**
     * Spaltennamen einer Tabelle (OHNE Präfix). Leer = Tabelle fehlt.
     *
     * @return list<string>
     */
    public function columnsOf(string $tableWithoutPrefix): array;
}

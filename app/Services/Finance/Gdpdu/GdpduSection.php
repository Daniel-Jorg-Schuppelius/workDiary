<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GdpduSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\Organization;
use Carbon\CarbonInterface;

/**
 * Ein Datenbereich des GoBD-Z3-Pakets (Vollscan 2026-08-23, B13): liefert die
 * Tabellenbeschreibung für die `index.xml` und die CSV-Datenzeilen. Zeilen
 * MÜSSEN deterministisch geordnet sein — derselbe Zeitraum muss byte-gleiche
 * Dateien und damit denselben Paket-Hash ergeben.
 */
interface GdpduSection {
    /** Stabiler Bereichsschlüssel (Auswahl + Nachweis, z. B. `invoices`). */
    public function key(): string;

    /**
     * Tabellen-/Spaltenbeschreibung im GDPdU-Beschreibungsstandard.
     *
     * @return array{file: string, name: string, description: string, columns: list<array{name: string, type: string, accuracy?: int}>}
     */
    public function definition(): array;

    /**
     * CSV-Datenzeilen des Prüfungszeitraums (deterministisch geordnet).
     *
     * Als Generator/LazyCollection: das Z3-Paket eines Prüfungszeitraums
     * kann Hunderttausende Zeilen umfassen — sie alle im Speicher zu halten,
     * bevor die erste CSV-Zeile geschrieben ist, war der Grund für den
     * Speicher-Brennpunkt (Vollscan 2026-08-23, A16).
     *
     * @return iterable<int, list<string>>
     */
    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): iterable;
}

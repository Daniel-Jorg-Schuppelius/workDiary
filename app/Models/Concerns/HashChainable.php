<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HashChainable.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Vertrag fuer revisionssichere, hash-verkettete Audit-Modelle ({@see HashChained}).
 * Erlaubt es Konsumenten (z. B. audit:verify) ueber den Klassennamen typsicher
 * zu rechnen, ohne auf das generische Eloquent-Model zu fallen.
 */
interface HashChainable {
    /** @return array<string, mixed> Nutzdaten dieser Zeile (feste Reihenfolge). */
    public function hashPayload(): array;

    /**
     * Name der Kette, zu der diese Zeile gehoert (`tabelle:organisation`).
     * Verifikation und Umkettung muessen dieselbe Aufteilung sehen wie der
     * Schreibpfad — deshalb Teil des Vertrags (MVP-722).
     */
    public function chainName(): string;

    /** @param array<string, mixed> $data */
    public static function chainHash(?string $prevHash, array $data): string;

    /**
     * Hydriert ein Modell aus einer Roh-DB-Zeile (fuer Verifikation/Backfill).
     *
     * @param array<string, mixed> $attributes
     */
    public static function fromStorageRow(array $attributes): static;
}

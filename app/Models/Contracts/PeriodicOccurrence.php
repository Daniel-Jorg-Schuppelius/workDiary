<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodicOccurrence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contracts;

use App\Support\Period\PeriodKey;

/**
 * Vorkommen einer wiederkehrenden Reihe (Feature 201, MVP-1645): Träger der
 * Merker für die nächste Periode. Ein Modell implementiert diesen Vertrag und
 * bindet `<x-carry-forward-notes :subject="$model" />` in seine Detailseite ein;
 * mehr braucht ein Träger nicht. Lesen und Schreiben der Merker folgt den
 * Rechten am Träger (`view` bzw. `update` seiner Policy), die Organisation kommt
 * aus `organization_id` des Modells.
 *
 * Der Reihenschlüssel kommt vom Träger und bleibt stabil, solange die Reihe
 * dieselbe ist (Auftrag: `order:<kunde>:<eintragsart>:<produktgruppe>`); höchstens
 * 191 Zeichen. Gehört ein Datensatz zu keiner Reihe (etwa ein Auftrag ohne
 * Periode), liefern beide Methoden null — die Komponente zeigt dann nichts.
 */
interface PeriodicOccurrence {
    /** Stabiler Schlüssel der Reihe; null = kein Vorkommen einer Reihe. */
    public function carryForwardSeries(): ?string;

    /** Periode dieses Vorkommens; null = kein Vorkommen einer Reihe. */
    public function carryForwardPeriod(): ?PeriodKey;
}

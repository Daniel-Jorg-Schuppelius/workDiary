<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AcceptsInboxAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Bezugsdatensatz eines Eingangsfalls, der die Zuordnung selbst übernimmt
 * (MVP-1610): Ordnet der Integrations-Eingang einen Fall einem Kunden zu oder
 * legt ihn an, erfährt es der referenzierte Datensatz — etwa die
 * Terminanfrage, die damit ihren Kunden bekommt.
 */
interface AcceptsInboxAssignment {
    public function acceptInboxAssignment(Model $target): void;
}

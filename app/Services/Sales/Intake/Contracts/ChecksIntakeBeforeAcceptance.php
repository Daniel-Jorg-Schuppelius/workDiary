<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChecksIntakeBeforeAcceptance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\Contracts;

use App\Models\Sales\LeadIntake;
use App\Services\Fields\FieldValues;

/**
 * Eigene Prüfung eines Prüfpakets vor der Annahme (Feature 200, MVP-1641),
 * zusätzlich zu den Pflichtfeldern des Feldschemas — etwa „politisch
 * exponierte Person verlangt hohe Risikostufe“ im GwG-Paket.
 */
interface ChecksIntakeBeforeAcceptance {
    /**
     * @param  FieldValues  $values  Werte des Pakets, Schlüssel ohne Präfix
     * @return array<string, string> Feldschlüssel ohne Präfix → Meldung
     */
    public function checkBeforeAcceptance(FieldValues $values, LeadIntake $intake): array;
}

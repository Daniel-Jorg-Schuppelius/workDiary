<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SectionContext.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation;

/**
 * Laufzeit-Optionen eines Builder-Laufs: die Hash-Ketten werden nur beim
 * Veröffentlichen vollständig nachgerechnet (Vorschau bleibt schnell).
 */
final class SectionContext {
    public function __construct(public readonly bool $verifyChains = false) {}
}

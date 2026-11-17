<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeadlineSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Deadlines\Dto;

use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;

/**
 * Eine berechnete Kundenfrist (Feature 197): Fristart, Periode und Termin.
 * `regionAssumed` = das Bundesland (Finanzamt, Einzugsstelle) fehlte, gerechnet
 * wurde mit der Region der Organisation.
 */
final readonly class DeadlineSpec {
    public function __construct(
        public string $kind,
        public PeriodKey $period,
        public CarbonImmutable $dueOn,
        public bool $regionAssumed = false,
    ) {}

    /** Schlüssel innerhalb eines Regelsatzes und Kunden: Fristart und Periode. */
    public function key(): string {
        return $this->kind . '|' . $this->period->key();
    }
}

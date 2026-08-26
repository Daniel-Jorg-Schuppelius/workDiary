<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\Sales\CommissionAssignmentSource;
use App\Models\{Lead, User};

/**
 * Ergebnis der Zuordnung Beleg → Vertriebsperson (Feature 146): wer bekommt
 * die Provision, und warum. Der Lead bleibt am Ergebnis haengen, damit die
 * Regelauswahl seine Quelle (`LeadSource`) lesen kann, ohne ihn erneut zu
 * suchen.
 */
final readonly class CommissionAssignment {
    public function __construct(
        public User $user,
        public CommissionAssignmentSource $source,
        public ?Lead $lead = null,
    ) {}
}

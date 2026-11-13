<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActsOnIntakeAcceptance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\Contracts;

use App\Models\Platform\User;
use App\Models\Sales\LeadIntake;

/**
 * Folge der Annahme für ein Prüfpaket (Feature 200): Das GwG-Paket legt den
 * Vermerk `STB_GELDWAESCHE_VERMERK` an (MVP-1642). Läuft in der Transaktion
 * der Entscheidung, nach dem zweiten Blick, falls die Organisation ihn verlangt.
 */
interface ActsOnIntakeAcceptance {
    public function accepted(LeadIntake $intake, User $actor): void;
}

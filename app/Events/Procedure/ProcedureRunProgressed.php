<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRunProgressed.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Procedure;

use App\Models\Platform\User;
use App\Models\Procedure\ProcedureRun;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Am Lauf hat sich etwas geändert, das ihn sperren oder freigeben kann
 * (Schritt, Wartezeit, Zweitperson, Abweichung, Abschluss). Synchron — der
 * Sperrstatus gehört zum Stand des Laufs (MVP-897).
 */
final class ProcedureRunProgressed {
    use Dispatchable;

    public function __construct(public readonly ProcedureRun $run, public readonly ?User $actor = null) {}
}

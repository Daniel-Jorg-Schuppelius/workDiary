<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeSourceConnector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Support\Ui\UiAction;

/** Verbinden einer Quelle des Cloud-Belegeingangs in der Verwaltung (MVP-1041). */
interface IntakeSourceConnector {
    /** Eintrag im Menü „Neu“ des Cloud-Belegeingangs. */
    public function intakeConnectAction(): UiAction;
}

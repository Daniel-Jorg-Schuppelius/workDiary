<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeConnectionsExist.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling\Guards;

use App\Models\CloudIntake\CloudDocumentConnection;
use App\Scheduling\Contracts\JobGuard;

/** Cloud-Dokumenteingang (cloud-intake:wake): nur mit mindestens einer Verbindung. */
final class CloudIntakeConnectionsExist implements JobGuard {
    public function shouldRun(): bool {
        return CloudDocumentConnection::query()->withoutGlobalScopes()->exists();
    }
}

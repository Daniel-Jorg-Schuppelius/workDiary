<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailboxesConfigured.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling\Guards;

use App\Models\Mail\EmailConnection;
use App\Scheduling\Contracts\JobGuard;

/** E-Mail-Abruf (mail:poll): nur, wenn irgendeine Organisation ein Postfach eingerichtet hat. */
final class MailboxesConfigured implements JobGuard {
    public function shouldRun(): bool {
        return EmailConnection::query()->withoutGlobalScopes()->exists();
    }
}

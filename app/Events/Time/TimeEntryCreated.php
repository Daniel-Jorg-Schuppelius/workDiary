<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryCreated.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Time;

use App\Models\Time\TimeEntry;
use Illuminate\Foundation\Events\Dispatchable;

/** Zeiteintrag angelegt — der Auftrag zieht seinen Status nach (synchron, wie der frühere Observer). */
final class TimeEntryCreated {
    use Dispatchable;

    public function __construct(public readonly TimeEntry $entry) {}
}

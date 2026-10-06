<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistConnectionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Todoist\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Stand der Todoist-Verbindung einer Organisation (MVP-111). */
enum TodoistConnectionStatus: string implements HasLabel {
    use HasOptions;

    case Active = 'active';

    /** Im Schema vorgesehen, heute von keinem Code geschrieben. */
    case Paused = 'paused';
    case Disconnected = 'disconnected';

    public function label(): string {
        return (string) __('todoist::todoist.status.' . $this->value);
    }
}

<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistProjectLinkStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Todoist\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Stand einer Todoist-Projektzuordnung (MVP-112): nur aktive Zuordnungen werden synchronisiert. */
enum TodoistProjectLinkStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';

    public function label(): string {
        return (string) __('todoist::todoist.link_status.' . $this->value);
    }
}

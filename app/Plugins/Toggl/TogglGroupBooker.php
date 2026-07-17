<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglGroupBooker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Toggl;

use App\Plugins\Support\MatchingTimeGroupBooker;

/**
 * Bindet die gruppierten Toggl-Zeit-Import-Einträge an die universelle
 * Zuordnungs-Inbox (gemeinsame Mechanik: {@see MatchingTimeGroupBooker}).
 */
class TogglGroupBooker extends MatchingTimeGroupBooker {
    public function __construct(TogglImportService $service) {
        parent::__construct($service);
    }

    protected function bookerPluginId(): string {
        return TogglPlugin::ID;
    }
}

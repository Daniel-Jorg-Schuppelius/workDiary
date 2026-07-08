<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyGroupBooker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Clockify;

use App\Plugins\Support\MatchingTimeGroupBooker;

/**
 * Bindet die gruppierten Clockify-Zeit-Import-Einträge an die universelle
 * Zuordnungs-Inbox (gemeinsame Mechanik: {@see MatchingTimeGroupBooker}).
 */
class ClockifyGroupBooker extends MatchingTimeGroupBooker {
    public function __construct(ClockifyImportService $service) {
        parent::__construct($service);
    }

    protected function bookerPluginId(): string {
        return ClockifyPlugin::ID;
    }
}

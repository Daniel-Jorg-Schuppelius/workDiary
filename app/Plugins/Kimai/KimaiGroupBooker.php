<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiGroupBooker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kimai;

use App\Plugins\Support\MatchingTimeGroupBooker;

/**
 * Bindet die gruppierten Kimai-Zeit-Import-Einträge an die universelle
 * Zuordnungs-Inbox (gemeinsame Mechanik: {@see MatchingTimeGroupBooker}).
 */
class KimaiGroupBooker extends MatchingTimeGroupBooker {
    public function __construct(KimaiImportService $service) {
        parent::__construct($service);
    }

    protected function bookerPluginId(): string {
        return KimaiPlugin::ID;
    }
}

<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphSeriesGroupBooker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Services;

use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Support\Calendar\CalendarSeriesGroupBooker;

/** Serien aus dem Rückimport dieses Kalenders als Inbox-Gruppe (MVP-977). */
final class MsgraphSeriesGroupBooker extends CalendarSeriesGroupBooker {
    public function pluginId(): string {
        return MsgraphPlugin::ID;
    }
}

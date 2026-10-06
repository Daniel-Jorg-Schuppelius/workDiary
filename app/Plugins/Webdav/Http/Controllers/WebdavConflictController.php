<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavConflictController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Http\Controllers;

use App\Plugins\Support\Mirror\{MirrorConflictController, MirrorTarget};
use App\Plugins\Webdav\Services\WebdavMirrorTarget;
use App\Plugins\Webdav\WebdavPlugin;

/** Auflösung eines WebDAV-Spiegelkonflikts aus der Zuordnungs-Inbox. */
class WebdavConflictController extends MirrorConflictController {
    protected function target(): MirrorTarget {
        return new WebdavMirrorTarget();
    }

    protected function pluginId(): string {
        return WebdavPlugin::ID;
    }
}

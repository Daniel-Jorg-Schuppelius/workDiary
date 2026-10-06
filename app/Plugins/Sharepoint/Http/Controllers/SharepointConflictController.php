<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointConflictController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Sharepoint\Http\Controllers;

use App\Plugins\Sharepoint\Services\SharepointMirrorTarget;
use App\Plugins\Sharepoint\SharepointPlugin;
use App\Plugins\Support\Mirror\{MirrorConflictController, MirrorTarget};

/** Auflösung eines SharePoint-Spiegelkonflikts aus der Zuordnungs-Inbox. */
class SharepointConflictController extends MirrorConflictController {
    protected function target(): MirrorTarget {
        return new SharepointMirrorTarget();
    }

    protected function pluginId(): string {
        return SharepointPlugin::ID;
    }
}

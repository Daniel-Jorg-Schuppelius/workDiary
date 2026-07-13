<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavMirrorCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Console;

use App\Plugins\Support\Mirror\Console\MirrorBackfillCommand;
use App\Plugins\Support\Mirror\MirrorTarget;
use App\Plugins\Webdav\WebdavMirrorTarget;

/**
 * Voll-Spiegellauf der WebDAV-Ablage (Feature 058, MVP-127; Kern seit
 * A10/MVP-330 im gemeinsamen {@see MirrorBackfillCommand}): reiht alle
 * aktuell freigegebenen Dokumente je Organisation idempotent in die
 * Integrations-Outbox ein. Läuft manuell aus der Admin-UI.
 */
class WebdavMirrorCommand extends MirrorBackfillCommand {
    protected $signature = 'webdav:mirror
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Spiegelt alle freigegebenen Dokumente je Organisation in die WebDAV-Ablage (idempotent über die Outbox).';

    protected function target(): MirrorTarget {
        return new WebdavMirrorTarget();
    }
}

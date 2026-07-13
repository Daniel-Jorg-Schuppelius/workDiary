<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointMirrorCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Sharepoint\Console;

use App\Plugins\Sharepoint\SharepointMirrorTarget;
use App\Plugins\Support\Mirror\Console\MirrorBackfillCommand;
use App\Plugins\Support\Mirror\MirrorTarget;

/**
 * Voll-Spiegellauf der SharePoint-Ablage (MVP-330, Bauturbo A10; Kern im
 * gemeinsamen {@see MirrorBackfillCommand}): reiht alle aktuell freigegebenen
 * Dokumente je Organisation idempotent in die Integrations-Outbox ein.
 * Läuft manuell aus der Admin-UI (bewusst kein Scheduler-Registry-Eintrag,
 * wie der WebDAV-Bestand).
 */
class SharepointMirrorCommand extends MirrorBackfillCommand {
    protected $signature = 'sharepoint:mirror
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Spiegelt alle freigegebenen Dokumente je Organisation in die SharePoint-Bibliothek (idempotent über die Outbox).';

    protected function target(): MirrorTarget {
        return new SharepointMirrorTarget();
    }
}

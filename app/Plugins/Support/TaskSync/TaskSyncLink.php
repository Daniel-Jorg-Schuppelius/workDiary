<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskSyncLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

/**
 * Naht des Aufgaben-Sync-Kerns ({@see AbstractTaskSyncService}) zu den
 * Link-Modellen (TodoistProjectLink, MsgraphTaskListLink): der Kern braucht
 * nur den Mandanten-Scope — alles Provider-Spezifische läuft über Hooks.
 */
interface TaskSyncLink {
    /** Mandant der Zuordnung (Scope aller Referenz-/Inbox-Zugriffe). */
    public function organizationId(): int;
}

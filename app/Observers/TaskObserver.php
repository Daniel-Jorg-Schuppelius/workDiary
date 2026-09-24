<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Events\Project\TaskSaved;
use App\Models\Project\Task;

/** Aufgabe gespeichert → Domain-Event; den Board-Sync übernimmt der Agile-Listener (MVP-863). */
class TaskObserver {
    public function saved(Task $task): void {
        TaskSaved::dispatch($task);
    }
}

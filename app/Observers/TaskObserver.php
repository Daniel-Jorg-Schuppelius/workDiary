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

use App\Models\Task;
use App\Services\Agile\AgileBoardService;

/**
 * Task→Board-Sync (Feature 064, P3; B4 aus dem Provider gezogen): Logik in
 * {@see AgileBoardService::syncColumnFromTask()}.
 */
class TaskObserver {
    public function __construct(private readonly AgileBoardService $agileBoard) {}

    public function saved(Task $task): void {
        $this->agileBoard->syncColumnFromTask($task);
    }
}

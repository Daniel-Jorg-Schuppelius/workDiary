<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncBoardColumnFromTask.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Agile;

use App\Events\Project\TaskSaved;
use App\Listeners\ModuleListener;
use App\Services\Agile\AgileBoardService;

final class SyncBoardColumnFromTask extends ModuleListener {
    public function __construct(private readonly AgileBoardService $boards) {}

    protected function module(): string {
        return 'agile';
    }

    public function handle(TaskSaved $event): void {
        if (! $this->shouldHandle($event->task->organization_id)) {
            return;
        }
        $this->boards->syncColumnFromTask($event->task);
    }
}

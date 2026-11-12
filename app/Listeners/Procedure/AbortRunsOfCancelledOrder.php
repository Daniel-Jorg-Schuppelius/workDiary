<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbortRunsOfCancelledOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Procedure;

use App\Enums\Procedure\ProcedureRunStatus;
use App\Events\Diary\OrderCancelled;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Procedure\ProcedureRun;
use App\Services\Procedure\ProcedureExecutionService;
use App\Support\MorphMap;

/** Storno eines Auftrags (MVP-1596): laufende Prozedurläufe enden als abgebrochen. */
final class AbortRunsOfCancelledOrder {
    public function __construct(private readonly ProcedureExecutionService $procedures) {}

    public function handle(OrderCancelled $event): void {
        $actor = $event->actorUserId !== null ? User::query()->find($event->actorUserId) : null;
        if (! $actor instanceof User) {
            return;
        }

        $running = ProcedureRun::query()
            ->where('organization_id', $event->organizationId)
            ->where('subject_type', MorphMap::alias(DiaryEntry::class))
            ->where('subject_id', $event->diaryEntryId)
            ->whereIn('status', [ProcedureRunStatus::Open->value, ProcedureRunStatus::InProgress->value, ProcedureRunStatus::Blocked->value])
            ->get();

        foreach ($running as $run) {
            $this->procedures->abort($run, $actor, (string) __('diary.lifecycle.cancel.run_abort_reason', ['reason' => $event->reason]));
        }
    }
}

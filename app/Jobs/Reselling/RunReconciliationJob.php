<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RunReconciliationJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Reselling;

use App\Enums\Reselling\ReconciliationRunStatus;
use App\Models\Reselling\ReconciliationRun;
use App\Services\Reselling\Marketplace\ReconciliationRunner;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Hintergrundlauf des Lizenz-Reselling-Abgleichs (Feature 151). Lexoffice wird
 * mit Drosselung gelesen — bei 30 Kunden dauert das Minuten, deshalb nie im
 * Request. Standard-Queue, damit der vorhandene Worker ihn abholt.
 */
class RunReconciliationJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly int $runId) {}

    public function handle(ReconciliationRunner $runner): void {
        $run = ReconciliationRun::query()->withoutGlobalScopes()->find($this->runId);
        if (! $run instanceof ReconciliationRun || $run->status !== ReconciliationRunStatus::Queued) {
            return;
        }

        $runner->run($run);
    }

    public function failed(?Throwable $exception): void {
        $run = ReconciliationRun::query()->withoutGlobalScopes()->find($this->runId);
        if (! $run instanceof ReconciliationRun || $run->status->isFinished()) {
            return;
        }

        $run->status = ReconciliationRunStatus::Failed;
        $run->error = $exception?->getMessage() ?: 'Lauf abgebrochen (Worker-Ausfall oder Zeitüberschreitung).';
        $run->finished_at = CarbonImmutable::now();
        $run->save();
    }
}

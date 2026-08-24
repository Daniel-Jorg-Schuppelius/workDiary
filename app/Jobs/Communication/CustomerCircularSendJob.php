<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerCircularSendJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Jobs\Communication;

use App\Jobs\Concerns\RetriesTransientFailures;
use App\Models\Communication\CustomerCircular;
use App\Models\User;
use App\Services\Communication\CustomerCircularService;
use App\Support\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUniqueUntilProcessing, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Versand eines Kundenrundschreibens in der Queue (Vollscan 2026-08-23,
 * A3/J4): vorher lief die SMTP-Schleife über alle Empfänger im HTTP-Request
 * — bei wenigen hundert Kunden ein PHP-Timeout und ein halb versendetes
 * Rundschreiben, das als `sending` hängen blieb. Der Job ist je Rundschreiben
 * eindeutig, solange er wartet (ShouldBeUniqueUntilProcessing — so kann er sich
 * für den nächsten Batch selbst weiterreichen), und idempotent: bereits
 * zugestellte/übersprungene Empfänger werden nicht erneut angeschrieben.
 */
class CustomerCircularSendJob implements ShouldBeUniqueUntilProcessing, ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RetriesTransientFailures;
    use SerializesModels;

    public function __construct(
        public readonly int $circularId,
        public readonly int $actorId,
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string {
        return (string) $this->circularId;
    }

    public function handle(CustomerCircularService $circulars): void {
        $circular = CustomerCircular::query()->withoutGlobalScopes()->find($this->circularId);
        $actor = User::query()->withoutGlobalScopes()->find($this->actorId);
        if (! $circular instanceof CustomerCircular || ! $actor instanceof User || $circular->organization === null) {
            return;
        }

        // Je Lauf ein Batch (circular.batch_size) — so bleibt jeder Lauf unter
        // dem Laufzeitbudget (5 min < retry_after 630 s der DB-Queue) und ein
        // großes Rundschreiben reicht sich selbst weiter, statt als ein langer
        // Job von einem zweiten Worker doppelt angefasst zu werden.
        $complete = OrganizationContext::run($circular->organization, fn (): bool => $circulars->deliver($circular, $actor));
        if (! $complete) {
            self::dispatch($this->circularId, $this->actorId);
        }
    }
}

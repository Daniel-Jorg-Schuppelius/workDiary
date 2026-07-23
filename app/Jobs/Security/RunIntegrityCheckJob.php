<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RunIntegrityCheckJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Security;

use App\Services\Release\CodeIntegrityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Integritätsprüflauf aus der Admin-UI (Feature 095, MVP-442):
 * derselbe Prüfpfad wie CLI/Scheduler ({@see CodeIntegrityService::runVerification}).
 */
class RunIntegrityCheckJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 600;

    public function handle(CodeIntegrityService $service): void {
        $service->runVerification('ui');
    }
}

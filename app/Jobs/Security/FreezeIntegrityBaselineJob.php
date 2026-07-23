<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreezeIntegrityBaselineJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Security;

use App\Models\User;
use App\Services\Release\CodeIntegrityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Lokales Baseline-Freeze aus der Admin-UI (Feature 095, MVP-442) mit
 * Nutzer-Provenienz; Sicherheitsnetz: nur Plattform-Admins (der Controller
 * prüft ebenfalls — Jobs sind aber auch direkt dispatchbar).
 */
class FreezeIntegrityBaselineJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 600;

    public function __construct(private readonly int $userId) {}

    public function handle(CodeIntegrityService $service): void {
        $creator = User::query()->whereKey($this->userId)->where('is_platform_admin', true)->first();
        if ($creator === null) {
            return;
        }
        $service->freeze('local', $creator);
    }
}

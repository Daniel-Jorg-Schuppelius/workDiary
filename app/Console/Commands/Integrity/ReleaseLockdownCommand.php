<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReleaseLockdownCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Integrity;

use App\Models\Platform\User;
use App\Services\Release\IntegrityLockdownService;
use Illuminate\Console\Command;

/**
 * Hebt den Integritäts-Lockdown ohne neue Baseline auf (Feature 097,
 * MVP-1612) — für den Plattformbetrieb, auditiert mit Grund und Person. Ein
 * Wartungsmodus aus anderem Anlass bleibt bestehen.
 */
class ReleaseLockdownCommand extends Command {
    protected $signature = 'integrity:release
        {--reason= : Grund der Aufhebung (Pflicht, steht im Audit-Log)}
        {--user= : E-Mail des auslösenden Plattform-Admins (optional)}';

    protected $description = 'Hebt den Integritäts-Lockdown auf (auditiert).';

    public function handle(IntegrityLockdownService $lockdown): int {
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('Bitte einen Grund angeben: --reason="…"');

            return self::FAILURE;
        }

        $actor = null;
        $email = $this->option('user');
        if (is_string($email) && $email !== '') {
            $actor = User::query()->withoutGlobalScopes()->where('email', $email)->where('is_platform_admin', true)->first();
            if ($actor === null) {
                $this->error('Kein Plattform-Admin mit dieser E-Mail gefunden: ' . $email);

                return self::FAILURE;
            }
        }

        if (! $lockdown->release($actor, 'manual: ' . $reason)) {
            $this->info((string) __('integrity.lockdown.not_engaged'));

            return self::SUCCESS;
        }

        $this->info((string) __('integrity.lockdown.released'));

        return self::SUCCESS;
    }
}

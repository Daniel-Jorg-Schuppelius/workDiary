<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LimitGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Licensing;

use App\Exceptions\LimitExceededException;
use App\Models\{AuditLog, Organization, User};

/**
 * Prüft Lizenz-Limits vor Create-Aktionen (MVP-047 §5).
 *
 * Diese Iteration deckt `max_users` ab — der häufigste Fall und der
 * einzige, der ein lizenzspezifisches Feld (`LicensePayload->maxUsers`)
 * direkt nutzt. `max_orgs` und `storage_quota_gb` aus der Spec folgen
 * separat, sobald `LicensePayload` die jeweiligen Felder hat.
 *
 * Wird die Lizenzprüfung nicht erzwungen (Dev/Test, siehe
 * {@see LicenseService::isEnforced()}), passiert nichts.
 */
class LimitGuard {
    public function __construct(private readonly LicenseService $licenses) {}

    /**
     * Wirft {@see LimitExceededException}, wenn das Nutzerlimit der
     * aktuellen Lizenz erreicht ist. Schreibt im Fehlerfall einen
     * `limit.exceeded`-Audit-Eintrag.
     */
    public function ensureCanCreateUser(Organization $organization, ?User $actor = null): void {
        if (! $this->licenses->isEnforced()) {
            return;
        }

        $payload = $this->licenses->current()->payload;
        $max = $payload?->maxUsers;
        if ($max === null || $max <= 0) {
            return;
        }

        $current = User::query()->count();
        if ($current < $max) {
            return;
        }

        $this->writeAudit($organization, $actor, 'max_users', $current, $max);

        throw new LimitExceededException(
            limit: 'max_users',
            current: $current,
            max: $max,
        );
    }

    private function writeAudit(Organization $organization, ?User $actor, string $limit, int $current, int $max): void {
        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor?->id,
            'event' => 'limit.exceeded',
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
            'changes' => [
                'limit' => $limit,
                'current' => $current,
                'max' => $max,
            ],
        ]);
    }
}

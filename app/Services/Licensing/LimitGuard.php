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
use App\Models\{Attachment, AuditLog, Organization, User};

/**
 * Prüft Lizenz-Limits vor Create-Aktionen (MVP-047 §5).
 *
 * Deckt aktuell `max_users`, `max_orgs` und `storage_quota_gb` ab.
 * Wird die Lizenzprüfung nicht erzwungen (Dev/Test, siehe
 * {@see LicenseService::isEnforced()}), passiert nichts.
 */
class LimitGuard {
    public function __construct(private readonly LicenseService $licenses) {}

    /**
     * Wirft {@see LimitExceededException}, wenn das Nutzerlimit der Lizenz
     * für diese Organisation erreicht ist. Schreibt im Fehlerfall einen
     * `limit.exceeded`-Audit-Eintrag.
     *
     * Maßgeblich ist die org-gebundene Lizenz (`organizations.license_key`),
     * sofern vorhanden; sonst die globale Installations-Lizenz. Gezählt werden
     * die aktiven Nutzer der Organisation gegen `max_users`. Fehlt ein Limit
     * (unbegrenzt/Enterprise), passiert nichts.
     */
    public function ensureCanCreateUser(Organization $organization, ?User $actor = null): void {
        if (! $this->licenses->isEnforced()) {
            return;
        }

        $payload = $this->licenseFor($organization)->payload;
        $max = $payload?->maxUsers;
        if ($max === null || $max <= 0) {
            return;
        }

        $current = $organization->activeUserCount();
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

    /**
     * Org-gebundene Lizenz, falls die Organisation einen eigenen Schlüssel
     * trägt; andernfalls die globale Installations-Lizenz als Fallback.
     */
    private function licenseFor(Organization $organization): \App\Services\Licensing\LicenseResult {
        $orgKey = $organization->license_key;
        if (is_string($orgKey) && trim($orgKey) !== '') {
            $orgLicense = $this->licenses->forOrganization($organization);
            if ($orgLicense->isUsable()) {
                return $orgLicense;
            }
        }

        return $this->licenses->current();
    }

    /**
     * Wirft {@see LimitExceededException}, wenn das Limit der maximal
     * zulässigen Organisationen (`max_orgs`) erreicht ist. Audit-Kontext
     * ist die zuletzt bekannte Organisation (für Filterbarkeit), der
     * Limit-Typ ist `max_orgs`.
     */
    public function ensureCanCreateOrganization(?Organization $context = null, ?User $actor = null): void {
        if (! $this->licenses->isEnforced()) {
            return;
        }

        $payload = $this->licenses->current()->payload;
        $max = $payload?->maxOrgs;
        if ($max === null || $max <= 0) {
            return;
        }

        $current = Organization::query()->count();
        if ($current < $max) {
            return;
        }

        if ($context !== null) {
            $this->writeAudit($context, $actor, 'max_orgs', $current, $max);
        }

        throw new LimitExceededException(
            limit: 'max_orgs',
            current: $current,
            max: $max,
        );
    }

    /**
     * Wirft {@see LimitExceededException}, wenn das Hochladen einer
     * weiteren Datei das Speicherkontingent (`storage_quota_gb`) der
     * Organisation überschreiten würde.
     */
    public function ensureCanStoreAttachment(Organization $organization, int $additionalBytes, ?User $actor = null): void {
        if (! $this->licenses->isEnforced()) {
            return;
        }
        if ($additionalBytes < 0) {
            $additionalBytes = 0;
        }

        $payload = $this->licenses->current()->payload;
        $quotaGb = $payload?->storageQuotaGb;
        if ($quotaGb === null || $quotaGb <= 0) {
            return;
        }

        $quotaBytes = $quotaGb * 1024 * 1024 * 1024;
        $current = (int) Attachment::query()
            ->where('organization_id', $organization->id)
            ->sum('size');

        if ($current + $additionalBytes <= $quotaBytes) {
            return;
        }

        $this->writeAudit($organization, $actor, 'storage_quota_gb', $current + $additionalBytes, $quotaBytes);

        throw new LimitExceededException(
            limit: 'storage_quota_gb',
            current: $current + $additionalBytes,
            max: $quotaBytes,
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

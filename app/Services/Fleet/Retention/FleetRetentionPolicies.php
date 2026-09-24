<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FleetRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fleet\Retention;

use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class FleetRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Führerscheinkontrollen (Vollaudit 2026-07, N24): nach Nachweisfrist
            // löschen — Vorschlag über den Review-Scan, keine Direktlöschung.
            new RetentionPolicy(
                area: 'driver_license_checks',
                modelClass: \App\Models\Fleet\DriverLicenseCheck::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Fleet\DriverLicenseCheck::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('checked_at', '<', $cutoff),
                purge: function (\App\Models\Fleet\DriverLicenseCheck $subject): void {
                    $subject->delete();
                },
            ),
        ];
    }
}

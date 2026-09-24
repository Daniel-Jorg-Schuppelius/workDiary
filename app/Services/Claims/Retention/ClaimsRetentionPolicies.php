<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimsRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims\Retention;

use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class ClaimsRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Reklamationsakten (Feature 072, MVP-256): abgeschlossene Fälle
            // nach Ablauf anonymisieren (Melder-PII), Kennzahlen bleiben.
            new RetentionPolicy(
                area: 'claims',
                modelClass: \App\Models\Claims\ClaimCase::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Claims\ClaimCase::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereNull('anonymized_at')
                    ->whereNotNull('closed_at')
                    ->where('closed_at', '<', $cutoff),
                purge: function (\App\Models\Claims\ClaimCase $subject): void {
                    $subject->forceFill([
                        'reporter_name' => null,
                        'reporter_email' => null,
                        'anonymized_at' => now(),
                    ])->save();
                },
            ),
        ];
    }
}

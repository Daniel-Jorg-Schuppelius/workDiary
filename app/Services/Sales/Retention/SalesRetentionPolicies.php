<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SalesRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Retention;

use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class SalesRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Leads (Feature 091, MVP-656): personenbezogene Daten ohne
            // Vertrag - nicht konvertierte Leads werden 6 Monate nach dem
            // letzten Kontakt anonymisiert (PII weg, Pipeline-Kennzahl bleibt).
            new RetentionPolicy(
                area: 'leads',
                modelClass: \App\Models\Sales\Lead::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Sales\Lead::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereNull('anonymized_at')
                    ->where('status', '!=', \App\Enums\Sales\LeadStatus::Converted->value)
                    ->whereNotNull('last_contact_at')
                    ->where('last_contact_at', '<=', now()->subMonths((int) config('sales.lead_retention_months', 6))),
                purge: function (\App\Models\Sales\Lead $subject): void {
                    app(\App\Services\Sales\LeadService::class)->anonymize($subject);
                },
            ),
        ];
    }
}

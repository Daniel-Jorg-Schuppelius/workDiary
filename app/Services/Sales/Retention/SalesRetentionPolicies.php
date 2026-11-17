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
            // Aufnahmeakten (Feature 200, MVP-1643): Frist je Akte aus der Regel
            // ihres Pakets (GwG: fünf Jahre ab Schluss des Kalenderjahres, in dem
            // die Geschäftsbeziehung endet, § 8 Abs. 4 GwG). Vorher werden die
            // Fristen nachgeführt (Ende der Geschäftsbeziehung am Kunden); bis
            // dahin übersteht die Akte die Anonymisierung des Leads. Legal Hold
            // am Kunden sperrt über `customer_id`.
            new RetentionPolicy(
                area: 'lead_intakes',
                modelClass: \App\Models\Sales\LeadIntake::class,
                overdueQuery: function ($organization, $cutoff) {
                    app(\App\Services\Sales\Intake\LeadIntakeRetention::class)->refreshOrganization($organization);

                    return \App\Models\Sales\LeadIntake::query()
                        ->withoutGlobalScopes()
                        ->where('organization_id', $organization->id)
                        ->whereNotNull('retention_until')
                        ->where('retention_until', '<', \App\Support\Query\DateRange::day(\Carbon\CarbonImmutable::today()));
                },
                purge: function (\App\Models\Sales\LeadIntake $subject): void {
                    app(\App\Services\Sales\Intake\LeadIntakeRetention::class)->purge($subject);
                },
            ),
        ];
    }
}

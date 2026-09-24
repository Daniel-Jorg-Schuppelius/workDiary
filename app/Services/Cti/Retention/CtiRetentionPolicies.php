<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti\Retention;

use App\Models\Communication\CommunicationNote;
use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class CtiRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // CTI-Anrufmetadaten (Vollaudit 2026-07, M18): Rufnummer aus
            // Referenz-Payload und Notiz-Betreff anonymisieren; Richtung/
            // Zeitpunkt/Dauer bleiben als Vorgangsnachweis.
            new RetentionPolicy(
                area: 'cti_calls',
                modelClass: \App\Models\Integration\ExternalReference::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Integration\ExternalReference::query()
                    ->forPlugin($organization->id, \App\Services\Cti\CtiCallService::PLUGIN_ID, \App\Services\Cti\CtiCallService::EXTERNAL_TYPE)
                    ->where('synced_at', '<', $cutoff)
                    ->whereRaw("json_extract(payload, '$.anonymized') is null"),
                purge: function (\App\Models\Integration\ExternalReference $subject): void {
                    $payload = (array) $subject->payload;
                    unset($payload['number']);
                    $subject->forceFill(['payload' => [...$payload, 'anonymized' => true]])->save();
                    $note = $subject->referenceable;
                    if ($note instanceof CommunicationNote) {
                        $note->forceFill(['subject' => (string) __('Anruf (anonymisiert)')])->save();
                    }
                },
            ),
        ];
    }
}

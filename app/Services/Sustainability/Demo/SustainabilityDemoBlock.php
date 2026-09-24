<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sustainability\Demo;

use App\Models\Platform\{Organization, User};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/** Nachhaltigkeits-Vorführung. Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class SustainabilityDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'sustainability' => $this->seedSustainability($context->organization, $actor),
        ];
    }

    public function purge(Organization $organization): void {}

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    /** Demo Feature 071: E/S/G-Kriterien, Stromverbrauch + Gerätebewertung. */
    private function seedSustainability(Organization $organization, ?User $actor): int {
        if (! $this->moduleActive('module.sustainability')) {
            return 0;
        }
        if ($actor === null) {
            return 0;
        }

        try {
            foreach ([['environment', 'Energieeffizienz', 3], ['environment', 'Reparierbarkeit', 2], ['social', 'Arbeitsschutz beim Einsatz', 2], ['governance', 'Lieferantennachweise', 1]] as [$dimension, $label, $weight]) {
                \App\Models\Sustainability\SustainabilityCriterion::query()->firstOrCreate([
                    'organization_id' => $organization->id,
                    'dimension' => $dimension,
                    'label' => $label,
                ], ['weight' => $weight, 'active' => true]);
            }

            \App\Models\Sustainability\SustainabilityActivityRecord::query()->create([
                'organization_id' => $organization->id,
                'activity_code' => 'electricity_kwh',
                'amount' => '1250.000',
                'unit' => 'kWh',
                'period_start' => \Carbon\Carbon::now()->startOfQuarter()->toDateString(),
                'period_end' => \Carbon\Carbon::now()->toDateString(),
                'data_quality' => 'measured',
                'source_note' => (string) __('Zählerstand Hauptgebäude (Demo)'),
                'created_by' => $actor->id,
            ]);

            $assessments = app(\App\Services\Sustainability\SustainabilityAssessmentService::class);
            $assessment = $assessments->createDraft($organization->id, null, null, (string) __('Akkuschrauber-Flotte (Demo)'), $actor);
            foreach ($assessment->items as $index => $item) {
                $item->update(['score' => [4, 3, 5, 2][$index % 4], 'data_quality' => 'calculated', 'source_note' => (string) __('Herstellerangaben + Wartungshistorie (Demo)')]);
            }
            $assessments->finalize($assessment->refresh(), $actor);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Nachhaltigkeits-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }
}

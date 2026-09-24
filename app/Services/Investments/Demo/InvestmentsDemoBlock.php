<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentsDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Investments\Demo;

use App\Models\Platform\{Organization, User};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/** Investitions-Vorführung. Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class InvestmentsDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'investments' => $this->seedInvestments($context->organization, $actor),
        ];
    }

    public function purge(Organization $organization): void {}

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    /**
     * Demo Feature 069: Investitionsakte mit Variantenvergleich und
     * eingereichtem Budgetantrag (Freigabe bewusst offen — Vorführung
     * der Kette). Robust: Fehler brechen den Seed nicht.
     */
    private function seedInvestments(Organization $organization, ?User $actor): int {
        if (! $this->moduleActive('module.investments')) {
            return 0;
        }
        if ($actor === null) {
            return 0;
        }

        try {
            $case = \App\Models\Investments\InvestmentCase::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Ersatz Servicefahrzeug (Demo)'),
                'category' => 'machine',
                'reason' => (string) __('Bestandsfahrzeug hat 280.000 km und steigende Reparaturkosten.'),
                'objective' => (string) __('Ausfallsicherheit im Außendienst, geringere Werkstattkosten.'),
                'urgency' => 'high',
                'status' => 'comparison',
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);
            $case->options()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Neufahrzeug Kauf (Demo)'),
                'one_time_cost' => '42000.00',
                'recurring_cost_yearly' => '1800.00',
                'delivery_weeks' => 16,
                'quality_score' => 5,
                'recommended' => true,
            ]);
            $case->options()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Jahreswagen (Demo)'),
                'one_time_cost' => '31000.00',
                'recurring_cost_yearly' => '2400.00',
                'delivery_weeks' => 3,
                'quality_score' => 4,
            ]);
            app(\App\Services\Investments\InvestmentService::class)->submitBudget($case->refresh(), [
                'amount' => '42000.00',
                'cost_kind' => 'purchase',
                'financing' => 'loan',
            ], $actor);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Investitions-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }
}

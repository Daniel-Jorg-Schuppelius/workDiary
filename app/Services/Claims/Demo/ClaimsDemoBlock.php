<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimsDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims\Demo;

use App\Models\Platform\{Organization, User};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/** Reklamations-Vorführung. Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class ClaimsDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'claims' => $this->seedClaims($context->organization, $actor),
        ];
    }

    public function purge(Organization $organization): void {}

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    /**
     * Reklamations-Demo (Feature 072, MVP-256): ein bewerteter und
     * entschiedener Fall inkl. Nachweis — ohne Lager-/Faktura-Folgen,
     * damit der Demo-Bestand konsistent bleibt.
     */
    private function seedClaims(Organization $organization, ?User $actor): int {
        if (! $this->moduleActive('module.claims')) {
            return 0;
        }
        if ($actor === null) {
            return 0;
        }

        try {
            $customer = \App\Models\Customer\Customer::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            if ($customer === null) {
                return 0;
            }

            $service = app(\App\Services\Claims\ClaimCaseService::class);
            $case = $service->open($organization, $actor, [
                'title' => (string) __('Thermostatventil tropft nach Wartung (Demo)'),
                'source' => 'phone',
                'priority' => 'high',
                'severity' => 'minor',
                'customer_id' => $customer->id,
                'description' => (string) __('Kunde meldet Tropfbildung am neu eingebauten Ventil im Bad.'),
                'responsible_user_id' => $actor->id,
            ]);
            $case->evidence()->create([
                'organization_id' => $organization->id,
                'kind' => 'photo',
                'title' => (string) __('Foto der Tropfstelle (Demo)'),
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]);
            $service->assess($case, $actor, \App\Enums\Claims\ClaimKind::WarrantyLegal, \App\Enums\Claims\ClaimVerdict::Justified, (string) __('Einbau vor 3 Monaten — gesetzliche Gewährleistung greift, Nacherfüllung angeboten.'));
            $service->decide($case->refresh(), $actor, 'accepted', (string) __('Nacherfüllung durch erneuten Serviceeinsatz (§ 439 BGB).'));
            $case->refresh()->actions()->create([
                'organization_id' => $organization->id,
                'kind' => 'service_visit',
                'status' => 'planned',
                'title' => (string) __('Nachbesserung vor Ort einplanen (Demo)'),
                'assigned_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Reklamations-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }
}

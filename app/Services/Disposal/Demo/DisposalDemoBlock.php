<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Disposal\Demo;

use App\Models\Platform\{Organization, User};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/** Entsorgungs-Vorführung. Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class DisposalDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'disposal' => $this->seedDisposal($context->organization, $actor),
        ];
    }

    public function purge(Organization $organization): void {}

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    private function seedDisposal(Organization $organization, ?User $actor): int {
        if (! $this->moduleActive('module.entsorgung')) {
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

            // Entsorgungsfachbetrieb als externes Kontaktprofil (Feature 033).
            $disposer = \App\Models\Contacts\ExternalContact::query()->create([
                'organization_id' => $organization->id,
                'name' => (string) __('Muster-Entsorgung GmbH (Demo)'),
                'email' => 'entsorgung@example.com',
                'role' => (string) __('Entsorgungsfachbetrieb'),
                'party' => 'other',
            ]);

            $service = app(\App\Services\Disposal\DisposalJobService::class);
            $job = $service->open($organization, $actor, [
                'customer_id' => $customer->id,
                'responsible_user_id' => $actor->id,
                'picked_up_on' => now()->subDays(3)->toDateString(),
                'notes' => (string) __('Demo-Entsorgungsvorgang: Altgeräte-Abholung mit Datenträger-Behandlung.'),
            ]);

            $server = $service->addItem($job, $actor, [
                'category' => (string) __('Server'),
                'manufacturer' => 'Muster-IT',
                'serial_number' => 'DEMO-SRV-001',
                'quantity' => 1,
                'weight_kg' => '18.5',
                'avv_code' => '20 01 35*',
                'has_data_storage' => true,
            ]);
            $service->addItem($job, $actor, [
                'category' => (string) __('Monitor'),
                'quantity' => 4,
                'weight_kg' => '22.0',
                'avv_code' => '20 01 36',
            ]);

            $service->transition($job->refresh(), $actor, \App\Enums\Disposal\DisposalJobStatus::Collected);
            $service->transition($job->refresh(), $actor, \App\Enums\Disposal\DisposalJobStatus::InTreatment);

            $service->addTreatment($server, $actor, [
                'media_type' => \App\Enums\Disposal\DataMediumType::Hdd->value,
                'method' => \App\Enums\Disposal\MediaTreatmentMethod::Shredding->value,
                'din_category' => \App\Enums\Disposal\DinCategory::H->value,
                'security_level' => 5,
                'protection_class' => 2,
                'treated_at' => now()->subDays(2),
                'evidence_reference' => 'DEMO-VERNICHTUNG-4711',
            ]);

            $service->transition($job->refresh(), $actor, \App\Enums\Disposal\DisposalJobStatus::HandedOver);
            $service->addHandover($job->refresh(), $actor, [
                'external_contact_id' => $disposer->id,
                'proof_type' => \App\Enums\Disposal\DisposalProofType::TransferNote->value,
                'document_number' => 'UES-2026-0815',
                'handed_over_on' => now()->subDay()->toDateString(),
                'certificate_reference' => 'EfbV-Zert. DEMO-99',
            ]);
            // Bewusst nicht abgeschlossen: das Prüfpanel zeigt die fehlende
            // Übernahme-Unterschrift als letztes Abschluss-Gate.

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Entsorgungs-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }
}

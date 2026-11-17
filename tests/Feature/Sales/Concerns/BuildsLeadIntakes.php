<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuildsLeadIntakes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales\Concerns;

use App\Models\Platform\User;
use App\Models\Sales\{Lead, LeadIntake};
use App\Services\Sales\Intake\{IntakeKeys, IntakePackages, LeadIntakeService};
use App\Services\Sales\Intake\Packages\GwgIntakeCheckPackage;

/**
 * Gemeinsame Bausteine der Tests zur Aufnahmeakte (Feature 200): Lead,
 * Akte und vollständige GwG-Angaben — alles erfunden, keine echten Ausweis-
 * oder Gesundheitsdaten.
 */
trait BuildsLeadIntakes {
    /** @param  array<string, mixed>  $attributes */
    protected function makeLead(array $attributes = []): Lead {
        return Lead::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'company' => 'Beispiel Werkstatt GmbH (Test)',
            'contact_name' => 'Erika Beispiel',
            'email' => 'erika.beispiel@example.com',
            'source' => 'web',
            'status' => 'qualified',
            'interest' => 'Laufende Buchhaltung',
            'last_contact_at' => now(),
        ], $attributes));
    }

    protected function enableGwg(): void {
        app(IntakePackages::class)->enable($this->organization->refresh(), [GwgIntakeCheckPackage::KEY]);
        $this->organization->refresh();
    }

    protected function openIntake(Lead $lead, User $actor): LeadIntake {
        return app(LeadIntakeService::class)->open($lead, $actor);
    }

    /** @param  array<string, mixed>  $values  Schlüssel des Bogens */
    protected function fill(LeadIntake $intake, array $values): LeadIntake {
        $intake->refresh();
        $intake->fillValues($intake->fieldDocumentValues()->with($values));
        $intake->forceFill(['version' => $intake->version + 1])->saveQuietly();

        return $intake->refresh();
    }

    /**
     * Vollständige GwG-Angaben einer natürlichen Person, geprüft per eID (ohne Kopie).
     *
     * @param  array<string, mixed>  $override  Feldschlüssel ohne Präfix
     * @return array<string, mixed>
     */
    protected function gwgValues(array $override = []): array {
        $values = array_replace([
            'party_kind' => 'natural',
            'first_name' => 'Erika',
            'last_name' => 'Beispiel',
            'birth_place' => 'Musterstadt',
            'birth_date' => '1980-01-01',
            'nationality' => 'erfunden',
            'home_address' => 'Musterweg 1, 12345 Musterstadt',
            'verification_method' => 'eid',
            'verified_on' => '2026-03-01',
            'pep_status' => 'no',
            'purpose' => 'Laufende Steuerberatung (Test)',
            'suspicion_checked' => true,
            'risk_level' => 'normal',
        ], $override);
        $out = ['request' => 'Laufende Buchhaltung und Jahresabschluss (Test)'];
        foreach ($values as $key => $value) {
            $out[IntakeKeys::package(GwgIntakeCheckPackage::KEY, $key)] = $value;
        }

        return $out;
    }
}

<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyNumberSequenceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\{DataSubjectRequestType, IncidentType};
use App\Models\Organization;
use App\Services\Privacy\{DataSubjectRequestService, IncidentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Vollreview W1.1: Privacy-Nummern laufen über den zentralen
 * NumberSequenceService (kein count()+1 mehr) — Format und Fortsetzung
 * über Bestandsdaten bleiben stabil.
 */
class PrivacyNumberSequenceTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
    }

    public function test_incident_numbers_use_central_sequence(): void {
        $org = Organization::factory()->create();
        $service = app(IncidentService::class);
        $year = Carbon::now()->year;

        $first = $service->open($org, IncidentType::Loss, 'Vorfall A');
        $second = $service->open($org, IncidentType::Loss, 'Vorfall B');

        $this->assertSame(sprintf('DSV-%d-0001', $year), $first->incident_number);
        $this->assertSame(sprintf('DSV-%d-0002', $year), $second->incident_number);
    }

    public function test_incident_numbers_continue_after_seeded_sequence(): void {
        $org = Organization::factory()->create();
        $year = Carbon::now()->year;

        // Bestand: Sequenz steht (z. B. durch die Seed-Migration) auf 7.
        DB::table('number_sequences')->insert([
            'organization_id' => $org->id,
            'scope' => 'privacy_incident',
            'period' => (string) $year,
            'last_value' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $incident = app(IncidentService::class)->open($org, IncidentType::Loss, 'Vorfall');

        $this->assertSame(sprintf('DSV-%d-0008', $year), $incident->incident_number);
    }

    public function test_data_subject_request_numbers_use_central_sequence(): void {
        $org = Organization::factory()->create();
        $service = app(DataSubjectRequestService::class);
        $year = Carbon::now()->year;

        $first = $service->open($org, DataSubjectRequestType::Access, 'Auskunft A', 'Inhalt A');
        $second = $service->open($org, DataSubjectRequestType::Access, 'Auskunft B', 'Inhalt B');

        $this->assertSame(sprintf('DSR-%d-0001', $year), $first->request_number);
        $this->assertSame(sprintf('DSR-%d-0002', $year), $second->request_number);
    }

    public function test_sequences_are_isolated_per_organization(): void {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $service = app(IncidentService::class);
        $year = Carbon::now()->year;

        $service->open($orgA, IncidentType::Loss, 'A1');
        $b = $service->open($orgB, IncidentType::Loss, 'B1');

        $this->assertSame(sprintf('DSV-%d-0001', $year), $b->incident_number);
    }
}

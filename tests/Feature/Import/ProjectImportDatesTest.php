<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectImportDatesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Import;

use App\Models\Customer\Customer;
use App\Models\Project\Project;
use App\Services\Import\ImportOutcome;
use App\Services\Project\Import\ProjectSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Der Projekt-Import las Daten mit einem eigenen Parser: der 31.02. wurde zum
 * 3. März, Unlesbares still zu „leer“ (Konsolidierungs-Audit 2026-10, k1-01).
 * Jetzt prüft und wandelt die gemeinsame Datumsprüfung der Importe.
 */
final class ProjectImportDatesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_invalid_dates_are_reported_and_valid_ones_are_stored_as_iso(): void {
        $this->setUpOrganization();
        Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-100']);
        $spec = app(ProjectSpec::class);
        $row = fn (string $start, string $end): array => $spec->normalize([
            'name' => 'Hallensanierung', 'customer_number' => 'K-100', 'starts_on' => $start, 'ends_on' => $end,
        ]);

        $fields = array_map(static fn ($issue): ?string => $issue->field, $spec->validateRow($row('31.02.2026', 'irgendwann'), $this->organization));
        $this->assertSame(['starts_on', 'ends_on'], $fields);

        $valid = $row('01.03.2026', '15.06.26');
        $this->assertSame([], $spec->validateRow($valid, $this->organization));
        [$outcome] = $spec->upsert($valid, $this->organization);

        $this->assertNotSame(ImportOutcome::Failed, $outcome);
        $project = Project::query()->where('name', 'Hallensanierung')->sole();
        $this->assertSame('2026-03-01', $project->starts_on?->toDateString());
        $this->assertSame('2026-06-15', $project->ends_on?->toDateString());
    }
}

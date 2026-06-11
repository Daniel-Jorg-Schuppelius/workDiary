<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionTransferGuardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, Project, TimeEntry, User};
use App\Services\TimeApproval\{TimeCorrectionService, TimeCorrectionWorkflowException};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Zeitkorrektur-Guard (Feature 045): bereits an die Fakturierung übergebene
 * (exported) Zeiteinträge dürfen nicht mehr per Korrekturantrag geändert oder
 * gelöscht werden.
 */
class TimeCorrectionTransferGuardTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    private User $approver;

    private TimeEntry $entry;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->approver = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'created_by' => $this->approver->id,
        ]);
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->approver->id,
        ]);
        $this->entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->owner->id,
            'date' => '2030-04-01',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
        ]);
    }

    private function applyCorrection(): void {
        $service = app(TimeCorrectionService::class);

        $request = $service->createDraft(
            $this->owner,
            CarbonImmutable::parse('2030-04-01'),
            'Minuten falsch erfasst, bitte auf 90 Minuten korrigieren.',
            [[
                'target_type' => TimeEntry::class,
                'target_id' => $this->entry->id,
                'action' => 'update',
                'after' => ['minutes' => 90],
            ]],
            $this->owner,
        );
        $service->submit($request);
        $service->approve($request->fresh(), $this->approver);
        $service->apply($request->fresh());
    }

    public function test_correction_on_transferred_entry_is_blocked(): void {
        $this->entry->forceFill(['exported' => true])->saveQuietly();

        try {
            $this->applyCorrection();
            $this->fail('Korrektur an übergebenem Eintrag muss blockiert werden');
        } catch (TimeCorrectionWorkflowException $e) {
            $this->assertSame('sourceTransferred', $e->reasonCode);
        }

        // Eintrag bleibt unverändert.
        $this->assertSame(120, (int) $this->entry->fresh()->minutes);
    }

    public function test_correction_on_untransferred_entry_still_works(): void {
        $this->applyCorrection();

        $this->assertSame(90, (int) $this->entry->fresh()->minutes);
    }
}

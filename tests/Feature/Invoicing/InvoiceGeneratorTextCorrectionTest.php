<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceGeneratorTextCorrectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, Project, TextCorrection, TimeEntry, User};
use App\Services\Invoicing\InvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Wörterbuch im klassischen Rechnungspfad: Vorschau und Rechnungslauf
 * liefern identisch korrigierte Positionstexte (gemeinsamer Trichter
 * bookingLine), die Zeiteinträge bleiben unverändert.
 */
class InvoiceGeneratorTextCorrectionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private TimeEntry $entry;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->admin->id,
        ]);
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        $this->entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'date' => '2030-04-01',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => '90.00',
            'description' => 'Server geprüfft',
        ]);

        TextCorrection::factory()->create([
            'organization_id' => $this->organization->id,
            'wrong' => 'geprüfft',
            'correct' => 'geprüft',
        ]);
    }

    public function test_vorschau_und_rechnungslauf_korrigieren_identisch(): void {
        $preview = app(InvoiceGenerator::class)->previewTimeEntries($this->customer, null);
        $previewDescription = (string) $preview['lines'][0]['description'];

        $invoice = app(InvoiceGenerator::class)->fromTimeEntries($this->customer->fresh(), null);
        $itemDescription = (string) $invoice->items->first()->description;

        $this->assertStringContainsString('Server geprüft', $previewDescription);
        $this->assertStringNotContainsString('geprüfft', $previewDescription);
        $this->assertSame($previewDescription, $itemDescription);

        // Quelldaten bleiben unangetastet.
        $this->assertSame('Server geprüfft', $this->entry->fresh()->description);
    }
}

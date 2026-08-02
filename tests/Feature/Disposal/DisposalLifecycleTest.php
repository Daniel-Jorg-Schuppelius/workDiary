<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Disposal;

use App\Enums\Asset\AssetStatus;
use App\Enums\Disposal\{DataMediumType, DinCategory, DisposalJobEventType, DisposalJobStatus, DisposalProofType, MediaTreatmentMethod};
use App\Models\{Asset, Customer, ExternalContact, User};
use App\Models\Disposal\{DisposalItem, DisposalJob};
use App\Services\Disposal\DisposalJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 100 (MVP-469/470): Lebenszyklus der Entsorgungsakte auf
 * Service-Ebene — Nummernkreis + Ereigniskette, AVV-Gefährlichkeits-
 * ableitung (WasteCode-VO, nie frei gesetzt), Statusmaschine mit
 * bewachtem Abschluss/Storno, Unveränderlichkeit nach Entsorger-Übergabe,
 * Abschluss-Gates (Status, Unterschrift, Datenträger-Behandlung,
 * Entsorger-Nachweis) und der versionierte Kundennachweis als
 * freigegebenes DMS-Dokument inkl. Asset-Ausmusterung.
 */
final class DisposalLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** 1×1-Pixel-PNG als data-URI (Canvas-Signatur-Muster). */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function service(): DisposalJobService {
        return app(DisposalJobService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function openJob(array $overrides = []): DisposalJob {
        return $this->service()->open($this->organization, $this->admin, array_merge([
            'customer_id' => $this->customer->id,
        ], $overrides));
    }

    /**
     * Bringt eine Akte bis unmittelbar vor den Abschluss: gefährliches,
     * datentragendes Item (optional mit Asset), Behandlung, Übergabe an den
     * Entsorger inkl. Nachweis — es fehlt nur noch die Unterschrift.
     *
     * @return array{0: DisposalJob, 1: DisposalItem}
     */
    private function readyForCompletion(?Asset $asset = null): array {
        $service = $this->service();

        $job = $this->openJob();
        $item = $service->addItem($job, $this->admin, [
            'category' => 'Server',
            'serial_number' => 'SRV-001',
            'quantity' => 1,
            'avv_code' => '20 01 35*',
            'has_data_storage' => true,
            'asset_id' => $asset?->id,
        ]);

        $job = $service->transition($job, $this->admin, DisposalJobStatus::Collected);

        $service->addTreatment($item, $this->admin, [
            'media_type' => DataMediumType::Hdd->value,
            'method' => MediaTreatmentMethod::Shredding->value,
            'din_category' => DinCategory::H->value,
            'security_level' => 5,
            'treated_at' => now(),
        ]);

        $job = $service->transition($job, $this->admin, DisposalJobStatus::HandedOver);

        $disposer = ExternalContact::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Entsorger',
            'party' => 'other',
        ]);
        $service->addHandover($job, $this->admin, [
            'external_contact_id' => $disposer->id,
            'proof_type' => DisposalProofType::TransferNote->value,
            'document_number' => 'UES-1',
            'handed_over_on' => now()->toDateString(),
        ]);

        return [$job->refresh(), $item->refresh()];
    }

    public function test_open_assigns_number_and_logs_created_event(): void {
        $job = $this->openJob();

        $this->assertStringStartsWith('ENT-' . now()->format('Y') . '-', $job->number);
        $this->assertSame(DisposalJobStatus::Draft, $job->status);

        $events = $job->events()->get();
        $this->assertCount(1, $events);
        $this->assertSame(DisposalJobEventType::Created, $events->first()->event);
    }

    public function test_item_avv_star_derives_hazard_and_normalizes(): void {
        $job = $this->openJob();

        // Kompakte Eingabe wird kanonisiert, Stern leitet die Gefährlichkeit ab —
        // ein mitgegebenes is_hazardous=false wird überschrieben (nie frei gesetzt).
        $hazardous = $this->service()->addItem($job, $this->admin, [
            'category' => 'Server',
            'quantity' => 1,
            'avv_code' => '200135*',
            'is_hazardous' => false,
        ]);
        $this->assertSame('20 01 35*', $hazardous->avv_code);
        $this->assertTrue($hazardous->is_hazardous);

        $harmless = $this->service()->addItem($job, $this->admin, [
            'category' => 'Monitor',
            'quantity' => 1,
            'avv_code' => '16 02 14',
        ]);
        $this->assertSame('16 02 14', $harmless->avv_code);
        $this->assertFalse($harmless->is_hazardous);
    }

    public function test_invalid_avv_code_is_rejected(): void {
        $job = $this->openJob();

        // Kapitel 99 existiert im Abfallverzeichnis nicht (01–20).
        $this->expectException(RuntimeException::class);
        $this->service()->addItem($job, $this->admin, [
            'category' => 'PC',
            'quantity' => 1,
            'avv_code' => '99 99 99',
        ]);
    }

    public function test_status_machine_blocks_illegal_transitions(): void {
        $job = $this->openJob();

        // Draft → HandedOver überspringt die Abholung.
        try {
            $this->service()->transition($job, $this->admin, DisposalJobStatus::HandedOver);
            $this->fail('Draft → HandedOver darf nicht möglich sein.');
        } catch (RuntimeException) {
            // erwartet
        }

        // Abschluss und Storno laufen NUR über complete()/cancel().
        try {
            $this->service()->transition($job, $this->admin, DisposalJobStatus::Completed);
            $this->fail('Completed darf nicht über transition() erreichbar sein.');
        } catch (RuntimeException) {
            // erwartet
        }
        try {
            $this->service()->transition($job, $this->admin, DisposalJobStatus::Cancelled);
            $this->fail('Cancelled darf nicht über transition() erreichbar sein.');
        } catch (RuntimeException) {
            // erwartet
        }

        $this->assertSame(DisposalJobStatus::Draft, $job->fresh()->status);
    }

    public function test_items_become_immutable_after_handover(): void {
        $service = $this->service();
        $job = $this->openJob();
        $job = $service->transition($job, $this->admin, DisposalJobStatus::Collected);
        $job = $service->transition($job, $this->admin, DisposalJobStatus::HandedOver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nicht mehr änderbar');
        $service->addItem($job, $this->admin, [
            'category' => 'PC',
            'quantity' => 1,
            'avv_code' => '16 02 14',
        ]);
    }

    public function test_completion_blockers_list_all_gates(): void {
        $service = $this->service();
        $job = $this->openJob();
        $service->addItem($job, $this->admin, [
            'category' => 'Server',
            'quantity' => 1,
            'avv_code' => '20 01 35*',
            'has_data_storage' => true,
        ]);
        $job = $service->transition($job, $this->admin, DisposalJobStatus::Collected);

        $blockers = $service->completionBlockers($job->refresh());

        // Status, Unterschrift, Datenträger-Behandlung, Entsorger-Nachweis.
        $this->assertCount(4, $blockers);
        $text = implode(' ', $blockers);
        $this->assertStringContainsString('übergeben', $text);
        $this->assertStringContainsString('Unterschrift', $text);
        $this->assertStringContainsString('Datenträger-Behandlung fehlt', $text);
        $this->assertStringContainsString('Entsorger-Nachweis', $text);
    }

    public function test_complete_requires_all_gates_and_produces_released_record(): void {
        Storage::fake('local');
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        [$job] = $this->readyForCompletion($asset);

        $job = $this->service()->sign($job, $this->admin, 'Max Mustermann', self::PNG);
        $job = $this->service()->complete($job->refresh(), $this->admin);

        $this->assertSame(DisposalJobStatus::Completed, $job->status);
        $this->assertNotNull($job->completed_at);
        $this->assertNotNull($job->record_document_id);

        // Kundennachweis: freigegebenes DMS-Dokument mit genau einer PDF-Version.
        $document = $job->recordDocument()->firstOrFail();
        $this->assertTrue($document->customer_visible);
        $this->assertSame(1, $document->versions()->count());
        $this->assertSame('application/pdf', $document->currentVersion()->firstOrFail()->mime);

        // Verknüpftes Asset wird ausgemustert.
        $this->assertSame(AssetStatus::Decommissioned, $asset->refresh()->status);

        $events = $job->events()->pluck('event')->map(fn (DisposalJobEventType $event): string => $event->value)->all();
        $this->assertContains('completed', $events);
        $this->assertContains('record_rendered', $events);
    }

    public function test_complete_blocked_without_signature(): void {
        Storage::fake('local');
        [$job] = $this->readyForCompletion();

        try {
            $this->service()->complete($job, $this->admin);
            $this->fail('Abschluss ohne Übernahme-Unterschrift darf nicht möglich sein.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Unterschrift', $exception->getMessage());
        }

        $fresh = $job->fresh();
        $this->assertSame(DisposalJobStatus::HandedOver, $fresh->status);
        $this->assertNull($fresh->record_document_id);
    }

    public function test_sign_rejects_non_png(): void {
        $job = $this->openJob();

        $this->expectException(RuntimeException::class);
        $this->service()->sign($job, $this->admin, 'Max Mustermann', 'data:image/png;base64,' . base64_encode('kein png'));
    }

    public function test_cancel_requires_reason_and_freezes(): void {
        $job = $this->openJob();

        $job = $this->service()->cancel($job, $this->admin, 'Irrtümlich angelegt');

        $this->assertSame(DisposalJobStatus::Cancelled, $job->status);
        $this->assertSame('Irrtümlich angelegt', $job->cancel_reason);
        $this->assertNotNull($job->cancelled_at);

        // Stornierte Akte ist eingefroren.
        $this->expectException(RuntimeException::class);
        $this->service()->addItem($job, $this->admin, [
            'category' => 'PC',
            'quantity' => 1,
            'avv_code' => '16 02 14',
        ]);
    }

    public function test_treatment_marks_item_as_data_storage(): void {
        $job = $this->openJob();
        $item = $this->service()->addItem($job, $this->admin, [
            'category' => 'Router',
            'quantity' => 1,
            'avv_code' => '16 02 14',
            'has_data_storage' => false,
        ]);
        $this->assertFalse($item->has_data_storage);

        $this->service()->addTreatment($item, $this->admin, [
            'media_type' => DataMediumType::UsbFlash->value,
            'method' => MediaTreatmentMethod::SoftwareWipe->value,
            'din_category' => DinCategory::E->value,
            'security_level' => 3,
            'treated_at' => now(),
        ]);

        // Wer eine Behandlung dokumentiert, bestätigt implizit den Datenträger.
        $this->assertTrue($item->refresh()->has_data_storage);
    }

    public function test_render_record_adds_new_version_when_repeated(): void {
        Storage::fake('local');
        [$job] = $this->readyForCompletion();
        $job = $this->service()->sign($job, $this->admin, 'Max Mustermann', self::PNG);
        $job = $this->service()->complete($job->refresh(), $this->admin);

        $documentId = $job->record_document_id;
        $this->assertNotNull($documentId);

        $document = $this->service()->renderRecord($job->refresh(), $this->admin);

        // Erneutes Rendern versioniert den Nachweis, statt ein neues Dokument anzulegen.
        $this->assertSame($documentId, $job->refresh()->record_document_id);
        $this->assertSame($documentId, $document->id);
        $this->assertSame(2, $document->versions()->count());
    }
}

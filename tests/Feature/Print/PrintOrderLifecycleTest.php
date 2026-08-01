<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintOrderLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Print;

use App\Enums\Asset\AssetBlockReason;
use App\Enums\Print\{PreflightStatus, PrintOrderStatus};
use App\Exceptions\AssetNotUsableException;
use App\Models\{Article, Asset, Document, ManufacturingOrder, User};
use App\Models\Print\PrintOrder;
use App\Services\Asset\AssetBlockService;
use App\Services\Document\DocumentService;
use App\Services\Manufacturing\ManufacturingOrderService;
use App\Services\Print\PrintOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-459 — Druckauftrag: Datei-Hash-Bindung, Preflight-Gates mit
 * auditiertem Override, eingefrorener Freigabe-Snapshot, Rücksetzung bei
 * Dateiänderung, Maschinen-Gate (D12) und Löschfrist, die nur die Datei
 * entfernt (kaufmännischer Nachweis bleibt).
 */
class PrintOrderLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actor = $this->orgAdmin();
    }

    private function service(): PrintOrderService {
        return app(PrintOrderService::class);
    }

    private function manufacturingOrder(): ManufacturingOrder {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true]);

        return app(ManufacturingOrderService::class)->createDraft($this->organization, $article, null, '500', 'Stk');
    }

    private function printOrder(): PrintOrder {
        return $this->service()->open($this->manufacturingOrder(), $this->actor);
    }

    /** Datei im Dokumentenspeicher anlegen und an den Auftrag binden. */
    private function bindPdf(PrintOrder $order, string $content = "%PDF-1.7\nHello"): Document {
        $document = app(DocumentService::class)->create(null, $this->actor, [
            'title' => 'Druckdaten Flyer',
            'document_type' => 'other',
        ], UploadedFile::fake()->createWithContent('flyer.pdf', $content));

        $version = $document->versions()->orderByDesc('version_no')->firstOrFail();
        $this->service()->bindFile($order, $document, $version, $this->actor);

        return $document;
    }

    /** @return array<string, mixed> */
    private function approvalParameters(): array {
        return [
            'final_format' => 'DIN A6',
            'pages' => 2,
            'color_mode' => '4/4 CMYK',
            'material' => 'Bilderdruck matt',
            'grammage' => '250 g/m²',
            'quantity' => '500',
            'due_date' => now()->addDays(3)->toDateString(),
            'finishing' => ['schneiden'],
        ];
    }

    public function test_branch_profile_installs_idempotently(): void {
        $installer = app(\App\Services\Classification\BranchProfileInstaller::class);
        $installer->install($this->organization, 'druck-kopiershop', $this->actor);

        $this->assertTrue(\App\Models\Classification::query()
            ->where('organization_id', $this->organization->id)
            ->where('domain', 'product_group')->where('code', 'visitenkarten')->exists());
        $this->assertTrue(\App\Models\ProcedureTemplate::query()
            ->where('organization_id', $this->organization->id)
            ->where('code', 'DR_DRUCKFREIGABE')->exists());

        $count = \App\Models\Classification::query()->where('organization_id', $this->organization->id)->count();
        $installer->install($this->organization, 'druck-kopiershop', $this->actor);
        $this->assertSame($count, \App\Models\Classification::query()->where('organization_id', $this->organization->id)->count());

        $settings = (array) $this->organization->refresh()->settings;
        $this->assertSame('druck-kopiershop', $settings['branch_profile_code'] ?? null);
        $this->assertTrue($this->service()->isPrintProfileActive($this->organization));
    }

    public function test_approval_requires_file_and_clean_preflight_and_freezes_snapshot(): void {
        Storage::fake('local');
        $order = $this->printOrder();

        // Ohne Datei keine Freigabe.
        try {
            $this->service()->approve($order, $this->approvalParameters(), $this->actor);
            $this->fail('Freigabe ohne Datei darf nicht möglich sein.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        $this->bindPdf($order);
        $this->assertSame(PreflightStatus::Pending, $order->refresh()->preflight_status);
        $this->assertNotNull($order->file_hash);

        // Preflight „pending" blockiert die Freigabe ebenfalls.
        try {
            $this->service()->approve($order, $this->approvalParameters(), $this->actor);
            $this->fail('Freigabe ohne Preflight darf nicht möglich sein.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('preflight', $e->errors());
        }

        $this->service()->runPreflight($order, $this->actor);
        $this->assertSame(PreflightStatus::Passed, $order->refresh()->preflight_status);
        $this->assertSame('basic', $order->preflight_provider);

        // Unvollständige Parameter blockieren (Menge fehlt).
        $incomplete = $this->approvalParameters();
        unset($incomplete['quantity']);
        try {
            $this->service()->approve($order, $incomplete, $this->actor);
            $this->fail('Freigabe ohne Menge darf nicht möglich sein.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $this->service()->approve($order, $this->approvalParameters(), $this->actor);
        $order->refresh();
        $this->assertSame(PrintOrderStatus::Approved, $order->status);
        $this->assertSame($order->file_hash, $order->approved_file_hash);
        $this->assertSame('DIN A6', data_get($order->production_snapshot, 'final_format'));
        $this->assertSame($order->file_hash, data_get($order->production_snapshot, 'file.sha256'));
        $this->assertTrue($order->approvalMatchesFile());
    }

    public function test_new_file_version_resets_approval_to_data_check(): void {
        Storage::fake('local');
        $order = $this->printOrder();
        $document = $this->bindPdf($order);
        $this->service()->runPreflight($order, $this->actor);
        $this->service()->approve($order->refresh(), $this->approvalParameters(), $this->actor);

        // Neue (geänderte) Dateiversion → zurück auf Datenprüfung, Freigabe weg.
        $version = app(DocumentService::class)->addVersion($document, $this->actor, UploadedFile::fake()->createWithContent('flyer-v2.pdf', "%PDF-1.7\nOther"));
        $this->service()->bindFile($order->refresh(), $document, $version, $this->actor);

        $order->refresh();
        $this->assertSame(PrintOrderStatus::DataCheck, $order->status);
        $this->assertNull($order->approved_at);
        $this->assertNull($order->approved_file_hash);
        $this->assertNull($order->production_snapshot);
        $this->assertSame(PreflightStatus::Pending, $order->preflight_status);
    }

    public function test_preflight_errors_block_and_override_needs_reason_and_is_audited(): void {
        Storage::fake('local');
        $order = $this->printOrder();
        $this->bindPdf($order, 'KEIN-PDF-INHALT'); // Mime pdf, Header kaputt
        $this->service()->runPreflight($order, $this->actor);
        $this->assertSame(PreflightStatus::Failed, $order->refresh()->preflight_status);
        $this->assertNotEmpty(data_get($order->preflight_findings, 'errors'));

        try {
            $this->service()->approve($order, $this->approvalParameters(), $this->actor);
            $this->fail('Blockierende Preflight-Fehler dürfen die Freigabe verhindern.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('preflight', $e->errors());
        }

        try {
            $this->service()->overridePreflight($order, '   ', $this->actor);
            $this->fail('Override ohne Begründung darf nicht möglich sein.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $this->service()->overridePreflight($order, 'Kunde wünscht Druck trotz Warnhinweis, Risiko übernommen.', $this->actor);
        $order->refresh();
        $this->assertSame(PreflightStatus::Overridden, $order->preflight_status);
        $this->assertTrue($order->auditLogs()->where('event', 'print.preflight_overridden')->exists());

        $this->service()->approve($order, $this->approvalParameters(), $this->actor);
        $this->assertSame(PrintOrderStatus::Approved, $order->refresh()->status);
    }

    public function test_machine_gate_blocks_locked_or_overdue_assets(): void {
        Storage::fake('local');
        $order = $this->printOrder();
        $this->bindPdf($order);
        $this->service()->runPreflight($order, $this->actor);
        $this->service()->approve($order->refresh(), $this->approvalParameters(), $this->actor);

        $machine = Asset::factory()->create(['organization_id' => $this->organization->id]);
        app(AssetBlockService::class)->block($machine, AssetBlockReason::InspectionOverdue, $this->actor, 'Kalibrierung überfällig');

        try {
            $this->service()->startProduction($order->refresh(), $machine, $this->actor);
            $this->fail('Gesperrte Maschine darf nicht eingeplant werden.');
        } catch (AssetNotUsableException) {
            // erwartet: D12-Guard blockiert
        }
        $this->assertSame(PrintOrderStatus::Approved, $order->refresh()->status);

        $usable = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $this->service()->startProduction($order, $usable, $this->actor);
        $order->refresh();
        $this->assertSame(PrintOrderStatus::InProduction, $order->status);
        $this->assertSame($usable->id, $order->asset_id);
    }

    public function test_quality_check_issue_and_retention_purge(): void {
        Storage::fake('local');
        $order = $this->printOrder();
        $this->bindPdf($order);
        $this->service()->runPreflight($order, $this->actor);
        $this->service()->approve($order->refresh(), $this->approvalParameters(), $this->actor);
        $this->service()->startProduction($order->refresh(), null, $this->actor);

        // QK: Nacharbeit → zurück in Produktion → bestanden → bereit.
        $this->service()->qualityCheck($order->refresh(), PrintOrder::QC_REWORK, 'Farbabweichung Andruck', $this->actor);
        $this->assertSame(PrintOrderStatus::Rework, $order->refresh()->status);
        $this->service()->resumeProduction($order, $this->actor);
        $this->service()->qualityCheck($order->refresh(), PrintOrder::QC_PASSED, null, $this->actor);
        $this->assertSame(PrintOrderStatus::Ready, $order->refresh()->status);

        // Abholung braucht einen Übergabenachweis.
        try {
            $this->service()->issue($order, [], $this->actor);
            $this->fail('Abholung ohne Übergabenachweis darf nicht möglich sein.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('handover_name', $e->errors());
        }
        $this->service()->issue($order, ['handover_name' => 'M. Muster'], $this->actor);
        $this->assertSame(PrintOrderStatus::Issued, $order->refresh()->status);

        // Löschfrist: Datei weg, Nachweis (Hash + Snapshot) bleibt.
        $order->forceFill(['files_retain_until' => now()->subDay()->toDateString()])->save();
        $version = $order->documentVersion()->firstOrFail();
        Storage::disk($version->disk)->assertExists($version->path);

        $purged = $this->service()->purgeExpiredFiles();
        $this->assertSame(1, $purged);
        Storage::disk($version->disk)->assertMissing($version->path);
        $order->refresh();
        $this->assertNotNull($order->files_purged_at);
        $this->assertNotNull($order->approved_file_hash);
        $this->assertNotNull($order->production_snapshot);
        $this->assertFalse($order->hasProductionFile());
    }

    public function test_foreign_documents_and_double_specialization_are_rejected(): void {
        Storage::fake('local');
        $order = $this->printOrder();

        // Zweite Fachakte am selben Fertigungsauftrag ist unzulässig (1:1).
        try {
            $this->service()->open($order->manufacturingOrder()->firstOrFail(), $this->actor);
            $this->fail('Doppelte Spezialisierung darf nicht möglich sein.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('manufacturing_order', $e->errors());
        }

        // Dokument einer fremden Organisation bindet nicht.
        $foreignAdmin = User::factory()->admin()->create();
        $foreignDocument = app(DocumentService::class)->create(null, $foreignAdmin, [
            'title' => 'Fremde Druckdaten',
            'document_type' => 'other',
        ], UploadedFile::fake()->createWithContent('foreign.pdf', "%PDF-1.7\nX"));
        $foreignVersion = $foreignDocument->versions()->orderByDesc('version_no')->firstOrFail();

        try {
            $this->service()->bindFile($order, $foreignDocument, $foreignVersion, $this->actor);
            $this->fail('Fremdes Dokument darf nicht gebunden werden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }
    }
}

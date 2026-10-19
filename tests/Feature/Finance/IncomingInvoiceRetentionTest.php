<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceRetentionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Models\Document\Document;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Models\Privacy\RetentionProposal;
use App\Services\Retention\RetentionScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Abgelehnte Rechnungseingänge räumt die Aufbewahrung nach Frist und Freigabe weg (Phase 150, MVP-1213). */
final class IncomingInvoiceRetentionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_rejected_documents_are_proposed_after_the_period_and_purged_after_approval(): void {
        config()->set('invoicing.rejected_retention_days', 30);
        $old = $this->incoming(['status' => IncomingEInvoiceStatus::Rejected, 'decided_at' => now()->subDays(40)]);
        $fresh = $this->incoming(['status' => IncomingEInvoiceStatus::Rejected, 'decided_at' => now()->subDays(5)]);
        $open = $this->incoming([]);

        $service = app(RetentionScanService::class);
        $service->scan($this->organization);
        $proposals = RetentionProposal::query()->where('area', 'incoming_invoices_rejected')->get();
        $this->assertSame([$old->id], $proposals->pluck('subject_id')->map(intval(...))->all());

        $proposal = $proposals->firstOrFail();
        $service->approve($proposal, $this->admin);
        $service->purge($proposal->fresh(), $this->admin);

        $this->assertDatabaseMissing('incoming_einvoices', ['id' => $old->id]);
        $this->assertDatabaseMissing('documents', ['id' => $old->document_id]);
        $this->assertDatabaseHas('incoming_einvoices', ['id' => $fresh->id]);
        $this->assertDatabaseHas('incoming_einvoices', ['id' => $open->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'incoming_einvoice.discarded', 'auditable_id' => $old->id]);
    }

    public function test_without_a_period_nothing_is_proposed(): void {
        config()->set('invoicing.rejected_retention_days', 0);
        $this->incoming(['status' => IncomingEInvoiceStatus::Rejected, 'decided_at' => now()->subYears(2)]);

        app(RetentionScanService::class)->scan($this->organization);

        $this->assertSame(0, RetentionProposal::query()->where('area', 'incoming_invoices_rejected')->count());
    }

    /** @param  array<string, mixed>  $attributes */
    private function incoming(array $attributes): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice, 'created_by_user_id' => $this->admin->id]);
        $incoming = IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'mail', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'recognition' => IncomingInvoiceRecognition::Extracted,
            'invoice_number' => 'RE-' . uniqid(), 'seller_name' => 'Irgendwer', 'currency' => 'EUR', 'amount_gross' => '119.00',
            'summary' => [],
        ]);
        if ($attributes !== []) {
            $incoming->forceFill($attributes)->save();
        }

        return $incoming;
    }
}

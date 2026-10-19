<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeasureRecognitionCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Models\Document\{Document, DocumentVersion, DocumentVersionText};
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Messlauf der Texterkennung (Phase 151, MVP-1217): zählt je Feld, schreibt nichts. */
final class MeasureRecognitionCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_it_counts_fields_kind_hints_and_agreement_with_manual_values(): void {
        $this->incomingWithText("Rechnung\nRechnungsnummer: RE-1\nRechnungsdatum: 14.08.2026\nGesamtbetrag 119,00 EUR", [
            'summary' => ['manual' => ['invoice_number' => 're-1', 'issue_date' => '2026-08-14', 'amount_gross' => '119.00']],
        ]);
        $this->incomingWithText("2. Mahnung\nReNr. Datum Betrag\nRZLRE2501475 17.06.2025 13,51\nZu zahlen: 16,51", ['recognition' => IncomingInvoiceRecognition::None]);
        $this->incomingWithText(null, ['recognition' => IncomingInvoiceRecognition::None]);
        $before = IncomingEInvoice::query()->get()->map(static fn (IncomingEInvoice $i): string => (string) $i->updated_at)->all();

        $this->assertSame(0, Artisan::call('incoming-invoices:measure-recognition', ['--organization' => $this->organization->id]));
        $output = Artisan::output();

        $this->assertStringContainsString('3 unstrukturierte Eingänge, 2 mit Text, 1 ohne Text', $output);
        $this->assertStringContainsString('reminder', $output);
        $this->assertMatchesRegularExpression('/Nummer\s*\|\s*1\s*\|\s*1/', $output);
        $this->assertSame($before, IncomingEInvoice::query()->get()->map(static fn (IncomingEInvoice $i): string => (string) $i->updated_at)->all(), 'Der Messlauf schreibt nichts.');

        $this->assertSame(0, Artisan::call('incoming-invoices:measure-recognition', ['--organization' => $this->organization->id, '--json' => true]));
        $this->assertStringContainsString('"with_text": 2', Artisan::output());
    }

    /** @param  array<string, mixed>  $attributes */
    private function incomingWithText(?string $text, array $attributes): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice, 'created_by_user_id' => $this->admin->id]);
        $version = DocumentVersion::factory()->create(['document_id' => $document->id, 'uploaded_by_user_id' => $this->admin->id]);
        $document->forceFill(['current_version_id' => $version->id])->save();
        if ($text !== null) {
            DocumentVersionText::factory()->create(['document_version_id' => $version->id, 'text' => $text]);
        }
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

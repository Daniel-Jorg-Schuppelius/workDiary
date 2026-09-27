<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeasingDocumentExtractionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\AssetFinance;

use App\Enums\Document\DocumentType;
use App\Models\Platform\User;
use App\Services\Document\{ContractTextAnalyzer, DocumentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-934: Leasingdaten aus einem Vertragsdokument vorschlagen. */
final class LeasingDocumentExtractionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const TEXT = "Leasingvertrag Nr. LV-2026-117\nLeasingbeginn: 01.10.2026\nDie Laufzeit beträgt 36 Monate.\nLeasingrate: 489,90 EUR monatlich zzgl. USt.\nLeasingsonderzahlung: 5.000,00 EUR\nKalkulierter Restwert: 12.345,67 EUR\nKaufoption zu 12.000,00 EUR zum Vertragsende.";

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_analyzer_recognises_leasing_terms(): void {
        $fields = app(ContractTextAnalyzer::class)->leasing(self::TEXT)['fields'];

        $this->assertSame('2026-10-01', $fields['starts_on']);
        $this->assertSame('2029-09-30', $fields['ends_on']);
        $this->assertSame('489.90', $fields['rate_amount']);
        $this->assertSame('monthly', $fields['payment_rhythm']);
        $this->assertSame('5000.00', $fields['special_payment']);
        $this->assertSame('12345.67', $fields['residual_value']);
        $this->assertSame('12000.00', $fields['purchase_option_amount']);
        $this->assertArrayNotHasKey('min_term_months', $fields);

        $quarterly = app(ContractTextAnalyzer::class)->leasing('Rate: 1.200,00 € vierteljährlich')['fields'];
        $this->assertSame(['rate_amount' => '1200.00', 'payment_rhythm' => 'quarterly'], $quarterly);
        $this->assertSame(['fields' => [], 'hints' => []], app(ContractTextAnalyzer::class)->leasing('Protokoll ohne Angaben.'));
    }

    public function test_document_prefills_the_leasing_dialog(): void {
        $document = app(DocumentService::class)->create(null, $this->admin, ['title' => 'Leasing Bagger', 'document_type' => DocumentType::Other->value], UploadedFile::fake()->createWithContent('leasing.txt', self::TEXT));

        $this->actingAs($this->admin)->get(route('documents.show', $document))->assertOk()->assertSee(route('asset-finance.create', ['document' => $document->sqid]), false);
        $this->actingAs($this->admin)->get(route('asset-finance.create', ['document' => $document->sqid]))->assertOk()
            ->assertSee('value="489.90"', false)
            ->assertSee('value="5000.00"', false)
            ->assertSee('value="2029-09-30"', false)
            ->assertSee('Leasing Bagger');
    }
}

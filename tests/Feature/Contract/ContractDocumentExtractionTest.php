<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractDocumentExtractionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Enums\Document\DocumentType;
use App\Models\Document\Document;
use App\Models\Platform\User;
use App\Services\Document\{ContractTextAnalyzer, DocumentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-906: Vertragsangaben aus einem Dokument vorschlagen, Übernahme erst beim Speichern. */
final class ContractDocumentExtractionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const TEXT = "Wartungsvertrag Heizungsanlage\n§ 2 Vertragsbeginn: 01.10.2026\n§ 3 Die Mindestlaufzeit beträgt 24 Monate. Der Vertrag verlängert sich stillschweigend um jeweils ein Jahr, wenn er nicht mit einer Frist von drei Monaten gekündigt wird.\n§ 4 Die Vergütung beträgt 1.250,00 EUR monatlich zzgl. USt.";

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function document(string $content): Document {
        return app(DocumentService::class)->create(null, $this->admin, ['title' => 'Wartungsvertrag Müller', 'document_type' => DocumentType::Other->value], UploadedFile::fake()->createWithContent('vertrag.txt', $content));
    }

    public function test_analyzer_recognises_common_clauses(): void {
        $fields = app(ContractTextAnalyzer::class)->analyze(self::TEXT)['fields'];

        $this->assertSame(['starts_on' => '2026-10-01', 'min_term_months' => 24, 'notice_period_days' => 90, 'renew_period_months' => 12, 'auto_renew' => true, 'value_amount' => '1250.00', 'value_period' => 'monthly', 'term_kind' => 'fixed'], $fields);
        $this->assertSame(['fields' => [], 'hints' => []], app(ContractTextAnalyzer::class)->analyze('Protokoll ohne Vertragsangaben.'));
        $this->assertSame(365, app(ContractTextAnalyzer::class)->analyze('Kündigungsfrist: 365 Tage')['fields']['notice_period_days']);
    }

    public function test_document_prefills_the_contract_dialog_with_hints(): void {
        $document = $this->document(self::TEXT);

        $this->actingAs($this->admin)->get(route('documents.show', $document))->assertOk()->assertSee(route('contracts.create', ['document' => $document->sqid]), false);

        $this->actingAs($this->admin)->get(route('contracts.create', ['document' => $document->sqid]))
            ->assertOk()
            ->assertSee(__('contract.extraction.title', ['document' => 'Wartungsvertrag Müller']))
            ->assertSee('value="2026-10-01"', false)
            ->assertSee('value="1250.00"', false)
            ->assertSee('name="notice_period_days"', false)
            ->assertSee('value="90"', false)
            ->assertSee('value="' . $document->sqid . '" selected', false);

        $unreadable = $this->document('');
        $this->actingAs($this->admin)->get(route('contracts.create', ['document' => $unreadable->sqid]))->assertOk()->assertSee(__('contract.extraction.none'));
    }
}

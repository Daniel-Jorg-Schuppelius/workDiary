<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportColumnMappingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Import\{ImportEntity, ImportRunState};
use App\Models\Customer\Customer;
use App\Models\Integration\{ImportColumnMapping, ImportRun};
use App\Models\Platform\{Organization, User};
use App\Services\Import\DirectCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1020: Spaltenzuordnung einmal speichern, in jedem weiteren Import nutzen. */
final class ImportColumnMappingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        $this->admin = $this->orgAdmin();
    }

    private function preflight(string $csv, string $name = 'kunden.csv'): ImportRun {
        $this->actingAs($this->admin)->post(route('admin.imports.preflight'), [
            'entity' => 'customers',
            'match_policy' => 'auto_create',
            'file' => UploadedFile::fake()->createWithContent($name, $csv),
        ])->assertRedirect();

        return ImportRun::query()->latest('id')->firstOrFail();
    }

    public function test_saved_mapping_rechecks_the_file_and_applies_to_every_later_import(): void {
        $run = $this->preflight("Bezeichnung;Kundennummer;Postfach\nACME;K-1;info@acme.example\n");
        $this->assertSame(ImportRunState::Failed, $run->state);

        $this->actingAs($this->admin)->get(route('admin.imports.show', $run))->assertOk()
            ->assertSee(__('import.columns.title'))->assertSee('Bezeichnung')->assertSee('Postfach');

        $this->actingAs($this->admin)->post(route('admin.imports.columns', $run), ['columns' => [
            ['header' => 'Bezeichnung', 'target' => 'name'],
            ['header' => 'Postfach', 'target' => 'email'],
        ]])->assertRedirect()->assertSessionHas('success');

        $this->assertNull(ImportRun::query()->find($run->id));
        $fresh = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::AwaitingApproval, $fresh->state);
        $this->assertSame('kunden.csv', $fresh->input_filename);
        $this->assertSame('ACME', $fresh->preview[0]['data']['name'] ?? null);
        $this->assertSame('info@acme.example', $fresh->preview[0]['data']['email'] ?? null);
        $this->assertSame(['bezeichnung' => 'name', 'postfach' => 'email'], ImportColumnMapping::aliasesFor((int) $this->organization->id, ImportEntity::Customers));
        $this->actingAs($this->admin)->get(route('admin.imports.show', $fresh))->assertOk()->assertDontSee(__('import.columns.title'));

        // Nächste Datei derselben Importart: kein Zuordnungsschritt mehr, auch nicht im Direktimport.
        $this->assertSame(ImportRunState::AwaitingApproval, $this->preflight("BEZEICHNUNG;Postfach\nBeta;beta@example.org\n")->state);
        $result = app(DirectCsvImportService::class)->import(
            UploadedFile::fake()->createWithContent('direkt.csv', "Bezeichnung;Postfach\nGamma;gamma@example.org\n"),
            ImportEntity::Customers,
            $this->organization,
        );
        $this->assertSame(1, $result['created'], implode(' | ', $result['errors']));
        $this->assertSame('gamma@example.org', Customer::query()->where('name', 'Gamma')->sole()->email);

        // Verwalten: Dialog listet die Zuordnungen, Löschen entfernt eine.
        $this->actingAs($this->admin)->get(route('admin.imports.column-mappings'))->assertOk()
            ->assertSee('bezeichnung')->assertSee(ImportEntity::Customers->label());
        $mapping = ImportColumnMapping::query()->where('source_header', 'postfach')->sole();
        $this->actingAs($this->admin)->delete(route('admin.imports.column-mappings.destroy', $mapping))->assertRedirect();
        $this->assertSame(['bezeichnung' => 'name'], ImportColumnMapping::aliasesFor((int) $this->organization->id, ImportEntity::Customers));
    }

    public function test_invalid_mappings_are_refused_and_the_run_stays(): void {
        $run = $this->preflight("Bezeichnung;Kundennummer;Postfach\nACME;K-1;x\n");

        $this->actingAs($this->admin)->post(route('admin.imports.columns', $run), ['columns' => [
            ['header' => 'Bezeichnung', 'target' => 'name'],
            ['header' => 'Postfach', 'target' => 'name'],
        ]])->assertRedirect(route('admin.imports.show', $run))->assertSessionHas('error', __('import.columns.error.duplicate'));

        // „number" ist über „Kundennummer" schon belegt, „Kundennummer" selbst ist keine offene Kopfzelle.
        $this->actingAs($this->admin)->post(route('admin.imports.columns', $run), ['columns' => [
            ['header' => 'Postfach', 'target' => 'number'],
            ['header' => 'Kundennummer', 'target' => 'email'],
        ]])->assertSessionHas('error', __('import.columns.error.none'));

        $this->assertSame(0, ImportColumnMapping::query()->count());
        $this->assertNotNull(ImportRun::query()->find($run->id));

        $viewer = $this->orgUser();
        $this->actingAs($viewer)->post(route('admin.imports.columns', $run), ['columns' => [['header' => 'Bezeichnung', 'target' => 'name']]])->assertForbidden();
    }

    public function test_mappings_of_other_organizations_stay_out_of_reach(): void {
        $other = Organization::factory()->create();
        $foreign = ImportColumnMapping::query()->create([
            'organization_id' => $other->id, 'entity' => ImportEntity::Customers, 'source_header' => 'bezeichnung', 'target_column' => 'name',
        ]);

        $this->assertSame(ImportRunState::Failed, $this->preflight("Bezeichnung\nACME\n")->state);
        $this->actingAs($this->admin)->get(route('admin.imports.column-mappings'))->assertOk()->assertSee(__('import.columns.saved_empty'));
        $this->actingAs($this->admin)->delete(route('admin.imports.column-mappings.destroy', $foreign->sqid))->assertNotFound();
        $this->assertNotNull($foreign->fresh());
    }
}

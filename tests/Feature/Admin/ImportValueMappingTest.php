<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Import\ImportRunState;
use App\Models\{Classification, Customer, ImportRun, ImportValueMapping, Organization, Tag, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rang 58: Tag-/Kategorie-Mapping im CSV-Import — Preflight sammelt
 * unbekannte Werte, Confirm blockt bis zur Zuordnung, Wiederholimporte
 * nutzen das Mapping (keine Blind-Neuanlage).
 *
 * A13: Klassifikations-Ziele — Quellwerte können org-gescoped auf
 * Klassifikationen gemappt werden; eindeutige Klassifikations-Codes
 * (CODE_REGEX) lösen automatisch auf.
 */
class ImportValueMappingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function preflight(string $csv): ImportRun {
        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);
        $this->actingAs($this->admin)
            ->post(route('admin.imports.preflight'), [
                'entity' => 'customers',
                'match_policy' => 'auto_create',
                'file' => $file,
            ])->assertRedirect();

        return ImportRun::query()->latest('id')->firstOrFail();
    }

    public function test_preflight_collects_unknown_tags_and_confirm_blocks(): void {
        // 'VIP' existiert bereits (case-insensitiver Namens-Treffer) → nur 'Wartung' ist unbekannt.
        Tag::query()->create(['organization_id' => $this->organization->id, 'name' => 'vip']);

        $run = $this->preflight("name;number;tags\nACME;K-1;Wartung, VIP\n");

        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);
        $this->assertSame(['tags' => ['Wartung']], $run->unresolved_values);

        // Confirm blockt mit Hinweis, solange Werte offen sind.
        $this->actingAs($this->admin)
            ->post(route('admin.imports.confirm', $run))
            ->assertRedirect(route('admin.imports.show', $run));
        $this->assertSame(ImportRunState::AwaitingApproval, $run->refresh()->state);
    }

    public function test_mapping_persists_and_reimport_resolves_automatically(): void {
        Tag::query()->create(['organization_id' => $this->organization->id, 'name' => 'vip']);

        $run = $this->preflight("name;number;tags\nACME;K-1;Wartung, VIP\n");

        // Zuordnen: 'Wartung' als neues Tag anlegen.
        $this->actingAs($this->admin)
            ->post(route('admin.imports.mapping', $run), [
                'mappings' => [
                    ['value' => 'Wartung', 'action' => 'new'],
                ],
            ])->assertRedirect(route('admin.imports.show', $run));

        $this->assertNull($run->refresh()->unresolved_values);
        $this->assertDatabaseHas('import_value_mappings', [
            'organization_id' => $this->organization->id,
            'entity' => 'customers',
            'source_value' => 'wartung',
            'target_kind' => ImportValueMapping::KIND_TAG,
        ]);

        // Import läuft durch und hängt beide Tags an den Kunden.
        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $run))->assertRedirect();

        $customer = Customer::query()->where('number', 'K-1')->firstOrFail();
        $tagNames = $customer->tags()->pluck('name')->map(fn ($n) => mb_strtolower((string) $n))->sort()->values()->all();
        $this->assertSame(['vip', 'wartung'], $tagNames);

        // Wiederholimport: kein offener Mapping-Schritt mehr, keine Tag-Duplikate.
        $rerun = $this->preflight("name;number;tags\nACME;K-1;Wartung, VIP\n");
        $this->assertNull($rerun->unresolved_values);
        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $rerun))->assertRedirect();

        $this->assertSame(1, Tag::query()->whereRaw('LOWER(name) = ?', ['wartung'])->count(), 'Keine Blind-Neuanlage beim Wiederholimport.');
        $this->assertCount(2, $customer->refresh()->tags);
    }

    public function test_ignore_mapping_skips_value(): void {
        $run = $this->preflight("name;number;tags\nACME;K-1;Altsystem-Müll\n");

        $this->actingAs($this->admin)
            ->post(route('admin.imports.mapping', $run), [
                'mappings' => [
                    ['value' => 'Altsystem-Müll', 'action' => 'ignore'],
                ],
            ])->assertRedirect();

        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $run))->assertRedirect();

        $customer = Customer::query()->where('number', 'K-1')->firstOrFail();
        $this->assertCount(0, $customer->tags);
        $this->assertSame(0, Tag::query()->count());
    }

    // ── A13: Klassifikations-Ziele ────────────────────────────────────────────

    public function test_mapping_to_classification_persists_and_import_assigns(): void {
        $classification = Classification::factory()->create([
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::Activity->value,
            'code' => 'wartung_intern',
            'label' => 'Wartung intern',
        ]);

        $run = $this->preflight("name;number;tags\nACME;K-1;Wartung\n");
        $this->assertSame(['tags' => ['Wartung']], $run->unresolved_values);

        // Klassifikations-Option wird im Formular angeboten (Entität trägt Klassifikationen).
        $this->actingAs($this->admin)
            ->get(route('admin.imports.show', $run))
            ->assertOk()
            ->assertSee(__('Klassifikation zuordnen'))
            ->assertSee('Wartung intern');

        $this->actingAs($this->admin)
            ->post(route('admin.imports.mapping', $run), [
                'mappings' => [
                    ['value' => 'Wartung', 'action' => 'classification', 'classification_id' => $classification->sqid],
                ],
            ])->assertRedirect(route('admin.imports.show', $run));

        $this->assertNull($run->refresh()->unresolved_values);
        $this->assertDatabaseHas('import_value_mappings', [
            'organization_id' => $this->organization->id,
            'entity' => 'customers',
            'source_value' => 'wartung',
            'target_kind' => ImportValueMapping::KIND_CLASSIFICATION,
            'classification_id' => $classification->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $run))->assertRedirect();

        $customer = Customer::query()->where('number', 'K-1')->firstOrFail();
        $this->assertTrue($customer->classifications()->whereKey($classification->id)->exists());

        // Wiederholimport: Mapping löst automatisch auf, Zuordnung bleibt idempotent.
        $rerun = $this->preflight("name;number;tags\nACME;K-1;Wartung\n");
        $this->assertNull($rerun->unresolved_values);
        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $rerun))->assertRedirect();
        $this->assertSame(1, $customer->refresh()->classifications()->count());
    }

    public function test_unique_classification_code_resolves_automatically(): void {
        // CODE_REGEX-Mechanik: Quellwert 'wartung' ist ein gültiger Code und
        // zeigt org-sichtbar auf genau eine aktive Klassifikation.
        $classification = Classification::factory()->create([
            'organization_id' => null, // Plattform-Default ist org-sichtbar
            'domain' => ClassificationDomain::Activity->value,
            'code' => 'wartung',
            'label' => 'Wartung',
        ]);

        $run = $this->preflight("name;number;tags\nACME;K-1;wartung\n");

        $this->assertNull($run->unresolved_values, 'Eindeutiger Klassifikations-Code darf nicht im Mapping-Formular landen.');

        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $run))->assertRedirect();

        $customer = Customer::query()->where('number', 'K-1')->firstOrFail();
        $this->assertTrue($customer->classifications()->whereKey($classification->id)->exists());
        $this->assertSame(0, Tag::query()->count(), 'Kein Blind-Tag für Klassifikations-Codes.');
    }

    public function test_ambiguous_classification_code_stays_unresolved(): void {
        // Gleicher Code in ZWEI Domänen → nicht deterministisch → Mapping-Formular.
        Classification::factory()->create([
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::Activity->value,
            'code' => 'wartung',
        ]);
        Classification::factory()->create([
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::EntryType->value,
            'code' => 'wartung',
        ]);

        $run = $this->preflight("name;number;tags\nACME;K-1;wartung\n");

        $this->assertSame(['tags' => ['wartung']], $run->unresolved_values);
    }

    public function test_foreign_org_classification_is_rejected(): void {
        $otherOrg = Organization::factory()->create();
        $foreign = Classification::factory()->create([
            'organization_id' => $otherOrg->id,
            'domain' => ClassificationDomain::Activity->value,
            'code' => 'fremd_code',
            'label' => 'Fremde Klassifikation',
        ]);

        $run = $this->preflight("name;number;tags\nACME;K-1;Wartung\n");

        $this->actingAs($this->admin)
            ->post(route('admin.imports.mapping', $run), [
                'mappings' => [
                    ['value' => 'Wartung', 'action' => 'classification', 'classification_id' => $foreign->sqid],
                ],
            ])->assertRedirect();

        // Fremd-Org-Klassifikation wird abgelehnt: Wert bleibt offen, kein Mapping.
        $this->assertSame(['tags' => ['Wartung']], $run->refresh()->unresolved_values);
        $this->assertDatabaseMissing('import_value_mappings', [
            'organization_id' => $this->organization->id,
            'source_value' => 'wartung',
        ]);

        // Confirm blockt weiterhin.
        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $run))->assertRedirect();
        $this->assertSame(ImportRunState::AwaitingApproval, $run->refresh()->state);
    }
}

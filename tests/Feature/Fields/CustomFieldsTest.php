<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomFieldsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Fields;

use App\Models\Article\Article;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Fields\{CustomFieldDefinition, CustomFieldValue};
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Fields\CustomFieldService;
use App\Services\Fields\Install\CustomFieldInstallStep;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Benutzerdefinierte Felder je Organisation (MVP-868). */
class CustomFieldsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->actingAs($this->admin);
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array {
        return [
            ['label' => 'Kundennummer EVU', 'type' => 'text', 'required' => '1'],
            ['label' => 'Zählerstand', 'type' => 'number', 'unit' => 'kWh', 'min' => '0'],
            ['label' => 'Segment', 'type' => 'choice', 'options' => 'Privat, Gewerbe'],
            ['label' => 'Newsletter', 'type' => 'boolean'],
        ];
    }

    public function test_admin_defines_fields_per_subject_and_versions_the_schema(): void {
        $this->get(route('admin.custom-fields.index'))->assertOk()->assertSee(__('fields.custom.none'));

        $this->put(route('admin.custom-fields.update', 'customers'), ['fields' => $this->rows()])
            ->assertRedirect(route('admin.custom-fields.index'));
        $definition = CustomFieldDefinition::query()->where('subject_alias', 'customers')->firstOrFail();
        $this->assertSame(['kundennummer_evu', 'zahlerstand', 'segment', 'newsletter'], $definition->schema->keys());
        $this->assertSame(1, $definition->version);
        $this->assertTrue($definition->is_active);

        // Unveränderte Zeilen: Version bleibt; geänderte: Version zählt hoch.
        $this->put(route('admin.custom-fields.update', 'customers'), ['fields' => $this->rows()]);
        $this->assertSame(1, $definition->refresh()->version);
        $this->put(route('admin.custom-fields.update', 'customers'), ['fields' => [...$this->rows(), ['label' => 'Rabattstufe', 'type' => 'scale', 'min' => '1', 'max' => '3']]]);
        $this->assertSame(2, $definition->refresh()->version);

        $this->put(route('admin.custom-fields.update', 'customers'), ['fields' => [['label' => 'Foto', 'type' => 'photo']]])
            ->assertSessionHasErrors('fields');
        $this->put(route('admin.custom-fields.update', 'invoices'), ['fields' => $this->rows()])->assertSessionHasErrors('subject');

        $this->patch(route('admin.custom-fields.toggle', 'customers'))->assertRedirect();
        $this->assertFalse($definition->refresh()->is_active);
        $this->assertTrue(app(CustomFieldService::class)->schemaFor(Customer::class, (int) $this->organization->id)->isEmpty(), 'Deaktiviert = kein Schema im Formular.');

        $this->get(route('admin.custom-fields.edit', 'customers'))->assertOk()->assertSee('Kundennummer EVU');
        $this->get(route('admin.custom-fields.edit', 'invoices'))->assertNotFound();
    }

    public function test_only_org_admins_with_the_permission_and_only_their_own_definitions(): void {
        $member = $this->orgUser();
        $this->actingAs($member)->get(route('admin.custom-fields.index'))->assertForbidden();
        $this->actingAs($member)->put(route('admin.custom-fields.update', 'customers'), ['fields' => $this->rows()])->assertForbidden();

        $other = Organization::factory()->create();
        app(CustomFieldService::class)->saveDefinition($other, 'customers', $this->rows());
        $this->assertTrue(app(CustomFieldService::class)->schemaFor(Customer::class, (int) $this->organization->id)->isEmpty(), 'Fremde Definitionen sind unsichtbar.');
        $this->actingAs($this->admin)->get(route('admin.custom-fields.index'))->assertOk()->assertSee(__('fields.custom.none'));
    }

    public function test_customer_form_validates_and_stores_custom_values_round_trip(): void {
        app(CustomFieldService::class)->saveDefinition($this->organization, 'customers', $this->rows());

        $this->get(route('customers.create'))->assertOk()->assertSee('custom[kundennummer_evu]', false);

        $invalid = $this->post(route('customers.store'), ['name' => 'Muster GmbH', 'custom' => ['zahlerstand' => '-5', 'segment' => 'Verein']]);
        $invalid->assertSessionHasErrors(['custom.kundennummer_evu', 'custom.zahlerstand', 'custom.segment']);
        $this->assertSame(0, Customer::query()->count());

        $this->post(route('customers.store'), ['name' => 'Muster GmbH', 'custom' => ['kundennummer_evu' => 'EVU-4711', 'zahlerstand' => '1234.5', 'segment' => 'Gewerbe', 'newsletter' => '1']])
            ->assertRedirect();
        $customer = Customer::query()->firstOrFail();
        $values = $customer->customValues();
        $this->assertSame('EVU-4711', $values->get('kundennummer_evu'));
        $this->assertSame(1234.5, $values->get('zahlerstand'));
        $this->assertTrue($values->get('newsletter'));
        $this->assertSame(1, $customer->customFieldValue?->schema_version);
        $this->assertSame(['EVU-4711', '1.234,5 kWh', 'Gewerbe', (string) __('fields.value.yes')], $customer->customFieldTexts());

        $this->get(route('customers.show', $customer))->assertOk()->assertSee('EVU-4711')->assertSee('1.234,5 kWh');

        $this->put(route('customers.update', $customer), ['name' => 'Muster GmbH', 'custom' => ['kundennummer_evu' => 'EVU-0815', 'segment' => 'Privat']])->assertRedirect();
        $this->assertSame('EVU-0815', $customer->fresh()?->customValues()->get('kundennummer_evu'));
        $this->assertSame(1, CustomFieldValue::query()->count(), 'Genau ein Wertesatz je Datensatz.');
    }

    public function test_every_subject_syncs_and_reads_values(): void {
        $service = app(CustomFieldService::class);
        $subjects = [
            [DiaryEntry::class, fn (): DiaryEntry => DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->organization->id])],
            [Asset::class, fn (): Asset => Asset::factory()->create(['organization_id' => $this->organization->id])],
            [Article::class, fn (): Article => Article::factory()->create(['organization_id' => $this->organization->id])],
            [Project::class, fn (): Project => Project::factory()->create(['organization_id' => $this->organization->id])],
        ];
        foreach ($subjects as [$class, $make]) {
            $service->saveDefinition($this->organization, MorphMap::alias($class), [['label' => 'Kostenstelle', 'type' => 'text'], ['label' => 'Prio', 'type' => 'scale', 'min' => '1', 'max' => '3']]);
            $model = $make();
            $model->syncCustomFields(['kostenstelle' => 'KST-100', 'prio' => '2']);
            $fresh = $model->fresh();
            $this->assertNotNull($fresh);
            $this->assertSame('KST-100', $fresh->customValues()->get('kostenstelle'), $class);
            $this->assertSame(2, $fresh->customValues()->get('prio'), $class);
            $this->assertSame(['KST-100', '2 / 3'], $fresh->customFieldTexts(), $class);
            $this->assertSame(MorphMap::alias($class), $fresh->customFieldValue?->subject_type);
        }
        $this->assertSame(4, CustomFieldValue::query()->count());
    }

    public function test_branch_profile_step_adds_missing_fields_without_overwriting_own_ones(): void {
        $service = app(CustomFieldService::class);
        $service->saveDefinition($this->organization, 'assets', [['label' => 'Inventarnummer alt', 'type' => 'text']]);
        $result = app(CustomFieldInstallStep::class)->install($this->organization, [
            'assets' => [['label' => 'Inventarnummer alt', 'type' => 'number'], ['label' => 'Prüfintervall', 'type' => 'number', 'unit' => 'Monate']],
            'customers' => [['label' => 'Kundennummer EVU', 'type' => 'text']],
            'invoices' => [['label' => 'Egal', 'type' => 'text']],
        ], $this->admin);
        $this->assertSame(['created' => 2, 'skipped' => 2], $result);
        $assets = $service->definition((int) $this->organization->id, 'assets');
        $this->assertNotNull($assets);
        $this->assertSame('text', $assets->schema->get('inventarnummer_alt')?->type->value, 'Eigenes Feld bleibt unverändert.');
        $this->assertSame('Monate', $assets->schema->get('prufintervall')?->unit);
        $this->assertContains(CustomFieldInstallStep::class, array_map(static fn (object $step): string => $step::class, (fn () => $this->steps())->call(app(BranchProfileInstaller::class))));
    }

    public function test_diary_export_and_search_carry_custom_values(): void {
        app(CustomFieldService::class)->saveDefinition($this->organization, 'diary_entries', [['label' => 'Auftragsnummer', 'type' => 'text']]);
        // Export gilt für den aktuellen Datumsbereich; die Factory streut ±1 Monat.
        $entry = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->organization->id, 'start_at' => now(), 'end_at' => now()->addHour()]);
        $entry->syncCustomFields(['auftragsnummer' => 'A-2026-99']);

        $csv = $this->actingAs($this->admin)->get(route('diary.export.csv'));
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('Auftragsnummer', $content);
        $this->assertStringContainsString('A-2026-99', $content);

        $document = app(\App\Services\Diary\Search\DiaryEntrySource::class)->build($entry->fresh() ?? $entry, app(\App\Services\Search\Indexing\SearchContext::class));
        $this->assertNotNull($document);
        $this->assertContains('A-2026-99', $document->texts);
    }

    public function test_listed_fields_appear_as_columns_and_are_capped(): void {
        $service = app(CustomFieldService::class);
        $service->saveDefinition($this->organization, 'customers', [
            ['label' => 'Kostenstelle', 'type' => 'text', 'listed' => '1'],
            ['label' => 'Intern', 'type' => 'text'],
        ]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Listenkunde']);
        $customer->syncCustomFields(['kostenstelle' => 'KST-4711', 'intern' => 'nicht sichtbar']);

        $this->assertSame(['kostenstelle'], array_map(static fn ($field): string => $field->key, $service->listColumns(Customer::class, (int) $this->organization->id)));
        $this->actingAs($this->admin)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Kostenstelle')
            ->assertSee('KST-4711')
            ->assertDontSee('nicht sichtbar');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->saveDefinition($this->organization, 'customers', array_map(
            static fn (int $i): array => ['label' => 'Feld ' . $i, 'type' => 'text', 'listed' => '1'],
            range(1, CustomFieldService::MAX_LIST_COLUMNS + 1),
        ));
    }
}

<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolTemplateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\{ProtocolItemType, ProtocolType};
use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Protocol\{Protocol, ProtocolItem, ProtocolTemplate};
use App\Services\Protocol\Fields\ProtocolItemFields;
use App\Services\Protocol\Install\ProtocolTemplateInstallStep;
use App\Services\Protocol\{ProtocolService, ProtocolTemplateService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** MVP-901: Protokollvorlagen aus einem Musterprotokoll, Auswahl nach Zuordnung. */
class ProtocolTemplateTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    private DiaryEntry $entry;

    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->admin->organization_id);
        $this->entry = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->admin->organization_id]);
    }

    private function sampleProtocol(): Protocol {
        $service = app(ProtocolService::class);
        $protocol = $service->create($this->entry, $this->admin, ['title' => 'Muster Wartung', 'type' => ProtocolType::Maintenance->value]);
        $group = $service->addItem($protocol, $this->admin, ['label' => 'Brenner', 'item_type' => ProtocolItemType::Group->value]);
        $choice = $service->addItem($protocol, $this->admin, [
            'label' => 'Zustand', 'item_type' => ProtocolItemType::Choice->value, 'required' => true, 'parent_item_id' => $group->id,
            'value_json' => ['options' => [['key' => 'gut', 'label' => 'Gut'], ['key' => 'defekt', 'label' => 'Defekt']]],
        ]);
        $service->fillItem($choice, $this->admin, ['value_json' => ['selected' => 'defekt']]);

        return $protocol;
    }

    public function test_protocol_is_saved_as_template_without_recorded_values(): void {
        $protocol = $this->sampleProtocol();

        $this->actingAs($this->admin)->get(route('protocols.as-template.form', $protocol))->assertOk();
        $this->actingAs($this->admin)->post(route('protocols.as-template', $protocol), ['name' => 'Heizungswartung'])
            ->assertRedirect(route('protocols.show', $protocol));

        $template = ProtocolTemplate::query()->where('name', 'Heizungswartung')->firstOrFail();
        $this->assertSame(ProtocolType::Maintenance, $template->kind);
        $this->assertSame('Brenner', $template->items[0]['label']);
        $child = $template->items[0]['children'][0];
        $this->assertSame('Zustand', $child['label']);
        $this->assertTrue($child['required']);
        $this->assertSame(['options' => [['key' => 'gut', 'label' => 'Gut'], ['key' => 'defekt', 'label' => 'Defekt']]], $child['config']);
        $this->assertArrayNotHasKey('selected', $child['config']);
    }

    public function test_new_protocol_from_template_copies_items_and_remembers_version(): void {
        $template = app(ProtocolTemplateService::class)->fromProtocol($this->sampleProtocol(), ['name' => 'Heizungswartung'], $this->admin);

        $this->actingAs($this->admin)
            ->get(route('protocols.create', ['subject_kind' => 'diary', 'subject' => $this->entry->sqid]))
            ->assertOk()
            ->assertSee('Heizungswartung');

        $this->actingAs($this->admin)->post(route('protocols.store'), [
            'subject_kind' => 'diary',
            'subject_id' => $this->entry->sqid,
            'type' => ProtocolType::Maintenance->value,
            'title' => 'Wartung Familie Muster',
            'template_id' => $template->sqid,
        ])->assertSessionHasNoErrors();

        $protocol = Protocol::query()->where('title', 'Wartung Familie Muster')->firstOrFail();
        $this->assertSame($template->id, (int) $protocol->template_id);
        $this->assertSame(1, (int) $protocol->template_version);
        $group = $protocol->items()->whereNull('parent_item_id')->firstOrFail();
        $child = ProtocolItem::query()->where('parent_item_id', $group->id)->firstOrFail();
        $this->assertSame('Zustand', $child->label);
        $this->assertNull(app(ProtocolItemFields::class)->value($child), 'Werte der Vorlage werden nicht übernommen');
        $this->assertCount(2, $child->value_json['options']);

        // Fortschreiben erhöht die Version; das bestehende Protokoll bleibt unverändert.
        $this->actingAs($this->admin)->post(route('protocols.as-template', $protocol), ['name' => 'egal', 'update_existing' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(2, $template->fresh()->version);
        $this->assertSame(1, (int) $protocol->fresh()->template_version);
    }

    public function test_templates_are_offered_by_entry_type_customer_and_validity(): void {
        $org = $this->admin->organization_id;
        $entryType = EntryType::query()->create(['organization_id' => $org, 'slug' => 'wartung', 'label' => 'Wartung']);
        $otherCustomer = Customer::create(['organization_id' => $org, 'name' => 'Andere GmbH', 'created_by' => $this->admin->id]);
        $this->entry->forceFill(['entry_type_id' => $entryType->id])->save();

        ProtocolTemplate::factory()->create(['organization_id' => $org, 'name' => 'Überall']);
        ProtocolTemplate::factory()->create(['organization_id' => $org, 'name' => 'Nur Wartung', 'entry_type_id' => $entryType->id]);
        ProtocolTemplate::factory()->create(['organization_id' => $org, 'name' => 'Anderer Kunde', 'customer_id' => $otherCustomer->id]);
        ProtocolTemplate::factory()->create(['organization_id' => $org, 'name' => 'Abgelaufen', 'valid_until' => now()->subDay()->toDateString()]);
        ProtocolTemplate::factory()->create(['organization_id' => $org, 'name' => 'Inaktiv', 'is_active' => false]);
        ProtocolTemplate::factory()->create(['name' => 'Fremde Organisation']);
        $this->actingAs($this->admin);

        $names = app(ProtocolTemplateService::class)->applicableFor($this->entry->fresh())->pluck('name')->all();

        $this->assertSame(['Nur Wartung', 'Überall'], $names);
    }

    public function test_admin_list_edit_and_branch_profile_install(): void {
        $org = Organization::query()->findOrFail($this->admin->organization_id);
        $step = app(ProtocolTemplateInstallStep::class);
        $rows = [['name' => 'Abnahme Bad', 'kind' => 'acceptance', 'items' => [['label' => 'Fugen dicht', 'item_type' => 'boolean']]]];

        $this->assertSame(['created' => 1, 'skipped' => 0], $step->install($org, $rows, $this->admin));
        $this->assertSame(['created' => 0, 'skipped' => 1], $step->install($org, $rows, $this->admin));

        $template = ProtocolTemplate::query()->where('name', 'Abnahme Bad')->firstOrFail();
        $this->actingAs($this->admin)->get(route('protocol-templates.index'))->assertOk()->assertSee('Abnahme Bad');
        $this->actingAs($this->admin)->get(route('protocol-templates.edit', $template))->assertOk();
        $this->actingAs($this->admin)->put(route('protocol-templates.update', $template), ['name' => 'Abnahme Badezimmer', 'is_active' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($template->fresh()->is_active);
        $this->assertSame('Abnahme Badezimmer', $template->fresh()->name);
    }

    public function test_catalog_templates_of_all_branch_profiles_install_and_fill_a_protocol(): void {
        $catalog = require database_path('data/protocol_templates.php');
        foreach ($catalog as $code => $template) {
            $this->assertNotNull(ProtocolType::tryFrom($template['kind']), $code);
            array_walk_recursive($template['items'], static function (mixed $value, string|int $key) use ($code): void {
                if ($key === 'item_type') {
                    self::assertNotNull(ProtocolItemType::tryFrom((string) $value), $code . ': ' . $value);
                }
            });
        }

        $codes = [];
        foreach (glob(database_path('data/branchprofiles/*.php')) ?: [] as $file) {
            $profile = require $file;
            foreach ($profile['protocol_templates'] ?? [] as $row) {
                $codes[] = $row['code'];
            }
        }
        $this->assertSame([], array_values(array_diff($codes, array_keys($catalog))), 'jeder Profilcode steht im Katalog');

        $org = Organization::query()->findOrFail($this->admin->organization_id);
        $result = app(ProtocolTemplateInstallStep::class)->install($org, [['code' => 'EL_PRUEFPROTOKOLL'], ['code' => 'UNBEKANNT']], $this->admin);
        $this->assertSame(['created' => 1, 'skipped' => 1], $result);

        $template = ProtocolTemplate::query()->where('name', 'Prüfprotokoll elektrische Anlage')->firstOrFail();
        $protocol = app(ProtocolService::class)->create($this->entry, $this->admin, ['title' => 'E-Check', 'type' => ProtocolType::Inspection->value, 'template_id' => $template->id]);
        $measurements = $protocol->items()->where('label', 'Messungen')->firstOrFail();
        $this->assertSame(4, ProtocolItem::query()->where('parent_item_id', $measurements->id)->count());
        $this->assertSame('MΩ', ProtocolItem::query()->where('label', 'Isolationswiderstand')->firstOrFail()->value_json['unit']);
    }

    public function test_user_without_right_cannot_manage_templates(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);

        $this->actingAs($user)->get(route('protocol-templates.index'))->assertForbidden();
    }
}

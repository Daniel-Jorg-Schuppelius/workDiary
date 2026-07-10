<?php
/*
 * Created on   : Thu Jul 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntryTypeFlowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Customer, EntryType, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-End-Fluss Auftragstyp → Auftragsformular: Ein über die
 * Admin-UI angelegter Typ (nicht Factory!) muss im Auftragsformular
 * angeboten werden, seine Kunden-Pflicht muss das Kundenfeld
 * einblenden (Flags-Map) UND serverseitig erzwungen werden.
 */
class DiaryEntryTypeFlowTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * Typ wie über das Admin-Formular anlegen (Checkbox-Rohwerte).
     *
     * @param array<string, mixed> $overrides
     */
    private function createTypeViaAdminUi(array $overrides = []): EntryType {
        $this->actingAs($this->admin)
            ->post(route('admin.entry-types.store'), $overrides + [
                'slug' => 'kundendienst',
                'label' => 'Kundendienst',
                'icon' => 'build',
                'color' => 'primary',
                'sort' => 10,
                'is_active' => '1',
                'requires_customer' => '1',
                'default_status' => 2,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.entry-types.index'));

        return EntryType::query()->where('slug', $overrides['slug'] ?? 'kundendienst')->firstOrFail();
    }

    public function test_admin_created_type_is_offered_in_diary_form_with_customer_flag(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'Musterkunde GmbH',
        ]);
        $type = $this->createTypeViaAdminUi();

        $this->assertTrue($type->requires_customer, 'Kunden-Pflicht muss aus dem Admin-Formular persistiert werden.');
        $this->assertTrue($type->is_active);
        $this->assertSame($this->admin->organization_id, $type->organization_id);

        $content = (string) $this->actingAs($this->admin)
            ->get(route('diary.create'))
            ->assertOk()
            ->getContent();

        // Typ wird im Typ-Select angeboten …
        $this->assertStringContainsString('Kundendienst', $content);
        $this->assertStringContainsString($type->sqid, $content);
        // … seine Flags-Map blendet das Kundenfeld ein …
        $this->assertStringContainsString('"requires_customer":true', $content);
        // … und die Kundenauswahl enthält die Kunden der Organisation.
        $this->assertStringContainsString('Musterkunde GmbH', $content);
        $this->assertStringContainsString($customer->sqid, $content);
    }

    public function test_store_enforces_customer_for_requiring_type(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->admin->organization_id]);
        $type = $this->createTypeViaAdminUi();

        // Ohne Kunde → Validierungsfehler auf customer_id.
        $this->actingAs($this->admin)
            ->post(route('diary.store'), [
                'content' => 'Ohne Kunde',
                'status' => 2,
                'start_at' => '2030-01-15 09:00:00',
                'end_at' => '2030-01-15 10:00:00',
                'entry_type_id' => $type->sqid,
            ])
            ->assertSessionHasErrors('customer_id');

        // Mit Kunde → gespeichert.
        $this->actingAs($this->admin)
            ->post(route('diary.store'), [
                'content' => 'Mit Kunde',
                'status' => 2,
                'start_at' => '2030-01-15 09:00:00',
                'end_at' => '2030-01-15 10:00:00',
                'entry_type_id' => $type->sqid,
                'customer_id' => $customer->sqid,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diary_entries', [
            'content' => 'Mit Kunde',
            'entry_type_id' => $type->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_inactive_type_is_not_offered_in_diary_form(): void {
        $type = $this->createTypeViaAdminUi(['slug' => 'ruhend', 'label' => 'Ruhender Typ', 'is_active' => '0']);

        $this->assertFalse($type->is_active);

        $this->actingAs($this->admin)
            ->get(route('diary.create'))
            ->assertOk()
            ->assertDontSee('Ruhender Typ');
    }

    public function test_foreign_org_customer_and_type_are_rejected(): void {
        $type = $this->createTypeViaAdminUi();
        $foreignAdmin = User::factory()->admin()->create();
        $foreignCustomer = Customer::factory()->create(['organization_id' => $foreignAdmin->organization_id]);
        $foreignType = EntryType::factory()->create([
            'organization_id' => $foreignAdmin->organization_id,
            'slug' => 'fremd',
            'label' => 'Fremder Typ',
        ]);

        // Kunde einer fremden Organisation → Cross-Tenant-Injection abgelehnt.
        $this->actingAs($this->admin)
            ->post(route('diary.store'), [
                'content' => 'Fremder Kunde',
                'status' => 2,
                'start_at' => '2030-01-15 09:00:00',
                'end_at' => '2030-01-15 10:00:00',
                'entry_type_id' => $type->sqid,
                'customer_id' => $foreignCustomer->sqid,
            ])
            ->assertSessionHasErrors('customer_id');

        // Fremder Auftragstyp ebenso.
        $this->actingAs($this->admin)
            ->post(route('diary.store'), [
                'content' => 'Fremder Typ',
                'status' => 2,
                'start_at' => '2030-01-15 09:00:00',
                'end_at' => '2030-01-15 10:00:00',
                'entry_type_id' => $foreignType->sqid,
            ])
            ->assertSessionHasErrors('entry_type_id');
    }

    public function test_customer_field_is_always_available_even_without_requiring_type(): void {
        Customer::factory()->create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'Optionalkunde KG',
        ]);

        // Kein Typ gewählt → Kundenfeld muss trotzdem angeboten werden
        // (Server erlaubt Kunde ohne fordernden Typ seit jeher).
        $content = (string) $this->actingAs($this->admin)
            ->get(route('diary.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="customer_id"', $content);
        $this->assertStringContainsString('Optionalkunde KG', $content);
    }

    public function test_inactive_type_stays_selectable_and_enforced_on_edit(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->admin->organization_id]);
        $type = $this->createTypeViaAdminUi();
        $entry = \App\Models\DiaryEntry::factory()->for($this->admin)->create([
            'entry_type_id' => $type->id,
            'customer_id' => $customer->id,
        ]);

        $type->update(['is_active' => false]);

        // Edit-Formular bietet den Ist-Typ weiterhin an (markiert als inaktiv)
        // und liefert seine Flags — kein stiller Rückfall auf „ohne Typ".
        $content = (string) $this->actingAs($this->admin)
            ->get(route('diary.edit', $entry))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($type->sqid, $content);
        $this->assertStringContainsString('(inaktiv)', $content);
        $this->assertStringContainsString('"requires_customer":true', $content);

        // Speichern mit unverändertem (inaktivem) Typ bleibt möglich.
        $this->actingAs($this->admin)
            ->put(route('diary.update', $entry), [
                'content' => 'Aktualisiert',
                'status' => 2,
                'start_at' => '2030-01-15 09:00:00',
                'end_at' => '2030-01-15 10:00:00',
                'entry_type_id' => $type->sqid,
                'customer_id' => $customer->sqid,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($type->id, $entry->refresh()->entry_type_id);
    }

    public function test_recurrence_rule_mirrors_type_requirements(): void {
        $project = \App\Models\Project::factory()->create(['organization_id' => $this->admin->organization_id]);
        $customer = Customer::factory()->create(['organization_id' => $this->admin->organization_id]);
        $customerType = $this->createTypeViaAdminUi();
        $addressType = EntryType::factory()->create([
            'organization_id' => $this->admin->organization_id,
            'slug' => 'vor_ort',
            'label' => 'Vor-Ort-Einsatz',
            'requires_address' => true,
        ]);

        $base = [
            'name' => 'Wartung monatlich',
            'content_template' => 'Wartung {date}',
            'default_location_mode' => \App\Enums\Diary\LocationMode::Onsite->value,
            'frequency' => \App\Enums\Recurrence\RecurrenceFrequency::cases()[0]->value,
            'interval' => 1,
            'starts_on' => '2030-01-01',
        ];

        // Typ mit Kunden-Pflicht → Regel ohne Kunde wird abgelehnt.
        $this->actingAs($this->admin)
            ->post(route('projects.recurrence-rules.store', $project), $base + [
                'entry_type_id' => $customerType->sqid,
            ])
            ->assertSessionHasErrors('customer_id');

        // Mit Kunde → angelegt.
        $this->actingAs($this->admin)
            ->post(route('projects.recurrence-rules.store', $project), $base + [
                'entry_type_id' => $customerType->sqid,
                'customer_id' => $customer->sqid,
            ])
            ->assertSessionHasNoErrors();

        // Adress-fordernde Typen sind für Serienaufträge nicht wählbar.
        $this->actingAs($this->admin)
            ->post(route('projects.recurrence-rules.store', $project), $base + [
                'entry_type_id' => $addressType->sqid,
            ])
            ->assertSessionHasErrors('entry_type_id');
    }

    public function test_edit_form_shows_customer_options_for_requiring_type(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'Bestandskunde AG',
        ]);
        $type = $this->createTypeViaAdminUi();

        $entry = \App\Models\DiaryEntry::factory()->for($this->admin)->create([
            'entry_type_id' => $type->id,
            'customer_id' => $customer->id,
        ]);

        $content = (string) $this->actingAs($this->admin)
            ->get(route('diary.edit', $entry))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('"requires_customer":true', $content);
        $this->assertStringContainsString('Bestandskunde AG', $content);
    }
}

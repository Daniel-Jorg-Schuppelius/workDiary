<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormTemplateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Form;

use App\Enums\Form\FormTemplateStatus;
use App\Models\{FormTemplate, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vorlagen-Verwaltung (Feature 032): Modal-CRUD, Strukturvalidierung der
 * Felddefinition (FormFieldDefinition), Lebenszyklus, Permissions, Cross-Org.
 */
class FormTemplateTest extends TestCase {
    use RefreshDatabase;

    public function test_teamleitung_can_create_template_with_normalized_fields(): void {
        $lead = User::factory()->teamleitung()->create();

        $this->actingAs($lead)
            ->from(route('form-templates.index'))
            ->post(route('form-templates.store'), [
                'name' => 'Wartungsprotokoll Heizung',
                'description' => 'Quartalsweise Wartung',
                'fields' => [
                    ['label' => 'Bemerkung', 'type' => 'text', 'required' => '1'],
                    ['label' => 'Zustand', 'type' => 'select', 'options' => ' gut , mittel , schlecht '],
                    ['label' => 'Messwert', 'type' => 'number', 'unit' => 'kWh'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('form_templates', [
            'name' => 'Wartungsprotokoll Heizung',
            'status' => FormTemplateStatus::Draft->value,
            'created_by_user_id' => $lead->id,
            'organization_id' => $lead->organization_id,
        ]);

        app()->instance('currentOrganization', $lead->organization);
        $template = FormTemplate::query()->firstOrFail();
        $fields = $template->fields;

        // Keys slug-artig aus dem Label abgeleitet, Optionen getrimmt.
        $this->assertSame(['bemerkung', 'zustand', 'messwert'], array_column($fields, 'key'));
        $this->assertTrue($fields[0]['required']);
        $this->assertSame(['gut', 'mittel', 'schlecht'], $fields[1]['options']);
        $this->assertSame('kWh', $fields[2]['unit']);
        // Einheit gibt es nur bei Zahlenfeldern.
        $this->assertNull($fields[0]['unit']);

        // Listenseite rendert die neue Vorlage samt Status-Badge.
        $this->actingAs($lead)
            ->get(route('form-templates.index'))
            ->assertOk()
            ->assertSee('Wartungsprotokoll Heizung')
            ->assertSee(FormTemplateStatus::Draft->label());
    }

    public function test_duplicate_field_key_is_rejected_with_422(): void {
        $lead = User::factory()->teamleitung()->create();

        $this->actingAs($lead)
            ->postJson(route('form-templates.store'), [
                'name' => 'Doppelte Felder',
                'fields' => [
                    ['label' => 'Zustand', 'type' => 'text'],
                    ['label' => 'Zustand', 'type' => 'text'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields']);

        $this->assertDatabaseCount('form_templates', 0);
    }

    public function test_select_without_options_is_rejected_with_422(): void {
        $lead = User::factory()->teamleitung()->create();

        $this->actingAs($lead)
            ->postJson(route('form-templates.store'), [
                'name' => 'Kaputtes Auswahlfeld',
                'fields' => [
                    ['label' => 'Zustand', 'type' => 'select', 'options' => '  '],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields']);
    }

    public function test_unknown_field_type_and_empty_fields_are_rejected(): void {
        $lead = User::factory()->teamleitung()->create();

        $this->actingAs($lead)
            ->postJson(route('form-templates.store'), [
                'name' => 'Unbekannter Typ',
                'fields' => [
                    ['label' => 'Foto', 'type' => 'photo'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields']);

        $this->actingAs($lead)
            ->postJson(route('form-templates.store'), [
                'name' => 'Ohne Felder',
                'fields' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields']);
    }

    public function test_update_replaces_field_definition(): void {
        $lead = User::factory()->teamleitung()->create();
        $template = $this->makeTemplateFor($lead);

        // Anlege- und Bearbeitungs-Dialog rendern (Modal-Partials).
        $this->actingAs($lead)
            ->get(route('form-templates.create'))
            ->assertOk()
            ->assertSee(__('form.action.add_field'));
        $this->actingAs($lead)
            ->get(route('form-templates.edit', $template))
            ->assertOk()
            ->assertSee($template->name);

        $this->actingAs($lead)
            ->put(route('form-templates.update', $template), [
                'name' => 'Umbenannt',
                'fields' => [
                    ['label' => 'Neues Feld', 'type' => 'textarea'],
                ],
            ])
            ->assertRedirect();

        $template->refresh();
        $this->assertSame('Umbenannt', $template->name);
        $this->assertSame(['neues_feld'], array_column($template->fields, 'key'));
    }

    public function test_activate_and_archive_lifecycle(): void {
        $lead = User::factory()->teamleitung()->create();
        $template = $this->makeTemplateFor($lead);

        $this->actingAs($lead)
            ->post(route('form-templates.activate', $template))
            ->assertRedirect();
        $this->assertSame(FormTemplateStatus::Active, $template->refresh()->status);

        $this->actingAs($lead)
            ->post(route('form-templates.archive', $template))
            ->assertRedirect();
        $this->assertSame(FormTemplateStatus::Archived, $template->refresh()->status);
    }

    public function test_user_and_aussendienst_cannot_manage_templates(): void {
        $user = User::factory()->user()->create();
        $field = User::factory()->aussendienst()->create(['organization_id' => $user->organization_id]);
        $template = $this->makeTemplateFor($user);

        $this->actingAs($user)->get(route('form-templates.index'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('form-templates.store'), [
                'name' => 'Verboten',
                'fields' => [['label' => 'X', 'type' => 'text']],
            ])
            ->assertForbidden();

        $this->actingAs($field)->get(route('form-templates.index'))->assertForbidden();
        $this->actingAs($field)
            ->put(route('form-templates.update', $template), [
                'name' => 'Verboten',
                'fields' => [['label' => 'X', 'type' => 'text']],
            ])
            ->assertForbidden();
        $this->actingAs($user)
            ->delete(route('form-templates.destroy', $template))
            ->assertForbidden();
    }

    public function test_teamleitung_can_delete_template_softly(): void {
        $lead = User::factory()->teamleitung()->create();
        $template = $this->makeTemplateFor($lead);

        $this->actingAs($lead)
            ->delete(route('form-templates.destroy', $template))
            ->assertRedirect(route('form-templates.index'));

        $this->assertSoftDeleted('form_templates', ['id' => $template->id]);
    }

    public function test_guest_is_redirected_to_login(): void {
        $this->get(route('form-templates.index'))->assertRedirect(route('login'));
    }

    public function test_template_is_not_accessible_cross_organization(): void {
        $lead = User::factory()->teamleitung()->create();
        $stranger = User::factory()->teamleitung()->create(); // eigene Organisation
        $template = $this->makeTemplateFor($lead);

        $this->actingAs($stranger)
            ->get(route('form-templates.edit', $template))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->post(route('form-templates.activate', $template))
            ->assertNotFound();
    }

    /** Vorlage in der Organisation des Users (default: Entwurf). */
    private function makeTemplateFor(User $creator, bool $active = false): FormTemplate {
        $factory = FormTemplate::factory();
        if ($active) {
            $factory = $factory->active();
        }

        return $factory->create([
            'organization_id' => $creator->organization_id,
            'created_by_user_id' => $creator->id,
        ]);
    }
}

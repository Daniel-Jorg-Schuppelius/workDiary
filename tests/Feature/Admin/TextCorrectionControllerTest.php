<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TextCorrectionControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Admin;

use App\Models\{Organization, TextCorrection};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Pflege-UI des Schreibfehler-Wörterbuchs: finance.config-Gate, CRUD,
 * case-insensitive Org-Eindeutigkeit und Mandantengrenze.
 */
class TextCorrectionControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_ohne_finance_config_kein_zugriff(): void {
        $this->actingAs($this->orgUser());

        $this->get(route('admin.text-corrections.index'))->assertForbidden();
        $this->post(route('admin.text-corrections.store'), ['wrong' => 'a', 'correct' => 'b'])->assertForbidden();
    }

    public function test_index_zeigt_nur_eigene_eintraege(): void {
        TextCorrection::factory()->create(['organization_id' => $this->organization->id, 'wrong' => 'serverwartunng']);
        $foreign = Organization::factory()->create();
        TextCorrection::factory()->create(['organization_id' => $foreign->id, 'wrong' => 'fremdwort']);

        $this->actingAs($this->orgAdmin());

        $this->get(route('admin.text-corrections.index'))
            ->assertOk()
            ->assertSee('serverwartunng')
            ->assertDontSee('fremdwort');
    }

    public function test_anlegen_normalisiert_und_speichert(): void {
        $this->actingAs($this->orgAdmin());

        $this->post(route('admin.text-corrections.store'), [
            'wrong' => '  Serverwartunng  ',
            'correct' => 'Serverwartung',
        ])->assertRedirect(route('admin.text-corrections.index'));

        $this->assertDatabaseHas('text_corrections', [
            'organization_id' => $this->organization->id,
            'wrong' => 'Serverwartunng',
            'wrong_normalized' => 'serverwartunng',
            'correct' => 'Serverwartung',
            'origin' => TextCorrection::ORIGIN_MANUAL,
        ]);
    }

    public function test_case_insensitives_duplikat_wird_abgelehnt(): void {
        TextCorrection::factory()->create(['organization_id' => $this->organization->id, 'wrong' => 'Serverwartunng']);
        $this->actingAs($this->orgAdmin());

        $this->post(route('admin.text-corrections.store'), [
            'wrong' => 'SERVERWARTUNNG',
            'correct' => 'Serverwartung',
        ])->assertSessionHasErrors('wrong');

        $this->assertSame(1, TextCorrection::query()->count());
    }

    public function test_falsch_gleich_richtig_wird_abgelehnt(): void {
        $this->actingAs($this->orgAdmin());

        $this->post(route('admin.text-corrections.store'), [
            'wrong' => 'Serverwartung',
            'correct' => 'serverwartung',
        ])->assertSessionHasErrors('correct');

        $this->assertSame(0, TextCorrection::query()->count());
    }

    public function test_bearbeiten_erlaubt_eigenen_key_und_prueft_fremde(): void {
        $own = TextCorrection::factory()->create(['organization_id' => $this->organization->id, 'wrong' => 'serverwartunng']);
        TextCorrection::factory()->create(['organization_id' => $this->organization->id, 'wrong' => 'geprüfft', 'correct' => 'geprüft']);
        $this->actingAs($this->orgAdmin());

        // Gleicher Key am selben Eintrag bleibt erlaubt.
        $this->patch(route('admin.text-corrections.update', $own), [
            'wrong' => 'Serverwartunng',
            'correct' => 'Serverwartung (Fernzugriff)',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Serverwartung (Fernzugriff)', $own->fresh()->correct);

        // Kollision mit anderem Eintrag wird abgelehnt.
        $this->patch(route('admin.text-corrections.update', $own), [
            'wrong' => 'GEPRÜFFT',
            'correct' => 'geprüft',
        ])->assertSessionHasErrors('wrong');
    }

    public function test_umschalten_und_loeschen(): void {
        $correction = TextCorrection::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->orgAdmin());

        $this->post(route('admin.text-corrections.toggle', $correction))->assertSessionHasNoErrors();
        $this->assertFalse($correction->fresh()->active);

        $this->delete(route('admin.text-corrections.destroy', $correction))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('text_corrections', ['id' => $correction->id]);
    }

    public function test_fremder_eintrag_ist_nicht_erreichbar(): void {
        $foreign = Organization::factory()->create();
        $correction = TextCorrection::factory()->create(['organization_id' => $foreign->id]);
        $this->actingAs($this->orgAdmin());

        $this->get(route('admin.text-corrections.edit', $correction))->assertNotFound();
        $this->delete(route('admin.text-corrections.destroy', $correction))->assertNotFound();
    }
}

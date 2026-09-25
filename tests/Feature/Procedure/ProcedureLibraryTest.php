<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureLibraryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\ProcedureStepType;
use App\Models\Platform\User;
use App\Models\Procedure\{ProcedureLibraryStep, ProcedureTemplate};
use App\Services\Procedure\ProcedureTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-896: Schrittbibliothek pflegen und Schritte als Kopie in den Entwurf einfügen. */
final class ProcedureLibraryTest extends TestCase {
    use RefreshDatabase;

    public function test_library_step_is_maintained_and_inserted_with_code_suffix_and_origin(): void {
        $lead = User::factory()->teamleitung()->create();
        app()->instance('currentOrganization', $lead->organization);

        $this->actingAs($lead)->get(route('procedures.library.create'))->assertOk()->assertSee('step_kind');
        $this->actingAs($lead)->post(route('procedures.library.store'), [
            'code' => 'LOTO', 'step_kind' => ProcedureStepType::Confirm->value, 'label' => 'Anlage freischalten', 'is_required' => '1', 'is_blocking' => '1', 'requires_second_person' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($lead)->post(route('procedures.library.store'), ['code' => 'LOTO', 'step_kind' => ProcedureStepType::Confirm->value, 'label' => 'Doppelt'])
            ->assertSessionHasErrors('code');

        $library = ProcedureLibraryStep::query()->where('code', 'LOTO')->firstOrFail();
        $this->assertTrue($library->requires_second_person);
        $this->actingAs($lead)->get(route('procedures.library.index'))->assertOk()->assertSee('Anlage freischalten');

        $service = app(ProcedureTemplateService::class);
        /** @var ProcedureTemplate $template */
        $template = $service->create($lead->organization, $lead, ['code' => 'MAINT', 'name' => 'Wartung']);
        $draft = $template->versions()->firstOrFail();
        $service->addStepDef($draft, ['sort_order' => 10, 'code' => 'LOTO', 'step_type' => ProcedureStepType::Text->value, 'label' => 'Eigener Schritt']);

        $this->actingAs($lead)->get(route('procedures.edit', $template))->assertOk()->assertSee(route('procedures.library.insert-form', $template), false);
        $this->actingAs($lead)->get(route('procedures.library.insert-form', $template))->assertOk()->assertSee($library->sqid);
        $this->actingAs($lead)->post(route('procedures.library.insert', $template), ['library_steps' => [$library->sqid]])
            ->assertRedirect(route('procedures.edit', $template));

        $copy = $draft->steps()->where('code', 'LOTO-2')->firstOrFail();
        $this->assertSame($library->id, (int) $copy->library_step_id);
        $this->assertTrue((bool) $copy->requires_second_person);
        $this->assertSame(20, (int) $copy->sort_order);

        $service->syncSteps($draft->fresh(), [
            ['code' => 'LOTO-2', 'step_type' => ProcedureStepType::Confirm->value, 'label' => 'Anlage freischalten (angepasst)'],
            ['code' => 'LOTO', 'step_type' => ProcedureStepType::Text->value, 'label' => 'Eigener Schritt'],
        ]);
        $this->assertSame($library->id, (int) $draft->steps()->where('code', 'LOTO-2')->value('library_step_id'));
        $this->assertNull($draft->steps()->where('code', 'LOTO')->value('library_step_id'));

        $this->actingAs($lead)->delete(route('procedures.library.destroy', $library))->assertRedirect();
        $this->assertNull($draft->steps()->where('code', 'LOTO-2')->value('library_step_id'), 'Kopie bleibt, Herkunft wird gelöst');
    }

    public function test_library_of_another_organisation_is_not_reachable(): void {
        $lead = User::factory()->teamleitung()->create();
        $other = User::factory()->teamleitung()->create();
        app()->instance('currentOrganization', $other->organization);
        $foreign = ProcedureLibraryStep::query()->create(['organization_id' => $other->organization_id, 'code' => 'X', 'step_kind' => ProcedureStepType::Confirm->value, 'label' => 'Fremd']);

        app()->instance('currentOrganization', $lead->organization);
        $this->actingAs($lead)->get(route('procedures.library.edit', $foreign))->assertNotFound();
        $this->actingAs($lead)->get(route('procedures.library.index'))->assertOk()->assertDontSee('Fremd');
    }
}

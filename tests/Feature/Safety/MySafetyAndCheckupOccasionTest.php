<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MySafetyAndCheckupOccasionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Safety;

use App\Enums\Safety\MedicalCheckupKind;
use App\Models\Platform\User;
use App\Models\Safety\{MedicalCheckup, MedicalCheckupOccasion};
use App\Services\Safety\SafetyInstructionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-986: „Meine Unterweisungen“ und Vorsorgeanlässe mit Intervall. */
final class MySafetyAndCheckupOccasionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_own_list_shows_open_and_confirmed_instructions_and_confirms_inline(): void {
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $me = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $service = app(SafetyInstructionService::class);
        $open = $service->create($this->organization, $lead, ['topic' => 'Hautschutz', 'held_on' => now()->toDateString()], [$me->id]);
        $done = $service->create($this->organization, $lead, ['topic' => 'Leitern', 'held_on' => now()->subMonth()->toDateString(), 'repeat_interval_months' => 12], [$me->id]);
        $service->sign($done->participants()->firstOrFail(), $me);
        $service->create($this->organization, $lead, ['topic' => 'Fremdes Thema', 'held_on' => now()->toDateString()], [$other->id]);
        MedicalCheckup::query()->create(['organization_id' => $this->organization->id, 'user_id' => $me->id, 'kind' => MedicalCheckupKind::Offered, 'occasion' => 'Bildschirmarbeit', 'performed_on' => now()->toDateString()]);

        $page = $this->actingAs($me)->get(route('safety.instructions.mine'))->assertOk();
        $page->assertSee('Hautschutz')->assertSee('Leitern')->assertSee('Bildschirmarbeit')->assertDontSee('Fremdes Thema');
        $page->assertSee(route('safety.instructions.participants.sign', [$open, $open->participants()->firstOrFail()]));

        $this->actingAs($me)->post(route('safety.instructions.participants.sign', [$open, $open->participants()->firstOrFail()]))->assertRedirect();
        $this->assertNotNull($open->participants()->firstOrFail()->signed_at);
        $this->actingAs($me)->get(route('safety.instructions.mine'))->assertOk()->assertSee(__('safety.register.empty.mine_open'));
    }

    public function test_occasion_catalogue_derives_kind_text_and_next_due_date(): void {
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $person = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($person)->get(route('safety.checkups.occasions.index'))->assertForbidden();
        $this->actingAs($lead)->post(route('safety.checkups.occasions.store'), ['label' => 'Lärm', 'kind' => 'mandatory', 'interval_months' => 36])
            ->assertRedirect(route('safety.checkups.occasions.index'));
        $this->actingAs($lead)->post(route('safety.checkups.occasions.store'), ['label' => 'Lärm', 'kind' => 'offered'])->assertSessionHasErrors('label');
        $occasion = MedicalCheckupOccasion::query()->sole();

        $this->actingAs($lead)->post(route('safety.checkups.store'), [
            'user_id' => $person->sqid, 'medical_checkup_occasion_id' => $occasion->sqid, 'kind' => 'requested', 'performed_on' => '2026-02-10',
        ])->assertSessionHasNoErrors();
        $checkup = MedicalCheckup::query()->sole();
        $this->assertSame(MedicalCheckupKind::Mandatory, $checkup->kind);
        $this->assertSame('Lärm', $checkup->occasion);
        $this->assertSame('2029-02-10', $checkup->next_due_on?->toDateString());
        $this->assertSame($occasion->id, $checkup->medical_checkup_occasion_id);

        // Eigene Fälligkeit hat Vorrang vor dem Intervall.
        $this->actingAs($lead)->put(route('safety.checkups.update', $checkup), [
            'user_id' => $person->sqid, 'medical_checkup_occasion_id' => $occasion->sqid, 'performed_on' => '2026-02-10', 'next_due_on' => '2027-02-10',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2027-02-10', $checkup->refresh()->next_due_on?->toDateString());

        // Deaktivierte Anlässe stehen nicht mehr zur Wahl, bleiben aber an bestehenden Vorsorgen.
        $this->actingAs($lead)->put(route('safety.checkups.occasions.update', $occasion), ['label' => 'Lärm', 'kind' => 'mandatory', 'interval_months' => 36])->assertRedirect();
        $this->assertFalse($occasion->refresh()->is_active);
        $this->actingAs($lead)->get(route('safety.checkups.create'))->assertOk()->assertDontSee($occasion->sqid);
        $this->actingAs($lead)->get(route('safety.checkups.edit', $checkup))->assertOk()->assertSee($occasion->sqid);
        $this->actingAs($lead)->get(route('safety.checkups.occasions.index'))->assertOk()->assertSee('Lärm');
    }
}

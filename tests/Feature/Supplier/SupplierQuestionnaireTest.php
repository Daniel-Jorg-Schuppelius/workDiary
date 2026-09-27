<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierQuestionnaireTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Supplier;

use App\Enums\Supplier\SupplierQuestionnaireStatus;
use App\Mail\SupplierQuestionnaireMail;
use App\Models\Platform\User;
use App\Models\Supplier\{Supplier, SupplierQuestionnaire, SupplierQuestionnaireRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-937: Lieferanten-Selbstauskunft mit Einmal-Link, Antworten und Prüfung. */
final class SupplierQuestionnaireTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Stahl AG', 'email' => 'einkauf@stahl.test']);
    }

    private function questionnaire(): SupplierQuestionnaire {
        $this->actingAs($this->admin)->post(route('supplier-questionnaires.store'), [
            'name' => 'ESG-Basis', 'validity_months' => 12, 'is_active' => 1,
            'fields' => [
                ['label' => 'Umweltmanagement zertifiziert', 'type' => 'boolean', 'required' => '1'],
                ['label' => 'CO2-Emissionen Scope 1 (t)', 'type' => 'number'],
                ['label' => 'Zertifikat', 'type' => 'file'],
            ],
        ])->assertSessionHasErrors('fields');
        $this->actingAs($this->admin)->post(route('supplier-questionnaires.store'), [
            'name' => 'ESG-Basis', 'validity_months' => 12, 'is_active' => 1,
            'fields' => [
                ['label' => 'Umweltmanagement zertifiziert', 'type' => 'boolean', 'required' => '1'],
                ['label' => 'CO2-Emissionen Scope 1 (t)', 'type' => 'number', 'required' => '1'],
            ],
        ])->assertSessionHas('success');

        return SupplierQuestionnaire::query()->sole();
    }

    public function test_supplier_answers_via_link_and_answer_is_reviewed(): void {
        $questionnaire = $this->questionnaire();
        $this->actingAs($this->admin)->post(route('supplier-questionnaires.send', $this->supplier), ['questionnaire_id' => $questionnaire->sqid, 'recipient_email' => 'esg@stahl.test'])->assertSessionHas('success');
        $token = null;
        Mail::assertQueued(SupplierQuestionnaireMail::class, function (SupplierQuestionnaireMail $mail) use (&$token): bool {
            $token = $mail->token;

            return $mail->hasTo('esg@stahl.test');
        });
        $this->assertIsString($token);
        auth()->logout();
        app()->forgetInstance('currentOrganization');

        $this->get(route('supplier-questionnaire.public', $token))->assertOk()->assertSee('ESG-Basis')->assertSee('Umweltmanagement zertifiziert');
        $this->post(route('supplier-questionnaire.public.store', $token), ['values' => ['umweltmanagement_zertifiziert' => '1']])->assertSessionHasErrors();
        $this->post(route('supplier-questionnaire.public.store', $token), ['values' => ['umweltmanagement_zertifiziert' => '1', 'co2_emissionen_scope_1_t' => '120']])->assertRedirect();
        $this->get(route('supplier-questionnaire.public', 'falsch'))->assertNotFound();

        $request = SupplierQuestionnaireRequest::query()->withoutGlobalScopes()->sole();
        $this->assertSame(SupplierQuestionnaireStatus::Submitted, $request->status);
        $this->assertSame('120', (string) $request->answers->get('co2_emissionen_scope_1_t'));
        // Nach dem Absenden ist der Link verbraucht.
        $this->withSession([])->get(route('supplier-questionnaire.public', $token))->assertNotFound();

        $this->actingAs($this->admin)->get(route('supplier-questionnaires.requests.show', $request))->assertOk()->assertSee('120');
        $this->actingAs($this->admin)->post(route('supplier-questionnaires.requests.review', $request), ['decision' => 'accept'])->assertSessionHas('success');
        $this->assertSame(SupplierQuestionnaireStatus::Accepted, $request->fresh()?->status);
        $this->assertNotNull($request->fresh()?->valid_until);
        $this->actingAs($this->admin)->get(route('suppliers.show', $this->supplier))->assertOk()->assertSee('ESG-Basis');
    }

    public function test_rights(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)->post(route('supplier-questionnaires.store'), ['name' => 'x', 'validity_months' => 1, 'fields' => []])->assertForbidden();
    }
}

<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyOptOutTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Mail\SurveyInvitationMail;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Survey\Survey;
use App\Services\Customer\Import\CustomerSpec;
use App\Services\Export\Specs\CustomerExportSpec;
use App\Services\Survey\SurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Umfrage-Opt-out je Kunde (MVP-1603): Schalter am Kunden, Abmeldelink in
 * Einladung und Umfrageseite, Audit am Kunden, Spalte in Export und Import.
 */
final class SurveyOptOutTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function survey(): Survey {
        $survey = Survey::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Jahres-NPS',
            'active' => true,
            'anonymous' => false,
            'created_by' => $this->admin->id,
        ]);
        $survey->questions()->create([
            'organization_id' => $this->organization->id,
            'type' => 'nps',
            'label' => 'Wie wahrscheinlich empfehlen Sie uns weiter?',
            'required' => true,
            'position' => 1,
        ]);

        return $survey;
    }

    private function customer(): Customer {
        return Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@example.test']);
    }

    public function test_customer_form_sets_and_clears_the_opt_out(): void {
        $customer = $this->customer();

        $this->actingAs($this->admin)->get(route('customers.edit', $customer))
            ->assertOk()->assertSee('name="survey_opt_out"', false);

        $this->actingAs($this->admin)
            ->put(route('customers.update', $customer), ['name' => $customer->name, 'survey_opt_out' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($customer->fresh()->survey_opt_out);

        $this->actingAs($this->admin)
            ->put(route('customers.update', $customer), ['name' => $customer->name])
            ->assertSessionHasNoErrors();
        $this->assertFalse($customer->fresh()->survey_opt_out);
    }

    public function test_invitation_mail_and_survey_page_carry_the_opt_out_link(): void {
        Mail::fake();
        $survey = $this->survey();
        $customer = $this->customer();

        $this->actingAs($this->admin)
            ->post(route('surveys.invite', $survey), ['customer' => $customer->sqid])
            ->assertSessionHas('success');

        $token = '';
        Mail::assertSent(SurveyInvitationMail::class, function (SurveyInvitationMail $mail) use (&$token): bool {
            $token = $mail->token;

            return $mail->withOptOut && str_contains($mail->render(), route('surveys.public-opt-out', ['token' => $mail->token]));
        });

        auth()->logout();
        $this->get(route('surveys.public-show', ['token' => $token]))
            ->assertOk()->assertSee(route('surveys.public-opt-out', $token), false);
    }

    public function test_opt_out_link_asks_first_then_records_the_objection_with_audit(): void {
        $survey = $this->survey();
        $customer = $this->customer();
        $service = app(SurveyService::class);
        $issued = $service->invite($survey, 'kunde@example.test', $customer);
        $url = route('surveys.public-opt-out', ['token' => $issued['token']]);

        // GET ändert nichts (Mail-Scanner rufen Links ab).
        $this->get($url)->assertOk()->assertSee(__('Keine Umfragen mehr senden'));
        $this->assertFalse($customer->fresh()->survey_opt_out);

        $this->post($url)->assertOk()->assertSee(__('Sie erhalten keine Umfragen mehr.'));
        $this->assertTrue($customer->fresh()->survey_opt_out);
        $this->assertSame(1, $customer->auditLogs()->where('event', 'survey.opted_out')->count());

        // Wiederholung bleibt ohne zweiten Eintrag, und eingeladen wird nicht mehr.
        $this->post($url)->assertOk();
        $this->assertSame(1, $customer->auditLogs()->where('event', 'survey.opted_out')->count());

        $this->expectException(RuntimeException::class);
        $service->invite($this->survey(), 'kunde@example.test', $customer->fresh());
    }

    public function test_opt_out_works_after_the_survey_was_answered_and_ignores_unknown_tokens(): void {
        $survey = $this->survey();
        $customer = $this->customer();
        $service = app(SurveyService::class);
        $issued = $service->invite($survey, 'kunde@example.test', $customer);
        $service->submit($issued['invitation'], [$survey->questions()->firstOrFail()->id => 9]);

        $this->get(route('surveys.public-show', ['token' => $issued['token']]))->assertNotFound();
        $this->post(route('surveys.public-opt-out.store', ['token' => $issued['token']]))->assertOk();
        $this->assertTrue($customer->fresh()->survey_opt_out);

        $this->get(route('surveys.public-opt-out', ['token' => 'unbekannt']))->assertNotFound();

        // Ohne Kundenbezug gibt es nichts, woran der Widerspruch hängen könnte: kein Link, 404.
        $loose = $service->invite($this->survey(), 'frei@example.test');
        $this->get(route('surveys.public-opt-out', ['token' => $loose['token']]))->assertNotFound();
        $this->get(route('surveys.public-show', ['token' => $loose['token']]))
            ->assertOk()->assertDontSee(__('Umfragen abbestellen'));
    }

    public function test_export_and_import_carry_the_opt_out_column(): void {
        $customer = $this->customer();
        $customer->forceFill(['survey_opt_out' => true])->save();

        $export = app(CustomerExportSpec::class);
        $this->assertContains('survey_opt_out', $export->columns());
        $this->assertNotSame($export->toRow($customer->fresh())['survey_opt_out'], $export->toRow($this->customer())['survey_opt_out']);

        $import = app(CustomerSpec::class);
        $this->assertContains('survey_opt_out', $import->columns());
        $this->assertTrue($import->normalize(['name' => 'Beispiel GmbH', 'survey_opt_out' => 'ja'])['survey_opt_out']);
        $this->assertNull($import->normalize(['name' => 'Beispiel GmbH'])['survey_opt_out']);
    }
}

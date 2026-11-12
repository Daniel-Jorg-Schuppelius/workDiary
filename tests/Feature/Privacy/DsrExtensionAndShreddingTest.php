<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DsrExtensionAndShreddingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\{DataSubjectRequestStatus, DataSubjectRequestType, IncidentType, RetentionProposalStatus};
use App\Mail\Privacy\DsarDeadlineExtensionMail;
use App\Models\Platform\{Organization, User};
use App\Models\Privacy\{DataSubjectRequest, DsarPortal, Incident, PrivacyAttachment, RetentionProposal};
use App\Services\Privacy\{DataProtectionPermissions, DataSubjectRequestService, IncidentService, PrivacyDeadlineService, SubjectDataExporter};
use App\Services\Retention\RetentionScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Storage};
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Lückenschluss MVP-1602: Fristverlängerung nach Art. 12 Abs. 3 DSGVO,
 * Schlüsselvernichtung über den Löschscan und die Sprache des Betroffenenportals.
 */
class DsrExtensionAndShreddingTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function officer(Organization $org): User {
        DataProtectionPermissions::seedOrganization($org);
        $user = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);

        return $user;
    }

    private function request(Organization $org, ?string $email = null): DataSubjectRequest {
        return app(DataSubjectRequestService::class)->open($org, DataSubjectRequestType::Access, 'Erika Muster', 'Bitte Auskunft.', 'email', null, $email);
    }

    /** MVP-1620: Identitätsnachweis, Rückfrage, Antwort und Rücknahme stehen im Bearbeitungsstand und im Verlauf. */
    public function test_processing_status_follows_identity_check_query_and_withdrawal(): void {
        $org = Organization::factory()->create(['legal_region' => 'DE']);
        $officer = $this->officer($org);
        $dsr = $this->request($org);
        $go = fn (DataSubjectRequestStatus $to) => $this->actingAs($officer)->post(route('dataprotection.requests.transition', $dsr), ['status' => $to->value]);

        $this->actingAs($officer)->get(route('dataprotection.requests.show', $dsr))->assertOk()->assertSee(__('dsar.internal.status_action.identity_check'));
        $go(DataSubjectRequestStatus::IdentityCheck)->assertSessionHasNoErrors();
        $this->assertSame(DataSubjectRequestStatus::IdentityCheck, $dsr->fresh()?->status);
        $go(DataSubjectRequestStatus::AwaitingInfo)->assertSessionHasNoErrors();
        $this->assertSame(DataSubjectRequestStatus::AwaitingInfo, $dsr->fresh()?->status);
        $go(DataSubjectRequestStatus::InProgress)->assertSessionHasNoErrors();
        $this->assertSame(DataSubjectRequestStatus::InProgress, $dsr->fresh()?->status);
        $go(DataSubjectRequestStatus::IdentityCheck)->assertSessionHasErrors('status');
        $this->actingAs($officer)->post(route('dataprotection.requests.transition', $dsr), ['status' => DataSubjectRequestStatus::Completed->value])->assertSessionHasErrors('status');

        $go(DataSubjectRequestStatus::Withdrawn)->assertSessionHasNoErrors();
        $fresh = $dsr->fresh();
        $this->assertSame(DataSubjectRequestStatus::Withdrawn, $fresh?->status);
        $this->assertNotNull($fresh?->closed_at);
        $this->assertSame(['identity_check_started', 'info_requested', 'resumed', 'withdrawn'], $fresh?->journal()->whereIn('event', ['identity_check_started', 'info_requested', 'resumed', 'withdrawn'])->orderBy('id')->pluck('event')->all());
        $go(DataSubjectRequestStatus::InProgress)->assertSessionHasErrors('status');
    }

    public function test_deadline_extension_is_recorded_notified_and_reminded_again(): void {
        Mail::fake();
        $org = Organization::factory()->create(['legal_region' => 'DE']);
        $officer = $this->officer($org);
        $dsr = $this->request($org, 'erika@example.test');
        $first = $dsr->deadline_at->copy();

        // Erinnerung zur ersten Frist ist schon gelaufen.
        $this->travelTo($first->copy()->subDays(3));
        $this->assertSame(1, app(PrivacyDeadlineService::class)->remind());
        $this->assertSame(0, app(PrivacyDeadlineService::class)->remind());

        $new = $first->copy()->addMonth()->toDateString();
        $this->actingAs($officer)
            ->post(route('dataprotection.requests.extend', $dsr), ['deadline_at' => $new, 'extension_reason' => 'Umfangreiche Recherche in Altarchiven'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dataprotection.requests.show', $dsr));

        $dsr = $dsr->fresh();
        $this->assertSame($new, $dsr->deadline_at->toDateString());
        $this->assertTrue($first->equalTo($dsr->original_deadline_at));
        $this->assertSame('Umfangreiche Recherche in Altarchiven', $dsr->deadline_extension_reason_ciphertext);
        $this->assertStringNotContainsString('Altarchiven', (string) $dsr->getRawOriginal('deadline_extension_reason_ciphertext'));
        $this->assertFalse($dsr->isOverdue());
        $this->assertSame(1, $dsr->journal()->where('event', 'deadline_extended')->count());
        $this->assertStringNotContainsString('Altarchiven', (string) json_encode($dsr->journal()->pluck('metadata')));

        Mail::assertSent(DsarDeadlineExtensionMail::class, fn (DsarDeadlineExtensionMail $mail): bool => $mail->hasTo('erika@example.test')
            && $mail->reason === 'Umfangreiche Recherche in Altarchiven'
            && $mail->requestNumber === $dsr->request_number);
        $this->assertSame(1, $dsr->journal()->where('event', 'deadline_extension_notified')->count());

        $this->actingAs($officer)->get(route('dataprotection.requests.show', $dsr))
            ->assertOk()->assertSee('Umfangreiche Recherche in Altarchiven');

        // Die verlängerte Frist bekommt ihre eigene Erinnerung – aber erst in ihrem Vorlauf.
        $this->assertSame(0, app(PrivacyDeadlineService::class)->remind());
        $this->travelTo($dsr->deadline_at->copy()->subDays(2));
        $this->assertSame(1, app(PrivacyDeadlineService::class)->remind());
        $this->assertSame(0, app(PrivacyDeadlineService::class)->remind());
    }

    public function test_extension_is_limited_to_two_months_and_to_the_first_deadline(): void {
        Mail::fake();
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = $this->request($org);
        $first = $dsr->deadline_at->copy();

        $this->actingAs($officer)
            ->post(route('dataprotection.requests.extend', $dsr), ['deadline_at' => $first->copy()->addMonths(3)->toDateString(), 'extension_reason' => 'Zu lang gewählt'])
            ->assertSessionHasErrors('deadline_at');
        $this->actingAs($officer)
            ->post(route('dataprotection.requests.extend', $dsr), ['deadline_at' => $first->toDateString(), 'extension_reason' => 'Keine Verlängerung'])
            ->assertSessionHasErrors('deadline_at');
        $this->actingAs($officer)
            ->post(route('dataprotection.requests.extend', $dsr), ['deadline_at' => $first->copy()->addMonth()->toDateString()])
            ->assertSessionHasErrors('extension_reason');
        $this->assertNull($dsr->fresh()->deadline_extended_at);

        // Nach Ablauf der ersten Frist ist die Mitteilung nicht mehr fristgerecht möglich.
        $this->travelTo($first->copy()->addDay());
        $this->actingAs($officer)
            ->post(route('dataprotection.requests.extend', $dsr), ['deadline_at' => $first->copy()->addMonth()->toDateString(), 'extension_reason' => 'Zu spät beantragt'])
            ->assertSessionHasErrors('deadline_at');
        $this->assertNull($dsr->fresh()->deadline_extended_at);
        Mail::assertNothingSent();

        $outsider = User::factory()->create(['organization_id' => $org->id]);
        $this->actingAs($outsider)
            ->post(route('dataprotection.requests.extend', $dsr), ['deadline_at' => $first->copy()->addMonth()->toDateString(), 'extension_reason' => 'Ohne Recht'])
            ->assertForbidden();
    }

    public function test_retention_scan_proposes_key_destruction_and_download_answers_410(): void {
        Storage::fake('local');
        $org = Organization::factory()->create(['legal_region' => 'DE']);
        app()->instance('currentOrganization', $org);
        $officer = $this->officer($org);
        $service = app(DataSubjectRequestService::class);
        $dsr = $this->request($org);
        $service->decide($dsr, 'granted', 'Auskunft erteilt', $officer);

        Storage::disk('local')->put(SubjectDataExporter::STORAGE_PREFIX . $dsr->id . '/paket.enc', 'ciphertext');
        Storage::disk('local')->put('privacy/attachments/ausweis.pdf', 'KLARTEXT');
        $package = PrivacyAttachment::create([
            'organization_id' => $org->id, 'attachable_type' => $dsr->getMorphClass(), 'attachable_id' => $dsr->id,
            'filename' => 'auskunft.pdf', 'path' => SubjectDataExporter::STORAGE_PREFIX . $dsr->id . '/paket.enc', 'size' => 10, 'mime' => 'application/pdf',
        ]);
        PrivacyAttachment::create([
            'organization_id' => $org->id, 'attachable_type' => $dsr->getMorphClass(), 'attachable_id' => $dsr->id,
            'filename' => 'ausweis.pdf', 'path' => 'privacy/attachments/ausweis.pdf', 'size' => 8, 'mime' => 'application/pdf',
        ]);

        $scanner = app(RetentionScanService::class);
        $this->assertSame(0, RetentionProposal::query()->count());
        $scanner->scan($org);
        $this->assertSame(0, RetentionProposal::query()->where('area', 'privacy_requests')->count(), 'Vor Ablauf der Frist kein Vorschlag.');

        $this->travelTo(now()->addYears(3)->addDays(2));
        $scanner->scan($org);
        $proposal = RetentionProposal::query()->where('area', 'privacy_requests')->firstOrFail();
        $this->assertSame(RetentionProposalStatus::Pending, $proposal->status);
        $this->assertFalse($dsr->fresh()->isShredded(), 'Der Scan schlägt nur vor.');

        $scanner->approve($proposal, $officer);
        $scanner->purge($proposal->fresh(), $officer);

        $dsr = $dsr->fresh();
        $this->assertNotNull($dsr, 'Der Vorgang bleibt als Nachweis stehen.');
        $this->assertTrue($dsr->isShredded());
        $this->assertNull($dsr->subject_ciphertext);
        $this->assertSame(1, $dsr->journal()->where('event', 'shredded')->count());
        Storage::disk('local')->assertMissing('privacy/attachments/ausweis.pdf');
        $this->assertSame(1, $dsr->attachments()->count());

        $this->actingAs($officer)->get(route('dataprotection.attachment.download', $package))->assertStatus(410);
        $this->actingAs($officer)->get(route('dataprotection.requests.show', $dsr))->assertOk();

        // Kein zweiter Vorschlag für denselben, bereits vernichteten Vorgang.
        $scanner->scan($org);
        $this->assertSame(1, RetentionProposal::query()->where('area', 'privacy_requests')->count());
        $this->artisan('audit:verify')->assertExitCode(0);
    }

    public function test_closed_incidents_are_shredded_after_the_retention_period(): void {
        Storage::fake('local');
        $org = Organization::factory()->create(['legal_region' => 'DE']);
        app()->instance('currentOrganization', $org);
        $officer = $this->officer($org);
        $incidents = app(IncidentService::class);
        $incident = $incidents->open($org, IncidentType::Misdelivery, 'Fehlversand eines Lohnzettels', 'Eine Person', null, $officer);
        $incidents->close($incident, 'Versandprozess angepasst', $officer);

        $scanner = app(RetentionScanService::class);
        $this->travelTo(now()->addYears(3)->addDays(2));
        $scanner->scan($org);
        $proposal = RetentionProposal::query()->where('area', 'privacy_incidents')->firstOrFail();
        $scanner->approve($proposal, $officer);
        $scanner->purge($proposal->fresh(), $officer);

        $incident = Incident::query()->findOrFail($incident->id);
        $this->assertNull($incident->getAttribute('dek_wrapped'));
        $this->assertNull($incident->summary_ciphertext);
        $this->assertSame(1, $incident->journal()->where('event', 'shredded')->count());
        $this->actingAs($officer)->get(route('dataprotection.incidents.show', $incident))->assertOk();
    }

    public function test_public_portal_uses_its_default_language(): void {
        $org = Organization::factory()->create();
        $portal = DsarPortal::create(['organization_id' => $org->id, 'public_slug' => 'ds-sprache', 'is_enabled' => true, 'default_locale' => 'en']);

        $this->get(route('dsar.portal', ['portal' => $portal->public_slug]))
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee(trans('dsar.form.submit', [], 'en'));

        $portal->forceFill(['default_locale' => null])->save();
        app()->setLocale('de'); // Im Test teilen sich beide Aufrufe die Anwendung samt gesetzter Sprache.
        $this->get(route('dsar.portal', ['portal' => $portal->public_slug]))
            ->assertOk()
            ->assertSee('lang="de"', false);
    }
}

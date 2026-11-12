<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseWorkCompletionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Whistleblowing;

use App\Enums\Whistleblowing\{AttachmentScanStatus, CaseStatus};
use App\Models\Platform\{Organization, User};
use App\Models\Whistleblowing\{Attachment, CaseAssignment, CaseEvent, Portal, WhistleblowingCase};
use App\Notifications\Whistleblowing\WhistleblowingDeadlineNotification;
use App\Services\Whistleblowing\{ReporterCredentialService, WhistleblowingCaseWorkflowService, WhistleblowingPermissions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Notification, Storage};
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Lückenschluss MVP-1601: Rückmeldung nach § 17 HinSchG, Vorwarnstufen,
 * Aufbewahrung je Portal, Ereignisprotokoll, Widerruf, Anhänge der Meldestelle,
 * Begründungspflichten, Listenfilter, externe Meldewege und Portalsprachen.
 */
class CaseWorkCompletionTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function handler(Organization $org): User {
        $user = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(WhistleblowingPermissions::ROLE_MELDESTELLE);
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    /** @param array<string, mixed> $attributes */
    private function makeCase(Organization $org, array $attributes = [], string $category = 'fraud'): WhistleblowingCase {
        $cred = app(ReporterCredentialService::class);
        $secret = $cred->generateSecret();

        $case = new WhistleblowingCase;
        $case->organization_id = $org->id;
        $case->initializeDek();
        $case->reporter_mode = 'anonymous';
        $case->category = $category;
        $case->subject_ciphertext = 'GeheimerBetreffABC';
        $case->description_ciphertext = 'Beschreibung';
        $case->forceFill(array_merge([
            'case_number' => $cred->generateCaseNumber(),
            'access_code_hash' => $cred->hashSecret($secret),
            'access_code_lookup' => $cred->lookupHmac($secret),
        ], $attributes));
        $case->save();

        return $case;
    }

    private function assignTo(WhistleblowingCase $case, User $user): void {
        CaseAssignment::create([
            'organization_id' => $case->organization_id,
            'case_id' => $case->id,
            'user_id' => $user->id,
            'role' => 'processor',
            'assigned_at' => now(),
        ]);
    }

    public function test_marked_message_records_the_feedback_once_and_ends_the_reminders(): void {
        Notification::fake();
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org, ['status' => 'investigating', 'feedback_due_at' => now()->subDay()]);
        $this->assignTo($case, $handler);

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.message', $case), ['body' => 'Rückfrage ohne Kennzeichnung'])
            ->assertRedirect();
        $this->assertNull($case->fresh()->feedback_sent_at, 'Eine Rückfrage ist keine Rückmeldung.');

        $this->travelTo(now()->addMinute());
        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.message', $case), ['body' => 'Folgemaßnahmen', 'is_feedback' => '1'])
            ->assertRedirect();
        $first = $case->fresh()->feedback_sent_at;
        $this->assertNotNull($first);
        $this->assertSame(1, CaseEvent::query()->where('case_id', $case->id)->where('event', 'case.feedback_sent')->count());

        $this->travelTo(now()->addHour());
        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.message', $case), ['body' => 'Nachtrag', 'is_feedback' => '1'])
            ->assertRedirect();
        $this->assertTrue($first->equalTo($case->fresh()->feedback_sent_at), 'Der Zeitpunkt der ersten Rückmeldung bleibt.');

        $this->artisan('whistleblowing:deadlines')->assertExitCode(0);
        Notification::assertNothingSent();
    }

    public function test_reminders_escalate_in_stages_and_each_warning_goes_out_once(): void {
        Notification::fake();
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org, [
            'status' => 'investigating',
            'acknowledged_at' => now(),
            'feedback_due_at' => now()->addDays(10),
        ]);
        $this->assignTo($case, $handler);

        $this->artisan('whistleblowing:deadlines')->assertExitCode(0);
        $this->travelTo(now()->addDay());
        $this->artisan('whistleblowing:deadlines')->assertExitCode(0);
        Notification::assertSentToTimes($handler, WhistleblowingDeadlineNotification::class, 1);
        Notification::assertSentTo($handler, WhistleblowingDeadlineNotification::class, fn ($n): bool => $n->kind === 'feedback_14d');

        $this->travelTo(now()->addDays(8));
        $this->artisan('whistleblowing:deadlines')->assertExitCode(0);
        Notification::assertSentTo($handler, WhistleblowingDeadlineNotification::class, fn ($n): bool => $n->kind === 'feedback_48h');

        $this->travelTo(now()->addDays(2));
        $this->artisan('whistleblowing:deadlines')->assertExitCode(0);
        Notification::assertSentTo($handler, WhistleblowingDeadlineNotification::class, fn ($n): bool => $n->kind === 'feedback');
        Notification::assertSentToTimes($handler, WhistleblowingDeadlineNotification::class, 3);
    }

    public function test_acknowledgement_is_announced_48_hours_ahead(): void {
        Notification::fake();
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org, ['acknowledgement_due_at' => now()->addHours(30)]);
        $this->assignTo($case, $handler);

        $this->artisan('whistleblowing:deadlines')->assertExitCode(0);

        Notification::assertSentTo($handler, WhistleblowingDeadlineNotification::class, function ($n) use ($case): bool {
            $this->assertStringNotContainsString('GeheimerBetreffABC', (string) json_encode($n->toArray($n)));

            return $n->kind === 'acknowledge_48h' && $n->caseNumber === $case->case_number;
        });
    }

    public function test_skipping_the_acknowledged_status_still_records_the_acknowledgement(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);

        app(WhistleblowingCaseWorkflowService::class)->transition($case, CaseStatus::Triage, $handler);

        $this->assertNotNull($case->fresh()->acknowledged_at);
    }

    public function test_retention_follows_the_portal_setting(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        Portal::create(['organization_id' => $org->id, 'public_slug' => 'wb-retention', 'is_enabled' => true, 'retention_months' => 12]);
        $case = $this->makeCase($org, ['status' => 'triage']);

        $this->travelTo('2027-01-15 10:00:00');
        app(WhistleblowingCaseWorkflowService::class)->transition($case, CaseStatus::ClosedOutOfScope, $handler, 'Kein Anwendungsfall');

        $this->assertSame('2028-01-15', $case->fresh()->retention_due_at->toDateString());
    }

    public function test_event_log_needs_its_own_permission_and_records_its_own_view(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $plain = User::factory()->create(['organization_id' => $org->id]);
        $case = $this->makeCase($org);
        $this->assignTo($case, $handler);
        $this->actingAs($handler)->get(route('whistleblowing.internal.show', $case))->assertOk();

        $this->actingAs($plain)->get(route('whistleblowing.internal.events', $case))->assertForbidden();

        $this->actingAs($handler)->get(route('whistleblowing.internal.events', $case))
            ->assertOk()
            ->assertSee(__('journal.whistleblowing.case.viewed'))
            ->assertDontSee('GeheimerBetreffABC');
        $this->assertSame(1, CaseEvent::query()->where('case_id', $case->id)->where('event', 'case.audit_viewed')->count());

        $foreign = $this->handler(Organization::factory()->create());
        $this->actingAs($foreign)->get(route('whistleblowing.internal.events', $case))->assertNotFound();
    }

    public function test_assignment_can_be_revoked_from_the_case_file(): void {
        $org = Organization::factory()->create();
        $lead = $this->handler($org);
        $colleague = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assignTo($case, $lead);
        $this->assignTo($case, $colleague);

        $this->actingAs($lead)->get(route('whistleblowing.internal.show', $case))
            ->assertOk()->assertSee(route('whistleblowing.internal.revoke', $case), false);

        $this->actingAs($lead)
            ->post(route('whistleblowing.internal.revoke', $case), ['user_id' => $colleague->sqid, 'role' => 'processor'])
            ->assertRedirect();

        $this->assertFalse($case->fresh()->isAssigned($colleague));
        $this->assertSame(1, CaseEvent::query()->where('case_id', $case->id)->where('event', 'case.assignment_revoked')->count());
        $this->actingAs($colleague)->get(route('whistleblowing.internal.show', $case))->assertForbidden();
    }

    public function test_handler_attachment_is_encrypted_and_quarantined(): void {
        Storage::fake('whistleblowing');
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assignTo($case, $handler);

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.attach', $case), ['file' => UploadedFile::fake()->createWithContent('vermerk.txt', 'KLARTEXT-VERMERK')])
            ->assertRedirect()
            ->assertSessionHas('success');

        $attachment = Attachment::query()->where('case_id', $case->id)->firstOrFail();
        $this->assertSame('handler', $attachment->uploaded_by_type);
        $this->assertSame(AttachmentScanStatus::Pending, $attachment->scan_status);
        $this->assertTrue($attachment->encrypted);
        $this->assertStringNotContainsString('KLARTEXT-VERMERK', (string) Storage::disk('whistleblowing')->get($attachment->storage_key));
        $this->assertSame($handler->id, (int) CaseEvent::query()->where('case_id', $case->id)->where('event', 'attachment.uploaded')->value('actor_user_id'));

        $unassigned = $this->handler($org);
        $this->actingAs($unassigned)
            ->post(route('whistleblowing.internal.attach', $case), ['file' => UploadedFile::fake()->createWithContent('x.txt', 'x')])
            ->assertForbidden();
    }

    public function test_legal_hold_needs_basis_responsible_and_review_date(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org, ['status' => 'retention_review']);
        $this->assignTo($case, $handler);

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.status', $case), ['to' => 'legal_hold'])
            ->assertSessionHasErrors(['legal_hold_basis', 'legal_hold_responsible', 'legal_hold_review_at']);
        $this->assertSame(CaseStatus::RetentionReview, $case->fresh()->status);

        $review = now()->addMonths(6)->toDateString();
        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.status', $case), [
                'to' => 'legal_hold',
                'legal_hold_basis' => 'Laufendes Ermittlungsverfahren',
                'legal_hold_responsible' => 'Rechtsabteilung',
                'legal_hold_review_at' => $review,
            ])
            ->assertSessionHasNoErrors();

        $case = $case->fresh();
        $this->assertSame(CaseStatus::LegalHold, $case->status);
        $this->assertSame($review, $case->legal_hold_review_at->toDateString());
        $this->assertStringContainsString('Laufendes Ermittlungsverfahren', (string) $case->messages()->first()->body_ciphertext);
        // Die Rechtsgrundlage ist Inhalt: Sie steht verschlüsselt in der Notiz, nie im Ereignis.
        $this->assertStringNotContainsString('Ermittlungsverfahren', (string) json_encode(CaseEvent::query()->where('case_id', $case->id)->pluck('metadata')));

        $this->expectException(InvalidArgumentException::class);
        app(WhistleblowingCaseWorkflowService::class)->transition($this->makeCase($org, ['status' => 'retention_review']), CaseStatus::LegalHold, $handler);
    }

    public function test_reopening_needs_a_reason_and_restarts_the_retention(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org, ['status' => 'closed_unsubstantiated', 'closed_at' => now(), 'retention_due_at' => now()->addYears(3)]);
        $this->assignTo($case, $handler);

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.status', $case), ['to' => 'investigating'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.status', $case), ['to' => 'investigating', 'reason' => 'Neue Belege'])
            ->assertSessionHasNoErrors();

        $case = $case->fresh();
        $this->assertSame(CaseStatus::Investigating, $case->status);
        $this->assertNull($case->retention_due_at);
        $this->assertSame(1, CaseEvent::query()->where('case_id', $case->id)->where('event', 'case.reopened')->count());
    }

    public function test_list_filters_and_category_filter_never_reveals_unassigned_cases(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $mine = $this->makeCase($org, ['status' => 'investigating'], 'fraud');
        $foreign = $this->makeCase($org, ['status' => 'triage'], 'fraud');
        $this->assignTo($mine, $handler);

        $this->actingAs($handler)->get(route('whistleblowing.internal.index', ['status' => 'triage']))
            ->assertOk()->assertSee($foreign->case_number)->assertDontSee($mine->case_number);

        $this->actingAs($handler)->get(route('whistleblowing.internal.index', ['category' => 'fraud']))
            ->assertOk()->assertSee($mine->case_number)->assertDontSee($foreign->case_number);

        $this->actingAs($handler)->get(route('whistleblowing.internal.index', ['assignee' => $handler->sqid]))
            ->assertOk()->assertSee($mine->case_number)->assertDontSee($foreign->case_number);

        $this->actingAs($handler)->get(route('whistleblowing.internal.index', ['assignee' => 'unbekannt']))
            ->assertOk()->assertDontSee($mine->case_number)->assertDontSee($foreign->case_number);

        $overdue = $this->makeCase($org, ['status' => 'investigating', 'feedback_due_at' => now()->subDay()]);
        $this->actingAs($handler)->get(route('whistleblowing.internal.index', ['deadline' => 'overdue']))
            ->assertOk()->assertSee($overdue->case_number)->assertDontSee($mine->case_number);

        $this->actingAs($handler)->get(route('whistleblowing.internal.index', ['from' => now()->addDay()->toDateString()]))
            ->assertOk()->assertDontSee($mine->case_number);
    }

    public function test_portal_settings_carry_external_channels_and_languages_to_the_public_portal(): void {
        $org = Organization::factory()->create();
        $manager = $this->handler($org);

        $this->actingAs($manager)->put('/compliance/portal', [
            'is_enabled' => '1',
            'allow_anonymous' => '1',
            'retention_months' => 36,
            'default_locale' => 'de',
            'allowed_locales' => ['en'],
            'privacy_text_version' => '2026-11',
            'external_channels' => [
                ['label' => 'Externe Meldestelle Beispiel', 'url' => 'https://meldestelle.example'],
                ['label' => '', 'url' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $portal = Portal::withoutGlobalScopes()->where('organization_id', $org->id)->firstOrFail();
        $this->assertSame([['label' => 'Externe Meldestelle Beispiel', 'url' => 'https://meldestelle.example']], $portal->external_channels);
        $this->assertSame(['de', 'en'], $portal->offeredLocales());

        auth()->logout();
        $this->get('/melden/' . $portal->public_slug)
            ->assertOk()
            ->assertSee('Externe Meldestelle Beispiel')
            ->assertSee('https://meldestelle.example', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('Meldung absenden')
            ->assertSee('lang=en', false);

        $this->get('/melden/' . $portal->public_slug . '?lang=en')->assertOk()->assertSee('Send report');
        // Die Wahl bleibt in der Portalsitzung, eine nicht erlaubte Sprache wirkt nicht.
        $this->get('/melden/' . $portal->public_slug)->assertOk()->assertSee('Send report');
        $this->get('/melden/' . $portal->public_slug . '?lang=fr')->assertOk()->assertSee('Send report');
    }

    public function test_portal_without_channels_still_points_to_external_reporting_offices(): void {
        $org = Organization::factory()->create();
        $portal = Portal::create(['organization_id' => $org->id, 'public_slug' => 'wb-plain', 'is_enabled' => true, 'allow_anonymous' => true]);

        $this->get('/melden/' . $portal->public_slug)
            ->assertOk()
            ->assertSee('Externe Meldewege')
            ->assertSee('externe Meldestelle der Behörden');
    }
}

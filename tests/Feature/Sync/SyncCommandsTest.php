<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncCommandsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sync;

use App\Enums\Sync\SyncCommandStatus;
use App\Models\{Attendance, AuditLog, Comment, DiaryEntry, FormSubmission, FormTemplate, Organization, SyncCommand, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Idempotenter Offline-Sync-Batch-Endpunkt (Feature 035, Phase 1 —
 * offline-sync-architektur.md §3.2): applied/duplicate/rejected je Befehl,
 * Ausführung über die bestehenden Services, Audit in der Hash-Kette.
 */
class SyncCommandsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    /** @param  list<array<string, mixed>>  $commands */
    private function postCommands(array $commands): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->user)->postJson(route('api.internal.sync.commands'), [
            'commands' => $commands,
        ]);
    }

    public function test_clock_in_command_is_applied_with_offline_timestamp(): void {
        $uuid = (string) Str::uuid();
        $startedAt = now()->subMinutes(42)->toIso8601String();

        $response = $this->postCommands([[
            'client_uuid' => $uuid,
            'type' => 'attendance.clock-in',
            'payload' => ['started_at' => $startedAt],
            'captured_at' => $startedAt,
        ]]);

        $response->assertOk()->assertJsonPath('results.0.status', 'applied');

        $attendance = Attendance::query()->where('user_id', $this->user->id)->sole();
        $this->assertNull($attendance->ended_at);
        $this->assertSame('offline-pwa', $attendance->started_device);
        $this->assertEqualsWithDelta(
            now()->subMinutes(42)->timestamp,
            $attendance->started_at->timestamp,
            5,
        );

        $this->assertSame(
            'attendances:' . $attendance->id,
            $response->json('results.0.ref'),
        );
        $this->assertDatabaseHas('sync_commands', [
            'user_id' => $this->user->id,
            'client_uuid' => $uuid,
            'result_status' => SyncCommandStatus::Applied->value,
        ]);
    }

    public function test_repeated_client_uuid_is_answered_as_duplicate_without_second_execution(): void {
        $uuid = (string) Str::uuid();
        $command = [
            'client_uuid' => $uuid,
            'type' => 'attendance.clock-in',
            'payload' => ['started_at' => now()->toIso8601String()],
        ];

        $this->postCommands([$command])->assertJsonPath('results.0.status', 'applied');
        $second = $this->postCommands([$command]);

        $second->assertOk()->assertJsonPath('results.0.status', 'duplicate');
        $this->assertSame(1, Attendance::query()->where('user_id', $this->user->id)->count());
        $this->assertSame(1, SyncCommand::query()->where('client_uuid', $uuid)->count());
        // Die duplicate-Antwort liefert die Original-Referenz zum Aufräumen.
        $this->assertStringStartsWith('attendances:', (string) $second->json('results.0.ref'));
    }

    public function test_business_rejection_is_recorded_and_does_not_block_following_commands(): void {
        // Erster Stempel offen ⇒ zweiter clock-in wird fachlich abgelehnt,
        // der nachfolgende Kommentar im selben Batch läuft trotzdem durch.
        $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'attendance.clock-in',
            'payload' => ['started_at' => now()->subHour()->toIso8601String()],
        ]])->assertJsonPath('results.0.status', 'applied');

        $diary = DiaryEntry::factory()->for($this->user)->create();
        $rejectedUuid = (string) Str::uuid();

        $response = $this->postCommands([
            [
                'client_uuid' => $rejectedUuid,
                'type' => 'attendance.clock-in',
                'payload' => ['started_at' => now()->toIso8601String()],
            ],
            [
                'client_uuid' => (string) Str::uuid(),
                'type' => 'comment.diary',
                'payload' => ['diary' => $diary->sqid, 'body' => 'Offline erfasst.'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.1.status', 'applied');

        $this->assertSame(1, Attendance::query()->where('user_id', $this->user->id)->count());
        $this->assertSame(1, $diary->comments()->count());
        $this->assertDatabaseHas('sync_commands', [
            'client_uuid' => $rejectedUuid,
            'result_status' => SyncCommandStatus::Rejected->value,
        ]);
    }

    public function test_clock_out_applies_break_minutes(): void {
        $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'attendance.clock-in',
            'payload' => ['started_at' => now()->subMinutes(180)->toIso8601String()],
        ]])->assertJsonPath('results.0.status', 'applied');

        $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'attendance.clock-out',
            'payload' => [
                'ended_at' => now()->toIso8601String(),
                'break_minutes' => 30,
            ],
        ]])->assertJsonPath('results.0.status', 'applied');

        $attendance = Attendance::query()->where('user_id', $this->user->id)->sole();
        $this->assertNotNull($attendance->ended_at);
        $this->assertSame(30, (int) $attendance->break_minutes_manual);
    }

    public function test_comment_for_foreign_org_diary_is_rejected(): void {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->user()->create(['organization_id' => $otherOrg->id]);
        $foreignDiary = DiaryEntry::factory()->for($otherUser)->create(['organization_id' => $otherOrg->id]);

        $response = $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'comment.diary',
            'payload' => ['diary' => $foreignDiary->sqid, 'body' => 'Fremder Mandant'],
        ]]);

        $response->assertOk()->assertJsonPath('results.0.status', 'rejected');
        $this->assertSame(0, Comment::query()->count());
    }

    public function test_applied_command_writes_sync_audit_event(): void {
        $uuid = (string) Str::uuid();

        $this->postCommands([[
            'client_uuid' => $uuid,
            'type' => 'attendance.clock-in',
            'payload' => ['started_at' => now()->toIso8601String()],
        ]])->assertJsonPath('results.0.status', 'applied');

        $event = AuditLog::query()->where('event', 'sync.applied')->latest('id')->first();
        $this->assertNotNull($event);
        $changes = $event->getAttribute('changes');
        $this->assertSame('attendance.clock-in', $changes['type'] ?? null);
        $this->assertSame($uuid, $changes['client_uuid'] ?? null);
    }

    public function test_unknown_type_fails_request_validation(): void {
        $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'stammdaten.update',
            'payload' => [],
        ]])->assertStatus(422);
    }

    public function test_guest_is_rejected(): void {
        $this->postJson(route('api.internal.sync.commands'), ['commands' => []])
            ->assertStatus(401);
    }

    public function test_form_submission_command_is_applied(): void {
        $template = FormTemplate::factory()->active()->create([
            'organization_id' => $this->user->organization_id,
            'created_by_user_id' => $this->user->id,
            'fields' => [
                ['key' => 'bemerkung', 'label' => 'Bemerkung', 'type' => 'text', 'required' => true, 'options' => [], 'help' => null, 'unit' => null],
            ],
        ]);
        $diary = DiaryEntry::factory()->for($this->user)->create();

        $response = $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'form.submission',
            'payload' => [
                'template' => Sqid::encode(FormTemplate::class, $template->id),
                'subject_kind' => 'diary',
                'subject_id' => $diary->sqid,
                'values' => ['bemerkung' => 'Offline ausgefüllt'],
            ],
        ]]);

        $response->assertOk()->assertJsonPath('results.0.status', 'applied');
        $this->assertDatabaseHas('form_submissions', [
            'form_template_id' => $template->id,
            'submitted_by_user_id' => $this->user->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $diary->id,
        ]);
        // Formularwerte tauchen NICHT im Diagnose-Payload des Registers auf.
        $row = SyncCommand::query()->latest('id')->firstOrFail();
        $this->assertArrayNotHasKey('values', (array) $row->payload);
    }

    public function test_form_submission_missing_required_field_is_rejected(): void {
        $template = FormTemplate::factory()->active()->create([
            'organization_id' => $this->user->organization_id,
            'created_by_user_id' => $this->user->id,
            'fields' => [
                ['key' => 'bemerkung', 'label' => 'Bemerkung', 'type' => 'text', 'required' => true, 'options' => [], 'help' => null, 'unit' => null],
            ],
        ]);

        $response = $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'form.submission',
            'payload' => [
                'template' => Sqid::encode(FormTemplate::class, $template->id),
                'values' => [],
            ],
        ]]);

        $response->assertOk()->assertJsonPath('results.0.status', 'rejected');
        $this->assertNotEmpty($response->json('results.0.errors'));
        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_form_submission_for_foreign_org_template_is_rejected(): void {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->user()->create(['organization_id' => $otherOrg->id]);
        $foreignTemplate = FormTemplate::factory()->active()->create([
            'organization_id' => $otherOrg->id,
            'created_by_user_id' => $otherUser->id,
        ]);

        $response = $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'form.submission',
            'payload' => [
                'template' => Sqid::encode(FormTemplate::class, $foreignTemplate->id),
                'values' => ['bemerkung' => 'Fremde Vorlage'],
            ],
        ]]);

        $response->assertOk()->assertJsonPath('results.0.status', 'rejected');
        $this->assertSame(0, FormSubmission::query()->withoutGlobalScopes()->count());
    }

    public function test_offline_changes_page_renders(): void {
        $this->actingAs($this->user)
            ->get(route('offline.changes'))
            ->assertOk()
            ->assertSee(__('offline.title'))
            ->assertSee('data-offline-changes', false);
    }

    public function test_comment_body_over_limit_is_rejected_with_errors(): void {
        $diary = DiaryEntry::factory()->for($this->user)->create();

        $response = $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'comment.diary',
            'payload' => ['diary' => $diary->sqid, 'body' => str_repeat('x', 5001)],
        ]]);

        $response->assertOk()->assertJsonPath('results.0.status', 'rejected');
        $this->assertNotEmpty($response->json('results.0.errors.body'));
        $this->assertSame(0, $diary->comments()->count());
    }
}

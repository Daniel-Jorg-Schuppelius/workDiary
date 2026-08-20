<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OfflineCorrectionAndPhotoTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sync;

use App\Models\{Attendance, FormSubmission, FormTemplate, SyncCommand, TimeCorrectionRequest, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Offline-Sync-Ausbau (Feature 035 Phase 3; Audit 2026-08, W4.1):
 *
 *  - `attendance.correct` — der erste ÄNDERNDE Befehl. Er läuft über den
 *    bestehenden Korrektur-Workflow und vergleicht `base_version`.
 *  - Konflikt ≠ Ablehnung: bei verändertem Stand kommt `conflict` samt
 *    Server-Fassung zurück, damit der Nutzer entscheiden kann.
 *  - Foto-Queue: die Abgabe entsteht mit Nachreich-Marker, der Bildinhalt
 *    kommt separat über `api.internal.sync.attachments`.
 */
class OfflineCorrectionAndPhotoTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    /** @param  list<array<string, mixed>>  $commands */
    private function postCommands(array $commands): TestResponse {
        return $this->actingAs($this->user)->postJson(route('api.internal.sync.commands'), [
            'commands' => $commands,
        ]);
    }

    private function attendance(): Attendance {
        return Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'started_at' => now()->startOfDay()->addHours(8),
            'ended_at' => now()->startOfDay()->addHours(16),
            'date' => now()->toDateString(),
        ]);
    }

    public function test_correction_creates_a_request_through_the_normal_workflow(): void {
        $attendance = $this->attendance();

        $response = $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'attendance.correct',
            'payload' => [
                'attendance' => $attendance->sqid,
                'base_version' => $attendance->correctionVersion(),
                'started_at' => now()->startOfDay()->addHours(7)->toIso8601String(),
                'reason' => 'Vergessen einzustempeln, Baustelle ohne Empfang seit dem frühen Morgen.',
            ],
        ]]);

        $response->assertOk()->assertJsonPath('results.0.status', 'applied');

        $request = TimeCorrectionRequest::query()->sole();
        $this->assertSame((int) $this->user->id, (int) $request->user_id);
        $this->assertSame('time_correction_requests:' . $request->id, $response->json('results.0.ref'));
        $this->assertCount(1, $request->items);
        $this->assertSame(Attendance::class, $request->items->first()->target_type);
    }

    /** Selbstkorrektur-Modus: die Änderung ist danach wirklich am Stempel. */
    public function test_self_correction_mode_applies_the_change_immediately(): void {
        $this->organization->forceFill([
            'settings' => array_merge((array) $this->organization->settings, [
                'attendance' => ['self_correction' => 'self'],
            ]),
        ])->save();

        $attendance = $this->attendance();
        $newStart = now()->startOfDay()->addHours(7);

        $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'attendance.correct',
            'payload' => [
                'attendance' => $attendance->sqid,
                'base_version' => $attendance->correctionVersion(),
                'started_at' => $newStart->toIso8601String(),
                'reason' => 'Vergessen einzustempeln, Baustelle ohne Empfang seit dem frühen Morgen.',
            ],
        ]])->assertOk()->assertJsonPath('results.0.status', 'applied');

        $this->assertSame($newStart->toDateTimeString(), $attendance->fresh()->started_at->toDateTimeString());
    }

    public function test_changed_record_yields_a_conflict_with_the_server_state(): void {
        $attendance = $this->attendance();
        $staleVersion = $attendance->correctionVersion();

        // Jemand anderes verschiebt inzwischen dasselbe Feld.
        $attendance->forceFill(['started_at' => now()->startOfDay()->addHours(9)])->save();

        $response = $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'attendance.correct',
            'payload' => [
                'attendance' => $attendance->sqid,
                'base_version' => $staleVersion,
                'started_at' => now()->startOfDay()->addHours(7)->toIso8601String(),
                'reason' => 'Vergessen einzustempeln, Baustelle ohne Empfang seit dem frühen Morgen.',
            ],
        ]]);

        $response->assertOk()->assertJsonPath('results.0.status', 'conflict');
        $this->assertNotNull($response->json('results.0.conflict.server.started_at'));
        $this->assertSame(
            $attendance->fresh()->correctionVersion(),
            $response->json('results.0.conflict.current_version'),
        );
        // Kein Korrekturantrag — der Konflikt ist keine halbe Anwendung.
        $this->assertSame(0, TimeCorrectionRequest::query()->count());
        $this->assertSame('conflict', SyncCommand::query()->sole()->result_status->value);
    }

    /** Der zweite Anlauf mit dem aktuellen Stand geht durch. */
    public function test_resending_with_the_current_version_succeeds(): void {
        $attendance = $this->attendance();
        $attendance->forceFill(['started_at' => now()->startOfDay()->addHours(9)])->save();

        $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'attendance.correct',
            'payload' => [
                'attendance' => $attendance->sqid,
                'base_version' => $attendance->fresh()->correctionVersion(),
                'started_at' => now()->startOfDay()->addHours(7)->toIso8601String(),
                'reason' => 'Vergessen einzustempeln, Baustelle ohne Empfang seit dem frühen Morgen.',
            ],
        ]])->assertOk()->assertJsonPath('results.0.status', 'applied');
    }

    public function test_correction_of_a_foreign_attendance_is_rejected(): void {
        $colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $attendance = Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $colleague->id,
            'started_at' => now()->startOfDay()->addHours(8),
            'ended_at' => now()->startOfDay()->addHours(16),
            'date' => now()->toDateString(),
        ]);

        $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'attendance.correct',
            'payload' => [
                'attendance' => $attendance->sqid,
                'base_version' => $attendance->correctionVersion(),
                'started_at' => now()->startOfDay()->addHours(7)->toIso8601String(),
                'reason' => 'Vergessen einzustempeln, Baustelle ohne Empfang seit dem frühen Morgen.',
            ],
        ]])->assertOk()->assertJsonPath('results.0.status', 'rejected');

        $this->assertSame(0, TimeCorrectionRequest::query()->count());
    }

    public function test_correction_without_any_change_is_rejected(): void {
        $attendance = $this->attendance();

        $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'attendance.correct',
            'payload' => [
                'attendance' => $attendance->sqid,
                'base_version' => $attendance->correctionVersion(),
                'reason' => 'Vergessen einzustempeln, Baustelle ohne Empfang seit dem frühen Morgen.',
            ],
        ]])->assertOk()->assertJsonPath('results.0.status', 'rejected');
    }

    // ── Foto-Queue ──────────────────────────────────────────────────────

    private function photoTemplate(): FormTemplate {
        return FormTemplate::factory()->active()->create([
            'organization_id' => $this->user->organization_id,
            'created_by_user_id' => $this->user->id,
            'fields' => [
                ['key' => 'schaden', 'label' => 'Schadensfoto', 'type' => 'photo', 'required' => true, 'options' => [], 'help' => null, 'unit' => null],
            ],
        ]);
    }

    public function test_announced_photo_field_does_not_block_the_submission(): void {
        $template = $this->photoTemplate();

        $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'form.submission',
            'payload' => [
                'template' => Sqid::encode(FormTemplate::class, $template->id),
                'values' => [],
                'pending_files' => ['schaden'],
            ],
        ]])->assertOk()->assertJsonPath('results.0.status', 'applied');

        $submission = FormSubmission::query()->sole();
        $this->assertSame((string) __('form.attachment.pending'), (string) $submission->values['schaden']);
    }

    /** Ohne Ankündigung bleibt das Pflicht-Fotofeld eine Ablehnung. */
    public function test_missing_required_photo_without_announcement_is_rejected(): void {
        $template = $this->photoTemplate();

        $this->postCommands([[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'form.submission',
            'payload' => [
                'template' => Sqid::encode(FormTemplate::class, $template->id),
                'values' => [],
            ],
        ]])->assertOk()->assertJsonPath('results.0.status', 'rejected');
    }

    public function test_queued_photo_is_attached_to_the_submission(): void {
        $template = $this->photoTemplate();
        $uuid = (string) Str::uuid();

        $this->postCommands([[
            'client_uuid' => $uuid,
            'type' => 'form.submission',
            'payload' => [
                'template' => Sqid::encode(FormTemplate::class, $template->id),
                'values' => [],
                'pending_files' => ['schaden'],
            ],
        ]])->assertOk();

        $this->actingAs($this->user)
            ->post(route('api.internal.sync.attachments'), [
                'client_uuid' => $uuid,
                'field' => 'schaden',
                'file' => UploadedFile::fake()->image('schaden.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'stored');

        $submission = FormSubmission::query()->sole();
        $this->assertSame('schaden.jpg', (string) $submission->fresh()->values['schaden']);
        $this->assertSame(1, $submission->attachments()->where('meta_type', 'field:schaden')->count());
    }

    public function test_attachment_for_an_unknown_field_is_refused(): void {
        $template = $this->photoTemplate();
        $uuid = (string) Str::uuid();

        $this->postCommands([[
            'client_uuid' => $uuid,
            'type' => 'form.submission',
            'payload' => [
                'template' => Sqid::encode(FormTemplate::class, $template->id),
                'values' => [],
                'pending_files' => ['schaden'],
            ],
        ]])->assertOk();

        $this->actingAs($this->user)
            ->postJson(route('api.internal.sync.attachments'), [
                'client_uuid' => $uuid,
                'field' => 'erfunden',
                'file' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertStatus(422);
    }

    /** Fremde client_uuid: kein Zugriff auf die Abgabe eines anderen Geräts. */
    public function test_attachment_with_a_foreign_command_uuid_is_refused(): void {
        $this->actingAs($this->user)
            ->postJson(route('api.internal.sync.attachments'), [
                'client_uuid' => (string) Str::uuid(),
                'field' => 'schaden',
                'file' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertStatus(409);
    }
}

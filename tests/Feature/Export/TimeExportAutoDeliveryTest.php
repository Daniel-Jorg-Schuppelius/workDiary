<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportAutoDeliveryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Export;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\TimeExport\TimeExportStatus;
use App\Enums\User\Permission as P;
use App\Jobs\DeliverTimeExportJob;
use App\Mail\TimeExportDeliveryMail;
use App\Models\{Attendance, MonthClosure, TimeExport, TimeExportDeliveryConfig, TimeExportEvent, User};
use App\Services\TimeApproval\MonthClosureService;
use App\Services\TimeExport\{TimeExportService, TimeExportSftpUploader};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Queue, Storage};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * A21 · MVP-019 — Automatische Export-Lieferung: Dispatch beim Abschluss,
 * Mail mit Anhang + persistenter Nachweis, Idempotenz je Kanal, SFTP über
 * den kapselnden Uploader, Fehlerpfad mit Teil-Erfolg, Konfigurations-UI
 * (Adress-Validierung, Passwort verschlüsselt/behalten).
 */
class TimeExportAutoDeliveryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private TimeExportService $service;
    private MonthClosureService $closureService;
    private int $year = 2024;
    private int $month = 1;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');

        $this->service = app(TimeExportService::class);
        $this->closureService = app(MonthClosureService::class);
    }

    // ── Dispatch beim Export-Abschluss ─────────────────────────────────

    public function test_build_dispatches_delivery_job_when_channel_configured(): void {
        $this->makeMailConfig('datev', ['lohn@example.org']);
        [$admin] = $this->readyFixture();

        Queue::fake();
        $export = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'datev', 'organization', actor: $admin),
            $admin,
        );

        Queue::assertPushed(DeliverTimeExportJob::class, fn (DeliverTimeExportJob $job): bool => $job->timeExportId === (int) $export->id);
    }

    public function test_build_does_not_dispatch_without_active_channel(): void {
        // Konfiguration existiert, aber alle Kanäle sind aus.
        TimeExportDeliveryConfig::query()->create([
            'organization_id' => $this->organization->id,
            'profile' => 'datev',
            'mail_enabled' => false,
            'sftp_enabled' => false,
        ]);
        [$admin] = $this->readyFixture();

        Queue::fake();
        $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'datev', 'organization', actor: $admin),
            $admin,
        );

        Queue::assertNotPushed(DeliverTimeExportJob::class);
    }

    // ── Mail-Kanal ─────────────────────────────────────────────────────

    public function test_job_sends_mail_with_attachment_and_persists_evidence(): void {
        // Config erst NACH build() anlegen: die sync-Queue der Tests würde
        // den Job sonst bereits beim Export-Abschluss ausführen.
        $export = $this->readyExport('datev');
        $this->makeMailConfig('datev', ['lohn@example.org', 'backup@example.org']);

        Mail::fake();
        (new DeliverTimeExportJob((int) $export->id))->handle(app(TimeExportSftpUploader::class));

        Mail::assertSent(TimeExportDeliveryMail::class, function (TimeExportDeliveryMail $mail) use ($export): bool {
            return $mail->hasTo('lohn@example.org')
                && $mail->hasTo('backup@example.org')
                && count($mail->attachments()) === 1
                && $mail->export->is($export);
        });

        // Nachweis am Export-Datensatz: wann/wohin, Status delivered (System).
        $export->refresh();
        $this->assertSame(TimeExportStatus::Delivered, $export->status);
        $this->assertNotNull($export->delivered_at);
        $this->assertNull($export->delivered_by_user_id);
        $auto = $export->auto_delivery;
        $this->assertIsArray($auto);
        $this->assertSame(['lohn@example.org', 'backup@example.org'], $auto['mail']['to']);
        $this->assertNotEmpty($auto['mail']['at']);

        // Append-only Audit-Ereignis je Kanal.
        $events = TimeExportEvent::query()->where('time_export_id', $export->id)->pluck('event')->all();
        $this->assertContains('export.delivered_auto', $events);
        $this->assertContains('export.delivered', $events);
    }

    public function test_job_is_idempotent_per_channel(): void {
        $export = $this->readyExport('datev');
        $this->makeMailConfig('datev', ['lohn@example.org']);

        Mail::fake();
        (new DeliverTimeExportJob((int) $export->id))->handle(app(TimeExportSftpUploader::class));

        // Retry-Simulation: Status zurück auf ready (z. B. nach Teil-Fehlschlag
        // eines zweiten Kanals) — der Mail-Nachweis verhindert Doppel-Versand.
        $export->refresh();
        $export->forceFill(['status' => TimeExportStatus::Ready])->save();
        (new DeliverTimeExportJob((int) $export->id))->handle(app(TimeExportSftpUploader::class));

        Mail::assertSent(TimeExportDeliveryMail::class, 1);
    }

    public function test_job_skips_when_export_already_delivered_manually(): void {
        $export = $this->readyExport('datev');
        $this->makeMailConfig('datev', ['lohn@example.org']);
        $this->service->markDelivered($export, null, 'manuell übergeben');

        Mail::fake();
        (new DeliverTimeExportJob((int) $export->id))->handle(app(TimeExportSftpUploader::class));

        Mail::assertNothingSent();
    }

    // ── SFTP-Kanal ─────────────────────────────────────────────────────

    public function test_job_uploads_via_sftp_and_records_target(): void {
        $export = $this->readyExport('datev');
        $this->makeSftpConfig('datev');

        $this->mock(TimeExportSftpUploader::class, function ($mock): void {
            $mock->shouldReceive('upload')
                ->once()
                ->andReturn('sftp://lohn@sftp.example.org:22/upload/datev-2024-01.csv');
        });

        Mail::fake();
        (new DeliverTimeExportJob((int) $export->id))->handle(app(TimeExportSftpUploader::class));

        $export->refresh();
        $this->assertSame(TimeExportStatus::Delivered, $export->status);
        $auto = $export->auto_delivery;
        $this->assertIsArray($auto);
        $this->assertSame('sftp://lohn@sftp.example.org:22/upload/datev-2024-01.csv', $auto['sftp']['target']);
        Mail::assertNothingSent();
    }

    public function test_sftp_failure_keeps_mail_evidence_and_retries_only_sftp(): void {
        $export = $this->readyExport('datev');
        $config = $this->makeSftpConfig('datev');
        $config->fill(['mail_enabled' => true, 'mail_recipients' => ['lohn@example.org']])->save();

        // Erster Lauf: Mail ok, SFTP down → Job wirft für den Queue-Retry.
        $this->mock(TimeExportSftpUploader::class, function ($mock): void {
            $mock->shouldReceive('upload')->once()->andThrow(new \RuntimeException('connection refused'));
        });
        Mail::fake();
        try {
            (new DeliverTimeExportJob((int) $export->id))->handle(app(TimeExportSftpUploader::class));
            $this->fail('Job hätte für den Queue-Retry werfen müssen.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sftp', $e->getMessage());
        }

        $export->refresh();
        $this->assertSame(TimeExportStatus::Ready, $export->status, 'Teil-Erfolg darf nicht als geliefert gelten.');
        $auto = $export->auto_delivery;
        $this->assertIsArray($auto);
        $this->assertArrayHasKey('mail', $auto);
        $this->assertArrayNotHasKey('sftp', $auto);
        $events = TimeExportEvent::query()->where('time_export_id', $export->id)->pluck('event')->all();
        $this->assertContains('export.delivery_failed', $events);

        // Retry: nur SFTP läuft nach, Mail wird nicht erneut versendet.
        $this->mock(TimeExportSftpUploader::class, function ($mock): void {
            $mock->shouldReceive('upload')->once()->andReturn('sftp://lohn@sftp.example.org:22/upload/datev-2024-01.csv');
        });
        (new DeliverTimeExportJob((int) $export->id))->handle(app(TimeExportSftpUploader::class));

        Mail::assertSent(TimeExportDeliveryMail::class, 1);
        $export->refresh();
        $this->assertSame(TimeExportStatus::Delivered, $export->status);
        $this->assertArrayHasKey('sftp', (array) $export->auto_delivery);
    }

    // ── Konfigurations-UI ──────────────────────────────────────────────

    public function test_delivery_config_validates_recipient_addresses(): void {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('admin.wage-type-mappings.index'))
            ->put(route('admin.wage-type-mappings.delivery.update', ['profile' => 'datev']), [
                'mail_enabled' => '1',
                'mail_recipients_raw' => "lohn@example.org\nkein-mail-wert",
                'sftp_enabled' => '0',
            ])
            ->assertSessionHasErrors('mail_recipients.1');

        $this->actingAs($admin)
            ->put(route('admin.wage-type-mappings.delivery.update', ['profile' => 'datev']), [
                'mail_enabled' => '1',
                'mail_recipients_raw' => "lohn@example.org, backup@example.org",
                'sftp_enabled' => '0',
            ])
            ->assertRedirect(route('admin.wage-type-mappings.index'));

        $config = TimeExportDeliveryConfig::query()->where('profile', 'datev')->firstOrFail();
        $this->assertTrue($config->mail_enabled);
        $this->assertSame(['lohn@example.org', 'backup@example.org'], $config->mailRecipients());
        $this->assertSame($this->organization->id, (int) $config->organization_id);
    }

    public function test_delivery_config_encrypts_password_and_keeps_it_on_blank_update(): void {
        $admin = $this->makeAdmin();

        // Erstanlage mit aktiviertem SFTP verlangt ein Passwort.
        $this->actingAs($admin)
            ->from(route('admin.wage-type-mappings.index'))
            ->put(route('admin.wage-type-mappings.delivery.update', ['profile' => 'datev']), [
                'mail_enabled' => '0',
                'sftp_enabled' => '1',
                'sftp_host' => 'sftp.example.org',
                'sftp_username' => 'lohn',
            ])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('time_export_delivery_configs', 0);

        $this->actingAs($admin)
            ->put(route('admin.wage-type-mappings.delivery.update', ['profile' => 'datev']), [
                'mail_enabled' => '0',
                'sftp_enabled' => '1',
                'sftp_host' => 'sftp.example.org',
                'sftp_port' => '2222',
                'sftp_username' => 'lohn',
                'sftp_password' => 'geheim-123',
                'sftp_root' => '/upload',
            ])
            ->assertRedirect(route('admin.wage-type-mappings.index'));

        $config = TimeExportDeliveryConfig::query()->where('profile', 'datev')->firstOrFail();
        $this->assertSame('geheim-123', $config->sftp_password);
        // At-rest verschlüsselt: Rohwert in der DB ist nie der Klartext.
        $raw = (string) \Illuminate\Support\Facades\DB::table('time_export_delivery_configs')
            ->where('id', $config->id)->value('sftp_password');
        $this->assertNotSame('geheim-123', $raw);
        $this->assertStringNotContainsString('geheim-123', $raw);

        // Update mit leerem Passwortfeld behält den Bestand.
        $this->actingAs($admin)
            ->put(route('admin.wage-type-mappings.delivery.update', ['profile' => 'datev']), [
                'mail_enabled' => '0',
                'sftp_enabled' => '1',
                'sftp_host' => 'sftp.example.org',
                'sftp_port' => '2222',
                'sftp_username' => 'lohn',
                'sftp_password' => '',
                'sftp_root' => '/upload',
            ])
            ->assertRedirect(route('admin.wage-type-mappings.index'));
        $this->assertSame('geheim-123', $config->refresh()->sftp_password);

        // Leere optionale Strings landen als NULL (nie leerer encrypted-String).
        $this->actingAs($admin)
            ->put(route('admin.wage-type-mappings.delivery.update', ['profile' => 'datev']), [
                'mail_enabled' => '0',
                'sftp_enabled' => '0',
                'sftp_host' => '',
                'sftp_username' => '',
                'sftp_root' => '',
            ])
            ->assertRedirect(route('admin.wage-type-mappings.index'));
        $config->refresh();
        $this->assertNull($config->sftp_host);
        $this->assertNull($config->sftp_root);
    }

    // ── Helfer ─────────────────────────────────────────────────────────

    /** @param  array<int, string>  $recipients */
    private function makeMailConfig(string $profile, array $recipients): TimeExportDeliveryConfig {
        return TimeExportDeliveryConfig::query()->create([
            'organization_id' => $this->organization->id,
            'profile' => $profile,
            'mail_enabled' => true,
            'mail_recipients' => $recipients,
            'sftp_enabled' => false,
        ]);
    }

    private function makeSftpConfig(string $profile): TimeExportDeliveryConfig {
        return TimeExportDeliveryConfig::query()->create([
            'organization_id' => $this->organization->id,
            'profile' => $profile,
            'mail_enabled' => false,
            'sftp_enabled' => true,
            'sftp_host' => 'sftp.example.org',
            'sftp_port' => 22,
            'sftp_username' => 'lohn',
            'sftp_password' => 'geheim-123',
            'sftp_root' => '/upload',
        ]);
    }

    /** @return array{0: User, 1: User} */
    private function readyFixture(): array {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8 * 60);
        $this->approvedClosureFor($user, $admin);
        $this->actingAs($admin);

        return [$admin, $user];
    }

    private function readyExport(string $profile): TimeExport {
        [$admin] = $this->readyFixture();

        return $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, $profile, 'organization', actor: $admin),
            $admin,
        );
    }

    private function seedAttendance(User $user, int $minutes, int $day = 15): void {
        $date = CarbonImmutable::create($this->year, $this->month, $day) ?? CarbonImmutable::now();
        Attendance::withoutEvents(function () use ($user, $minutes, $date): void {
            Attendance::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'date' => $date,
                'started_at' => $date->setTime(8, 0),
                'ended_at' => $date->setTime(8, 0)->addMinutes($minutes),
                'duration_minutes' => $minutes,
                'status' => AttendanceStatus::Closed,
            ]);
        });
    }

    private function approvedClosureFor(User $user, User $admin): MonthClosure {
        $this->actingAs($user);
        $closure = $this->closureService->getOrCreate($user, $this->year, $this->month);
        $closure = $this->closureService->submit($closure, $user);
        $this->actingAs($admin);

        return $this->closureService->approve($closure, $admin);
    }

    private function makeUser(): User {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $user->givePermissionTo([
            P::MonthViewOwn->value,
            P::MonthSubmitOwn->value,
        ]);
        $user->unsetRelation('permissions');

        return $user;
    }

    private function makeAdmin(): User {
        /** @var User $admin */
        $admin = User::factory()->create(['organization_id' => $this->organization->id]);
        $admin->givePermissionTo([
            P::MonthViewOrganization->value,
            P::MonthApprove->value,
            P::MonthReject->value,
            P::MonthReopen->value,
            P::MonthLock->value,
            P::ExportTimeCreate->value,
            P::ExportTimeDeliver->value,
            P::ExportTimeDelete->value,
            P::WageTypeMappingViewAny->value,
            P::WageTypeMappingManage->value,
        ]);
        $admin->unsetRelation('permissions');

        return $admin;
    }
}

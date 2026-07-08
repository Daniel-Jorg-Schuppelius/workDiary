<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosureBundleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\TimeApproval;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\User\Permission as P;
use App\Models\{Attendance, MonthClosure, Project, TimeEntry, User};
use App\Services\TimeApproval\{MonthClosureBundleService, MonthClosureService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use ZipArchive;

/**
 * Rang 40: Prüfexport-Bundle — nur freigegebene/gesperrte Monate,
 * reproduzierbarer Paket-Hash, Attachment-Ablage + Audit, Rechte.
 */
class MonthClosureBundleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private MonthClosureService $closureService;

    private User $admin;

    private User $worker;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->closureService = app(MonthClosureService::class);

        $this->worker = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->worker->givePermissionTo([P::MonthViewOwn->value, P::MonthSubmitOwn->value]);
        $this->worker->unsetRelation('permissions');

        $this->admin = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->admin->givePermissionTo([
            P::MonthViewOrganization->value,
            P::MonthApprove->value,
            P::MonthLock->value,
        ]);
        $this->admin->unsetRelation('permissions');
    }

    private function approvedClosure(): MonthClosure {
        Attendance::withoutEvents(function (): void {
            Attendance::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $this->worker->id,
                'date' => '2026-01-08',
                'started_at' => '2026-01-08 08:00:00',
                'ended_at' => '2026-01-08 16:00:00',
                'duration_minutes' => 480,
                'status' => AttendanceStatus::Closed,
            ]);
        });

        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'is_default' => false]);
        TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->worker->id,
            'date' => '2026-01-08',
            'minutes' => 480,
            'kind' => TimeEntryKind::Work,
            'description' => 'Montage Halle 3',
            'billable' => true,
        ]);

        $this->actingAs($this->worker);
        $closure = $this->closureService->getOrCreate($this->worker, 2026, 1);
        $closure = $this->closureService->submit($closure, $this->worker);
        $this->actingAs($this->admin);

        return $this->closureService->approve($closure, $this->admin);
    }

    public function test_bundle_only_for_approved_or_locked_months(): void {
        $this->actingAs($this->worker);
        $draft = $this->closureService->getOrCreate($this->worker, 2026, 2);

        // Policy: Draft-Monate liefern kein Prüfpaket (403).
        $this->actingAs($this->admin)
            ->post(route('admin.month-approval.bundle', $draft))
            ->assertForbidden();
    }

    public function test_bundle_creates_attachment_with_reproducible_hash(): void {
        $closure = $this->approvedClosure();

        $service = app(MonthClosureBundleService::class);
        $first = $service->package($closure, $this->admin);
        $second = $service->package($closure->refresh(), $this->admin);

        // Reproduzierbar: identischer Datenstand → identischer Paket-Hash,
        // das Attachment wird wiederverwendet statt dupliziert.
        $this->assertSame($first['package_sha256'], $second['package_sha256']);
        $this->assertTrue($second['reused']);
        $this->assertSame(1, $closure->attachments()->where('meta_type', MonthClosureBundleService::META_TYPE)->count());

        // Audit-Event geschrieben.
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $closure->getMorphClass(),
            'auditable_id' => $closure->id,
            'event' => 'month_closure.bundle_exported',
        ]);

        // ZIP-Inhalt: Manifest + Zeiten-CSV mit dem erfassten Eintrag.
        $tmp = tempnam(sys_get_temp_dir(), 'bundle');
        file_put_contents((string) $tmp, $first['content']);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open((string) $tmp) === true);
        $manifest = (string) $zip->getFromName('manifest.txt');
        $zeiten = (string) $zip->getFromName('zeiten.csv');
        $zip->close();
        @unlink((string) $tmp);

        $this->assertStringContainsString('zeiten.csv:', $manifest);
        $this->assertStringContainsString('package:' . $first['package_sha256'], $manifest);
        $this->assertStringContainsString('Montage Halle 3', $zeiten);
    }

    public function test_http_bundle_downloads_zip_and_requires_lock_permission(): void {
        $closure = $this->approvedClosure();

        // Ohne Sperr-Recht: verboten.
        $this->actingAs($this->worker)
            ->post(route('admin.month-approval.bundle', $closure))
            ->assertForbidden();

        $response = $this->actingAs($this->admin)->post(route('admin.month-approval.bundle', $closure));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertStringStartsWith('PK', (string) $response->getContent());
    }
}

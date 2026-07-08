<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoShowcaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Demo;

use App\Enums\Procedure\ProcedureRunStatus;
use App\Models\{Attachment, DiaryEntry, Organization, ProcedureBackupProof, ProcedureRun};
use App\Services\Demo\DemoSeederService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature 040 Nachtrag: Vorführ-Ausbau des Demo-Seeders — Beispiel-Anhänge
 * (PDF übers pdf-toolkit + Foto) und vollständig durchgespielter
 * Prozedurlauf inkl. verifiziertem Backup-Proof und Vier-Augen-Freigabe;
 * demo:fresh-org legt eine isolierte neue Demo-Org an.
 */
final class DemoShowcaseTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_seed_creates_attachments_and_completed_procedure_run(): void {
        $organization = Organization::factory()->create();

        $counts = app(DemoSeederService::class)->seed($organization);

        $this->assertSame(2, $counts['attachments']);
        $this->assertSame(1, $counts['procedure_runs']);

        // Anhänge hängen am Hauptauftrag, Dateien liegen im Storage.
        $attachments = Attachment::query()
            ->where('organization_id', $organization->id)
            ->where('attachable_type', DiaryEntry::class)
            ->get();
        $this->assertCount(2, $attachments);
        foreach ($attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
        }
        $this->assertSame(1, $attachments->where('mime', 'application/pdf')->count());

        // Prozedurlauf abgeschlossen, Backup verifiziert, Vier-Augen signiert.
        $run = ProcedureRun::query()->where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame(ProcedureRunStatus::Completed, $run->status);
        $this->assertSame(
            1,
            $run->stepRuns()->whereNotNull('second_person_signed_at')->count(),
        );
        $proof = ProcedureBackupProof::query()
            ->whereIn('procedure_step_run_id', $run->stepRuns()->pluck('id'))
            ->firstOrFail();
        $this->assertTrue($proof->verified);
    }

    public function test_reset_cleans_attachments_and_runs(): void {
        $organization = Organization::factory()->create();
        $seeder = app(DemoSeederService::class);
        $seeder->seed($organization);

        $seeder->reset($organization->refresh());

        // Keine Verwaisten: exakt die 2 aktuellen Dateien liegen im Demo-
        // Verzeichnis (die Pfade sind deterministisch — alte wurden gelöscht,
        // bevor die neuen mit gleichem Namen entstanden).
        $this->assertCount(2, Storage::disk('local')->allFiles('attachments/demo/' . $organization->id));
        $this->assertSame(2, Attachment::query()->where('organization_id', $organization->id)->count());
        $this->assertSame(1, ProcedureRun::query()->where('organization_id', $organization->id)->count());
    }

    public function test_seed_creates_agile_showcase_with_history(): void {
        $organization = Organization::factory()->create();

        $counts = app(DemoSeederService::class)->seed($organization);
        $this->assertSame(2, $counts['agile_boards']);

        // Scrum-Board: ein abgeschlossener Sprint mit Snapshot (Velocity-
        // Quelle) + ein aktiver; Historie liegt Wochen zurück (Reports).
        $completed = \App\Models\Agile\AgileSprint::query()
            ->where('organization_id', $organization->id)
            ->where('status', \App\Models\Agile\AgileSprint::STATUS_COMPLETED)
            ->firstOrFail();
        $this->assertGreaterThan(0, (int) ($completed->completion_snapshot['done_points'] ?? 0));
        $this->assertSame(
            1,
            \App\Models\Agile\AgileSprint::query()
                ->where('organization_id', $organization->id)
                ->where('status', \App\Models\Agile\AgileSprint::STATUS_ACTIVE)->count(),
        );

        $events = \App\Models\Agile\AgileEvent::query()->where('organization_id', $organization->id);
        $this->assertTrue($events->clone()->where('created_at', '<', now()->subWeeks(2))->exists());
        $this->assertTrue($events->clone()->where('event', 'item.blocked')->exists());

        // Kanban-Board trägt ein WIP-Limit; Zeit läuft wieder normal.
        $this->assertTrue(
            \App\Models\Agile\AgileBoardColumn::query()
                ->where('organization_id', $organization->id)
                ->where('wip_limit', 2)->exists(),
        );
        $this->assertFalse(\Illuminate\Support\Carbon::hasTestNow());
    }

    public function test_fresh_org_command_creates_isolated_demo_org(): void {
        $existing = Organization::factory()->create(['name' => 'Echte Firma']);

        $this->artisan('demo:fresh-org', ['--branche' => 'elektro'])->assertExitCode(0);

        $demo = Organization::query()->where('is_demo', true)->firstOrFail();
        $this->assertNotSame($existing->id, $demo->id);
        $this->assertStringContainsString('Demo', $demo->name);
        $this->assertFalse($existing->refresh()->is_demo);

        // Zweiter Aufruf → weitere isolierte Org ohne Namenskollision.
        $this->artisan('demo:fresh-org', ['--branche' => 'elektro'])->assertExitCode(0);
        $this->assertSame(2, Organization::query()->where('is_demo', true)->count());
        $this->assertSame(
            2,
            Organization::query()->where('is_demo', true)->distinct()->count('name'),
        );
    }
}

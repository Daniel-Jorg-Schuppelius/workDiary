<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportUserMappingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Import\ImportRunState;
use App\Models\{ImportRun, ImportValueMapping, Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Benutzer-Mapping der Zeitimporte (Muster Tag-Mapping, Rang 58): abweichende
 * Quell-E-Mails scheitern nicht mehr hart — die Preflight sammelt sie, das
 * Mapping-Formular ordnet sie einem Benutzer zu (oder überspringt die Zeilen),
 * Wiederholimporte lösen automatisch auf. Konto-Treffer sind case-insensitiv.
 */
class ImportUserMappingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Testprojekt',
        ]);
    }

    private function preflight(string $csv, string $entity = 'project_times'): ImportRun {
        $file = UploadedFile::fake()->createWithContent('zeiten.csv', $csv);
        $this->actingAs($this->admin)
            ->post(route('admin.imports.preflight'), [
                'entity' => $entity,
                'match_policy' => 'auto_create',
                'file' => $file,
            ])->assertRedirect();

        return ImportRun::query()->latest('id')->firstOrFail();
    }

    private function projectTimeCsv(string $email): string {
        return "user_email;date;start_time;end_time;project\n{$email};2026-01-05;09:00;10:00;Testprojekt\n";
    }

    public function test_preflight_collects_unknown_email_and_confirm_blocks(): void {
        $run = $this->preflight($this->projectTimeCsv('extern@fremd.de'));

        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);
        $this->assertSame(['user_email' => ['extern@fremd.de']], $run->unresolved_values);

        // Confirm blockt, solange die Adresse nicht zugeordnet ist.
        $this->actingAs($this->admin)
            ->post(route('admin.imports.confirm', $run))
            ->assertRedirect(route('admin.imports.show', $run));
        $this->assertSame(ImportRunState::AwaitingApproval, $run->refresh()->state);
        $this->assertSame(0, TimeEntry::query()->count());

        // Formular bietet die Benutzer-Zuordnung an.
        $this->actingAs($this->admin)
            ->get(route('admin.imports.show', $run))
            ->assertOk()
            ->assertSee(__('Unbekannte Benutzer zuordnen'))
            ->assertSee('extern@fremd.de');
    }

    public function test_case_insensitive_email_matches_account_directly(): void {
        $account = User::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'Daniel.Test@Example.com',
        ]);

        $run = $this->preflight($this->projectTimeCsv('daniel.test@example.com'));

        $this->assertNull($run->unresolved_values);

        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $run))->assertRedirect();

        $entry = TimeEntry::query()->firstOrFail();
        $this->assertSame($account->id, $entry->user_id);
        $this->assertSame($this->project->id, $entry->project_id);
    }

    public function test_user_mapping_books_rows_to_mapped_user_and_reimport_resolves(): void {
        $target = User::factory()->create(['organization_id' => $this->organization->id]);

        $run = $this->preflight($this->projectTimeCsv('info@alt-adresse.de'));
        $this->assertSame(['user_email' => ['info@alt-adresse.de']], $run->unresolved_values);

        $this->actingAs($this->admin)
            ->post(route('admin.imports.mapping', $run), [
                'mappings' => [
                    ['value' => 'info@alt-adresse.de', 'action' => 'user', 'user_id' => $target->sqid],
                ],
            ])->assertRedirect(route('admin.imports.show', $run));

        $this->assertNull($run->refresh()->unresolved_values);
        $this->assertDatabaseHas('import_value_mappings', [
            'organization_id' => $this->organization->id,
            'entity' => 'project_times',
            'source_value' => 'info@alt-adresse.de',
            'target_kind' => ImportValueMapping::KIND_USER,
            'user_id' => $target->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $run))->assertRedirect();

        $entry = TimeEntry::query()->firstOrFail();
        $this->assertSame($target->id, $entry->user_id);

        // Wiederholimport: kein offener Mapping-Schritt, idempotent über den Import-Key.
        $rerun = $this->preflight($this->projectTimeCsv('info@alt-adresse.de'));
        $this->assertNull($rerun->unresolved_values);
        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $rerun))->assertRedirect();
        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_ignore_mapping_skips_rows(): void {
        $run = $this->preflight($this->projectTimeCsv('bot@fremdsystem.de'));

        $this->actingAs($this->admin)
            ->post(route('admin.imports.mapping', $run), [
                'mappings' => [
                    ['value' => 'bot@fremdsystem.de', 'action' => 'ignore'],
                ],
            ])->assertRedirect();

        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $run))->assertRedirect();

        $this->assertSame(ImportRunState::Succeeded, $run->refresh()->state);
        $this->assertSame(0, TimeEntry::query()->count());
        $this->assertSame(1, $run->rows_skipped);
    }

    public function test_tag_actions_are_rejected_for_user_email_column(): void {
        $run = $this->preflight($this->projectTimeCsv('extern@fremd.de'));

        // Tag-Aktion auf der E-Mail-Spalte wird ignoriert — der Wert bleibt offen.
        $this->actingAs($this->admin)
            ->post(route('admin.imports.mapping', $run), [
                'mappings' => [
                    ['value' => 'extern@fremd.de', 'action' => 'new'],
                ],
            ])->assertRedirect();

        $this->assertSame(['user_email' => ['extern@fremd.de']], $run->refresh()->unresolved_values);
        $this->assertSame(0, ImportValueMapping::query()->count());
    }

    public function test_attendance_import_collects_unknown_email(): void {
        $csv = "user_email;date;start_time;end_time\nextern@fremd.de;2026-01-05;08:00;16:30\n";
        $run = $this->preflight($csv, 'attendances');

        $this->assertSame(['user_email' => ['extern@fremd.de']], $run->unresolved_values);
    }

    public function test_unmatched_project_stages_and_inbox_create_books_on_reimport(): void {
        User::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'worker2@example.com',
        ]);
        $csv = "user_email;date;start_time;end_time;project\nworker2@example.com;2026-01-05;09:00;10:00;Neues Bauprojekt\n";

        $run = $this->preflight($csv);
        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $run))->assertRedirect();

        // Unbekanntes Projekt: nichts gebucht, Zeile projektförmig in der Inbox.
        $this->assertSame(0, TimeEntry::query()->count());
        $item = \App\Models\IntegrationInboxItem::query()
            ->where('plugin_id', \App\Models\IntegrationInboxItem::PLUGIN_CSV)
            ->firstOrFail();
        $this->assertSame((new Project)->getMorphClass(), $item->target_type);
        $this->assertSame(['name' => 'Neues Bauprojekt'], $item->mapped_snapshot);

        // Inbox rendert das Zuordnen-Dropdown über x-project-options (Smoke).
        $this->actingAs($this->admin)
            ->get(route('admin.integration.inbox'))
            ->assertOk()
            ->assertSee('Testprojekt');

        // „Neu anlegen" aus der Inbox erzeugt das Projekt (MatchProfile-Registrierung).
        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.create', $item))
            ->assertRedirect()
            ->assertSessionHas('success');
        $project = Project::query()->whereRaw('LOWER(name) = ?', ['neues bauprojekt'])->firstOrFail();
        $this->assertNotSame('', (string) $project->slug);

        // Idempotenter Wiederholimport bucht die Zeile jetzt auf das neue Projekt.
        $rerun = $this->preflight($csv);
        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $rerun))->assertRedirect();
        $entry = TimeEntry::query()->firstOrFail();
        $this->assertSame($project->id, $entry->project_id);
    }
}

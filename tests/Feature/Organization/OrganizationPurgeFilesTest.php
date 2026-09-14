<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationPurgeFilesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Organization;

use App\Models\{Organization, OrganizationAuditLog};
use App\Services\OrganizationLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-13: Das endgültige Löschen einer Organisation
 * entfernte KEINE einzige Datei. Es räumte nur eine Kandidatenliste von
 * Verzeichnissen ab, die auf Pfade zeigte, die es gar nicht gibt
 * (`private/uploads/organizations/{id}` und Ähnliches). Die Oberfläche meldete
 * die endgültige Löschung, das revisionssichere Protokoll schrieb den Vorgang,
 * und auf der Platte blieben Anhänge, Dokumentfassungen, Personalakten,
 * Bewerbungsunterlagen und Meldeanhänge liegen — bei einem Löschverlangen nach
 * Artikel 17 also genau das Gegenteil der Zusage.
 */
class OrganizationPurgeFilesTest extends TestCase {
    use RefreshDatabase;

    public function test_purge_removes_the_files_of_the_organization_and_spares_the_others(): void {
        Storage::fake('local');

        $doomed = Organization::factory()->create();
        $keep = Organization::factory()->create();

        Storage::disk('local')->put('attachments/doomed.pdf', 'weg');
        Storage::disk('local')->put('attachments/keep.pdf', 'bleibt');

        DB::table('attachments')->insert([
            ['organization_id' => $doomed->id, 'attachable_type' => 'diary_entry', 'attachable_id' => 1,
                'disk' => 'local', 'path' => 'attachments/doomed.pdf', 'original_name' => 'doomed.pdf',
                'size' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $keep->id, 'attachable_type' => 'diary_entry', 'attachable_id' => 2,
                'disk' => 'local', 'path' => 'attachments/keep.pdf', 'original_name' => 'keep.pdf',
                'size' => 7, 'created_at' => now(), 'updated_at' => now()],
        ]);

        app(OrganizationLifecycleService::class)->purge($doomed->fresh(), null);

        Storage::disk('local')->assertMissing('attachments/doomed.pdf');
        Storage::disk('local')->assertExists('attachments/keep.pdf');

        $audit = OrganizationAuditLog::query()
            ->where('action', OrganizationAuditLog::ACTION_PURGE)
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame(1, (int) ($audit->payload['files_deleted'] ?? 0), 'Das Protokoll muss die Zahl gelöschter Dateien führen.');
    }
}

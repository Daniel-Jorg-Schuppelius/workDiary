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

use App\Models\Audit\OrganizationAuditLog;
use App\Models\Platform\Organization;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Services\Org\OrganizationLifecycleService;
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

    public function test_purge_also_removes_files_registered_by_plugins(): void {
        Storage::fake('local');
        $doomed = Organization::factory()->create();
        Storage::disk('local')->put('lexoffice/vouchers/doomed.pdf', 'weg');
        $voucher = LexofficeVoucher::query()->create([
            'organization_id' => $doomed->id, 'external_id' => 'v-doomed', 'voucher_type' => 'salesinvoice',
            'voucher_status' => 'open', 'voucher_date' => '2026-06-01', 'total_amount' => '1.00', 'currency' => 'EUR', 'archived' => false,
        ]);
        DB::table('lexoffice_vouchers')->where('id', $voucher->id)->update(['file_path' => 'lexoffice/vouchers/doomed.pdf']);

        // Die Tabelle meldet das Lexoffice-Plugin an (MVP-1044), nicht die Kernliste.
        app(OrganizationLifecycleService::class)->purge($doomed->fresh(), null);

        Storage::disk('local')->assertMissing('lexoffice/vouchers/doomed.pdf');
    }

    /**
     * Sicherheitsaudit 2026-10-04, li-6: Tabellen, die nach der Löschliste
     * entstanden, fehlten in ihr — ihre Dateien blieben ohne Zeile liegen.
     */
    public function test_purge_removes_the_files_of_tables_added_after_the_list(): void {
        Storage::fake('local');
        $doomed = Organization::factory()->create();
        $keep = Organization::factory()->create();
        $user = \App\Models\Platform\User::factory()->create(['organization_id' => $doomed->id]);
        $other = \App\Models\Platform\User::factory()->create(['organization_id' => $keep->id]);

        $files = [
            'dictations/' . $doomed->id . '/a', 'travel-logs/signatures/2026/10/a.png', 'hr-submissions/' . $doomed->id . '/a',
            'letterheads/original.pdf', 'letterheads/normalized.pdf',
        ];
        foreach ([...$files, 'travel-logs/signatures/2026/10/keep.png'] as $file) {
            Storage::disk('local')->put($file, 'x');
        }
        $now = ['created_at' => now(), 'updated_at' => now()];

        DB::table('dictations')->insert(['organization_id' => $doomed->id, 'context' => 'diary', 'audio_disk' => 'local', 'audio_path' => $files[0]] + $now);
        DB::table('travel_logs')->insert([
            ['organization_id' => $doomed->id, 'user_id' => $user->id, 'date' => '2026-10-01', 'driver_signature_path' => $files[1]] + $now,
            ['organization_id' => $keep->id, 'user_id' => $other->id, 'date' => '2026-10-01', 'driver_signature_path' => 'travel-logs/signatures/2026/10/keep.png'] + $now,
        ]);
        DB::table('personnel_file_submissions')->insert([
            'organization_id' => $doomed->id, 'user_id' => $user->id, 'title' => 'Zeugnis', 'hr_category' => 'certificate',
            'disk' => 'local', 'path' => $files[2], 'original_name' => 'zeugnis.pdf', 'size' => 1, 'status' => 'pending',
        ] + $now);
        DB::table('letterhead_assets')->insert([
            'organization_id' => $doomed->id, 'name' => 'Briefbogen', 'page_role' => 'first', 'source_type' => 'pdf', 'disk' => 'local',
            'original_path' => $files[3], 'normalized_path' => $files[4], 'original_name' => 'bogen.pdf', 'mime' => 'application/pdf',
            'size' => 1, 'original_sha256' => str_repeat('a', 64),
        ] + $now);

        app(OrganizationLifecycleService::class)->purge($doomed->fresh(), null);

        foreach ($files as $file) {
            Storage::disk('local')->assertMissing($file);
        }
        Storage::disk('local')->assertExists('travel-logs/signatures/2026/10/keep.png');
    }
}

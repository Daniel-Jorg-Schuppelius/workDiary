<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TidyProtocolPdfsCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\ProtocolEventType;
use App\Models\Attachments\Attachment;
use App\Models\Protocol\{Protocol, ProtocolEvent};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Altbestand der Protokoll-PDFs (MVP-1627): Der Nachweis eines abgeschlossenen
 * Protokolls wird Anhang, übrige Fassungen und Entwürfe verschwinden, was ein
 * Anhang referenziert, bleibt — mit Probelauf und wiederholbar.
 */
class TidyProtocolPdfsCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_evidence_becomes_an_attachment_and_unreferenced_files_go(): void {
        Storage::fake('local');
        $this->setUpOrganization();
        $org = $this->organization->id;
        $user = $this->orgUser();

        $signed = Protocol::factory()->signed()->create(['organization_id' => $org, 'created_by_user_id' => $user->id]);
        $draft = Protocol::factory()->create(['organization_id' => $org, 'created_by_user_id' => $user->id]);
        $attached = Protocol::factory()->signed()->create(['organization_id' => $org, 'created_by_user_id' => $user->id]);

        $early = "protocols/{$org}/2030/{$signed->id}_r1_aaaaaaaa.pdf";
        $evidence = "protocols/{$org}/2030/{$signed->id}_r1_bbbbbbbb_scccccc.pdf";
        $draftFile = "protocols/{$org}/2030/{$draft->id}_r1_dddddddd.pdf";
        $kept = "protocols/{$org}/2030/{$attached->id}_r1_eeeeeeee.pdf";
        $foreign = "protocols/{$org}/2030/notiz.pdf";
        foreach ([$early, $evidence, $draftFile, $kept, $foreign] as $file) {
            Storage::disk('local')->put($file, '%PDF-1.4');
        }
        ProtocolEvent::log($signed, ProtocolEventType::PdfRendered, ['path' => $evidence], $user->id);
        $attached->attachments()->create([
            'organization_id' => $org, 'disk' => 'local', 'path' => $kept, 'original_name' => 'x.pdf',
            'mime' => 'application/pdf', 'size' => 8, 'meta_type' => Protocol::META_PDF,
        ]);

        $this->artisan('protocols:tidy-pdfs', ['--dry-run' => true])
            ->expectsOutputToContain('1 referenziert, 1 als Anhang übernommen, 2 entfernt, 1 unbekannte')
            ->assertExitCode(0);
        Storage::disk('local')->assertExists([$early, $evidence, $draftFile]);
        $this->assertSame(0, $signed->attachments()->count());

        $this->artisan('protocols:tidy-pdfs')->assertExitCode(0);

        $attachment = $signed->attachments()->sole();
        $this->assertSame($evidence, $attachment->path);
        $this->assertSame(Protocol::META_PDF, $attachment->meta_type);
        Storage::disk('local')->assertExists([$evidence, $kept, $foreign]);
        Storage::disk('local')->assertMissing([$early, $draftFile]);

        $this->artisan('protocols:tidy-pdfs')
            ->expectsOutputToContain('2 referenziert, 0 als Anhang übernommen, 0 entfernt')
            ->assertExitCode(0);
        $this->assertSame(2, Attachment::query()->withoutGlobalScopes()->where('meta_type', Protocol::META_PDF)->count());
    }
}

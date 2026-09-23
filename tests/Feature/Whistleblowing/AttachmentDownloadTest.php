<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentDownloadTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\Platform\{Organization, User};
use App\Models\Whistleblowing\{Attachment, CaseAssignment, WhistleblowingCase};
use App\Services\Whistleblowing\{
    ReporterCredentialService,
    WhistleblowingAttachmentScanService,
    WhistleblowingAttachmentService,
    WhistleblowingPermissions,
};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Anhang-Auslieferung nur an berechtigte Bearbeiter und NUR nach Freigabe
 * (scan_status = clean). Pending bleibt in Quarantaene (Abschnitt 11).
 */
class AttachmentDownloadTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
        Storage::fake('whistleblowing');
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

    private function makeCase(Organization $org): WhistleblowingCase {
        $cred = app(ReporterCredentialService::class);
        $secret = $cred->generateSecret();

        $case = new WhistleblowingCase;
        $case->organization_id = $org->id;
        $case->initializeDek();
        $case->reporter_mode = 'anonymous';
        $case->category = 'fraud';
        $case->subject_ciphertext = 'S';
        $case->description_ciphertext = 'D';
        $case->forceFill([
            'case_number' => $cred->generateCaseNumber(),
            'access_code_hash' => $cred->hashSecret($secret),
            'access_code_lookup' => $cred->lookupHmac($secret),
        ]);
        $case->save();

        return $case;
    }

    private function attachment(WhistleblowingCase $case): Attachment {
        return app(WhistleblowingAttachmentService::class)
            ->storeReporterUpload($case, UploadedFile::fake()->create('beweis.pdf', 20, 'application/pdf'));
    }

    private function url(WhistleblowingCase $case, Attachment $a): string {
        return route('whistleblowing.internal.attachment', ['case' => $case, 'attachment' => $a->id]);
    }

    /**
     * Sicherheitsaudit 2026-09-13: Betreff, Inhalt und Kontaktdaten eines Falls
     * lagen laengst mit dem Fall-Schluessel verschluesselt, die hochgeladenen
     * Beweismittel dagegen im Klartext auf der Platte. Das Crypto-Shredding beim
     * Loeschen eines Falls wirkte damit nicht auf Dateien: In jedem Backup
     * blieben Fotos und PDFs samt EXIF- und Autor-Metadaten lesbar, also genau
     * der Weg, der den Hinweisgeber enttarnt.
     */
    public function test_attachment_is_not_readable_in_plaintext_on_disk(): void {
        $org = Organization::factory()->create();
        $case = $this->makeCase($org);

        $attachment = app(WhistleblowingAttachmentService::class)->storeReporterUpload(
            $case,
            UploadedFile::fake()->createWithContent('beweis.txt', 'GEHEIMER-HINWEIS-KANARIENVOGEL'),
        );

        $this->assertTrue($attachment->encrypted, 'Neue Anhaenge muessen als verschluesselt gekennzeichnet sein.');

        $raw = (string) Storage::disk((string) config('whistleblowing.disk', 'whistleblowing'))
            ->get($attachment->storage_key);
        $this->assertStringNotContainsString('GEHEIMER-HINWEIS-KANARIENVOGEL', $raw, 'Auf der Platte darf kein Klartext liegen.');

        // Lesbar bleibt er nur ueber den Fall-Schluessel.
        $attachment->setRelation('case', $case);
        $this->assertSame(
            'GEHEIMER-HINWEIS-KANARIENVOGEL',
            app(WhistleblowingAttachmentService::class)->contents($attachment),
        );
    }

    /**
     * Sicherheitsaudit 2026-09-13: Ein Beweisfoto traegt Aufnahmezeit, Geraet
     * und oft GPS-Koordinaten. Die Meldung ist anonym, das Foto war es nicht —
     * Abschnitt 25 des Konzepts sieht die Bereinigung seit jeher vor, umgesetzt
     * war sie nicht (`metadata_scrubbed` wurde nur auf false gesetzt).
     */
    public function test_image_metadata_is_removed_before_the_attachment_is_released(): void {
        $org = Organization::factory()->create();
        $case = $this->makeCase($org);

        // JPEG mit EXIF-Kommentar als Traeger des Personenbezugs.
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);
        $withExif = substr($jpeg, 0, 2) . "\xFF\xFE" . pack('n', 34) . 'GPS 52.5200 N / KANARIENVOGEL' . substr($jpeg, 2);

        $attachment = app(WhistleblowingAttachmentService::class)->storeReporterUpload(
            $case,
            UploadedFile::fake()->createWithContent('beweis.jpg', $withExif),
        );
        $attachment->forceFill(['mime_detected' => 'image/jpeg'])->save();

        $attachment->setRelation('case', $case);
        $this->assertStringContainsString(
            'KANARIENVOGEL',
            app(WhistleblowingAttachmentService::class)->contents($attachment),
            'Vor dem Lauf muss der Traeger noch drin sein, sonst prueft der Test nichts.',
        );

        // Der Standard-Scanner gibt bewusst kein Urteil ab (Quarantaene bleibt);
        // fuer die Bereinigung braucht es einen, der freigibt.
        $this->app->bind(\App\Services\Whistleblowing\Scanning\ScanDriver::class, fn () => new class implements \App\Services\Whistleblowing\Scanning\ScanDriver {
            public function scan(string $absolutePath, ?string $mime): ?\App\Enums\Whistleblowing\AttachmentScanStatus {
                return \App\Enums\Whistleblowing\AttachmentScanStatus::Clean;
            }
        });

        app(\App\Services\Whistleblowing\WhistleblowingAttachmentScanService::class)->scanPending();

        $fresh = $attachment->fresh();
        $fresh?->setRelation('case', $case);
        $this->assertTrue((bool) $fresh?->metadata_scrubbed, 'Bilder muessen als bereinigt gelten.');
        $this->assertStringNotContainsString(
            'KANARIENVOGEL',
            app(WhistleblowingAttachmentService::class)->contents($fresh),
            'Nach der Bereinigung darf kein Metadatenrest mehr im Bild stehen.',
        );
    }

    public function test_pending_attachment_is_withheld(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        CaseAssignment::create(['organization_id' => $org->id, 'case_id' => $case->id, 'user_id' => $handler->id, 'role' => 'processor', 'assigned_at' => now()]);
        $attachment = $this->attachment($case);

        $this->actingAs($handler)->get($this->url($case, $attachment))->assertForbidden();
    }

    public function test_clean_attachment_is_delivered(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        CaseAssignment::create(['organization_id' => $org->id, 'case_id' => $case->id, 'user_id' => $handler->id, 'role' => 'processor', 'assigned_at' => now()]);
        $attachment = $this->attachment($case);

        app(WhistleblowingAttachmentScanService::class)->markClean($attachment);

        $this->actingAs($handler)->get($this->url($case, $attachment))->assertOk()->assertDownload();
    }

    public function test_unassigned_handler_cannot_download(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $other = $this->handler($org);
        $case = $this->makeCase($org);
        CaseAssignment::create(['organization_id' => $org->id, 'case_id' => $case->id, 'user_id' => $handler->id, 'role' => 'processor', 'assigned_at' => now()]);
        $attachment = $this->attachment($case);
        app(WhistleblowingAttachmentScanService::class)->markClean($attachment);

        $this->actingAs($other)->get($this->url($case, $attachment))->assertForbidden();
    }
}

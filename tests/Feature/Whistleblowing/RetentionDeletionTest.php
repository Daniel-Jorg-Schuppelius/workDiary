<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionDeletionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\{Organization, User};
use App\Models\Whistleblowing\{CaseTombstone, WhistleblowingCase};
use App\Services\Whistleblowing\{
    ReporterCredentialService,
    WhistleblowingAttachmentService,
    WhistleblowingDeletionService,
    WhistleblowingMessageService,
};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use RuntimeException;
use Tests\TestCase;

/**
 * Aufbewahrungspruefung und kontrollierte Loeschung (Crypto-Shredding) mit
 * Legal-Hold-Guard und Tombstone (Abschnitt 16 / 25).
 */
class RetentionDeletionTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
        Storage::fake('whistleblowing');
    }

    private function makeCase(string $status): WhistleblowingCase {
        $org = Organization::factory()->create();
        $cred = app(ReporterCredentialService::class);
        $secret = $cred->generateSecret();

        $case = new WhistleblowingCase;
        $case->organization_id = $org->id;
        $case->initializeDek();
        $case->reporter_mode = 'anonymous';
        $case->category = 'fraud';
        $case->subject_ciphertext = 'GeheimerBetreff';
        $case->description_ciphertext = 'Inhalt';
        $case->forceFill([
            'case_number' => $cred->generateCaseNumber(),
            'access_code_hash' => $cred->hashSecret($secret),
            'access_code_lookup' => $cred->lookupHmac($secret),
            'status' => $status,
            'retention_due_at' => now()->subDay(),
        ]);
        $case->save();

        return $case;
    }

    public function test_retention_command_moves_due_closed_cases(): void {
        $case = $this->makeCase('closed_substantiated');

        $this->artisan('whistleblowing:retention-review')->assertExitCode(0);

        $this->assertSame('retention_review', $case->fresh()->status->value);
    }

    public function test_deletion_crypto_shreds_and_writes_tombstone(): void {
        $case = $this->makeCase('retention_review');
        $actor = User::factory()->create(['organization_id' => $case->organization_id]);

        app(WhistleblowingMessageService::class)->addInternalNote($case, 'Notiz', $actor);
        app(WhistleblowingAttachmentService::class)
            ->storeReporterUpload($case, UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'));

        $tombstone = app(WhistleblowingDeletionService::class)->delete($case, $actor);

        $fresh = $case->fresh();
        $this->assertSame('deleted', $fresh->status->value);
        $this->assertNull($fresh->getAttribute('dek_wrapped'), 'DEK vernichtet (Crypto-Shredding).');
        $this->assertNull(DB::table('whistleblowing_cases')->where('id', $case->id)->value('subject_ciphertext'));
        $this->assertSame(0, $case->messages()->count());
        $this->assertSame(0, $case->attachments()->count());

        $this->assertInstanceOf(CaseTombstone::class, $tombstone);
        $this->assertSame($case->case_number, $tombstone->case_number);
        $this->assertNotEmpty($tombstone->audit_hash);

        $this->assertDatabaseHas('whistleblowing_case_events', [
            'case_id' => $case->id, 'event' => 'case.deleted',
        ]);
    }

    public function test_legal_hold_blocks_deletion(): void {
        $case = $this->makeCase('legal_hold');
        $actor = User::factory()->create(['organization_id' => $case->organization_id]);

        $this->expectException(RuntimeException::class);
        app(WhistleblowingDeletionService::class)->delete($case, $actor);
    }

    public function test_deletion_requires_retention_review(): void {
        $case = $this->makeCase('closed_substantiated');
        $actor = User::factory()->create(['organization_id' => $case->organization_id]);

        $this->expectException(RuntimeException::class);
        app(WhistleblowingDeletionService::class)->delete($case, $actor);
    }
}

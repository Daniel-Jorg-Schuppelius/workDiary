<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseEncryptionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\Organization;
use App\Models\Whistleblowing\WhistleblowingCase;
use App\Services\Whistleblowing\ReporterCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sichert die fall-bezogene Verschluesselung und das Crypto-Shredding ab
 * (Abschnitt 10 / 25).
 */
class CaseEncryptionTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
    }

    private function makeCase(Organization $org, string $subject, string $description): WhistleblowingCase {
        $cred = app(ReporterCredentialService::class);
        $secret = $cred->generateSecret();

        $case = new WhistleblowingCase;
        $case->organization_id = $org->id;
        $case->initializeDek();
        $case->reporter_mode = 'anonymous';
        $case->category = 'fraud';
        $case->subject_ciphertext = $subject;
        $case->description_ciphertext = $description;
        $case->forceFill([
            'case_number' => $cred->generateCaseNumber(),
            'access_code_hash' => $cred->hashSecret($secret),
            'access_code_lookup' => $cred->lookupHmac($secret),
        ]);
        $case->save();

        return $case;
    }

    public function test_content_is_encrypted_at_rest_and_decrypts(): void {
        $org = Organization::factory()->create();
        $case = $this->makeCase($org, 'Top Secret Betreff', 'Detaillierte Beschreibung');

        // Roh in der DB: kein Klartext.
        $raw = DB::table('whistleblowing_cases')->where('id', $case->id)->first();
        $this->assertStringNotContainsString('Top Secret Betreff', (string) $raw->subject_ciphertext);
        $this->assertStringNotContainsString('Detaillierte', (string) $raw->description_ciphertext);
        $this->assertNotEmpty($raw->dek_wrapped);
        $this->assertNotSame('intern', $raw->public_id); // UUID gesetzt

        // Frisch geladen: transparent entschluesselt.
        $loaded = WhistleblowingCase::findOrFail($case->id);
        $this->assertSame('Top Secret Betreff', $loaded->subject_ciphertext);
        $this->assertSame('Detaillierte Beschreibung', $loaded->description_ciphertext);
    }

    public function test_crypto_shredding_makes_content_unrecoverable(): void {
        $org = Organization::factory()->create();
        $case = $this->makeCase($org, 'Geheim', 'Inhalt');

        WhistleblowingCase::findOrFail($case->id)->shredDek();

        $reloaded = WhistleblowingCase::findOrFail($case->id);
        $this->assertNull($reloaded->getAttribute('dek_wrapped'));
        $this->assertNull($reloaded->subject_ciphertext, 'Ohne DEK kein Klartext mehr.');
    }
}

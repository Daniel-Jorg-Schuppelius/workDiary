<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataSubjectRequestCryptoTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Models\Organization;
use App\Models\Privacy\{DataSubjectRequest, RequestEvent};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Per-Fall-Krypto der Betroffenenanfragen: Roundtrip, at-rest verschluesselt,
 * Crypto-Shredding, plus Integritaet der Event-Hash-Kette.
 */
class DataSubjectRequestCryptoTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
    }

    private function makeRequest(Organization $org): DataSubjectRequest {
        $dsr = new DataSubjectRequest;
        $dsr->organization_id = $org->id;
        $dsr->request_number = 'DSR-0001';
        $dsr->type = 'access';
        $dsr->status = 'intake';
        $dsr->initializeDek();
        $dsr->subject_ciphertext = 'Max Mustermann, max@example.com';
        $dsr->content_ciphertext = 'Bitte Auskunft ueber gespeicherte Daten.';
        $dsr->save();

        return $dsr;
    }

    public function test_encrypts_and_decrypts_roundtrip(): void {
        $org = Organization::factory()->create();
        $dsr = $this->makeRequest($org);

        $fresh = DataSubjectRequest::findOrFail($dsr->id);
        $this->assertSame('Max Mustermann, max@example.com', $fresh->subject_ciphertext);
        $this->assertSame('Bitte Auskunft ueber gespeicherte Daten.', $fresh->content_ciphertext);

        // At-rest: Rohwert in der DB ist NICHT der Klartext.
        $raw = (string) DB::table('privacy_data_subject_requests')->where('id', $dsr->id)->value('subject_ciphertext');
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString('Mustermann', $raw);
    }

    public function test_crypto_shredding_makes_content_unrecoverable(): void {
        $org = Organization::factory()->create();
        $dsr = $this->makeRequest($org);

        $dsr->shredDek();

        $fresh = DataSubjectRequest::findOrFail($dsr->id);
        $this->assertNull($fresh->dek_wrapped);
        $this->assertNull($fresh->subject_ciphertext, 'Ohne DEK kein Klartext mehr.');
    }

    public function test_request_event_hash_chain_verifies(): void {
        $org = Organization::factory()->create();
        $dsr = $this->makeRequest($org);

        foreach (['received', 'assigned', 'completed'] as $event) {
            RequestEvent::create([
                'organization_id' => $org->id,
                'request_id' => $dsr->id,
                'actor_type' => 'staff',
                'event' => $event,
                'metadata' => ['k' => $event],
            ]);
        }

        $this->assertNotNull(RequestEvent::query()->latest('id')->first()?->hash);
        $this->artisan('audit:verify')->assertExitCode(0);
    }
}

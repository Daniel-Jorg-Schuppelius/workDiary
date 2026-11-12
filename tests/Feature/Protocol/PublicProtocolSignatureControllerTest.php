<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicProtocolSignatureControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\{ProtocolSignatureRole, ProtocolType};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Protocol\{Protocol, ProtocolSignatureToken};
use App\Services\Protocol\{ProtocolPdfRenderer, ProtocolService, ProtocolSignatureTokenService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicProtocolSignatureControllerTest extends TestCase {
    use RefreshDatabase;

    public function test_show_renders_view_for_valid_token(): void {
        Storage::fake(ProtocolPdfRenderer::DISK);
        [$creator, $protocol] = $this->makeReviewProtocol();
        $token = $this->issueToken($creator, $protocol);

        $response = $this->get(route('protocols.public-sign', ['token' => $token]));
        $response->assertOk();
        $response->assertSee($protocol->title);
    }

    public function test_store_redeems_token_creates_signature(): void {
        Storage::fake(ProtocolPdfRenderer::DISK);
        [$creator, $protocol] = $this->makeReviewProtocol();
        $token = $this->issueToken($creator, $protocol);

        $response = $this->post(route('protocols.public-sign.submit', ['token' => $token]), [
            'signer_name' => 'Max Mustermann',
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
            'accept' => '1',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('protocol_signatures', [
            'protocol_id' => $protocol->id,
            'signer_name' => 'Max Mustermann',
        ]);

        // Das gezeichnete Bild liegt als Anhang am Protokoll (MVP-1572).
        $signature = $protocol->signatures()->firstOrFail();
        $this->assertNotNull($signature->signatureAttachment);
        $this->assertSame(Protocol::META_SIGNATURE, $signature->signatureAttachment->meta_type);
        Storage::disk('local')->assertExists($signature->signatureAttachment->path);
    }

    public function test_store_without_drawn_signature_is_rejected(): void {
        Storage::fake(ProtocolPdfRenderer::DISK);
        [$creator, $protocol] = $this->makeReviewProtocol();
        $token = $this->issueToken($creator, $protocol);

        $this->post(route('protocols.public-sign.submit', ['token' => $token]), ['signer_name' => 'Max Mustermann', 'accept' => '1'])
            ->assertSessionHasErrors('signature');
        $this->post(route('protocols.public-sign.submit', ['token' => $token]), ['signer_name' => 'Max Mustermann', 'accept' => '1', 'signature' => 'data:image/png;base64,' . base64_encode('kein Bild')])
            ->assertSessionHasErrors('signature');

        $this->assertSame(0, $protocol->signatures()->count());
    }

    public function test_expired_token_returns_410(): void {
        [$creator, $protocol] = $this->makeReviewProtocol();
        $token = $this->issueToken($creator, $protocol);
        ProtocolSignatureToken::query()->update(['expires_at' => now()->subDay()]);

        $response = $this->get(route('protocols.public-sign', ['token' => $token]));
        $response->assertStatus(410);
    }

    /**
     * @return array{0: User, 1: Protocol}
     */
    private function makeReviewProtocol(): array {
        $creator = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($creator)->create();
        /** @var ProtocolService $svc */
        $svc = app(ProtocolService::class);
        $p = $svc->create($entry, $creator, [
            'type' => ProtocolType::Acceptance->value,
            'title' => 'Abnahme',
        ]);
        $svc->requestReview($p, $creator);
        return [$creator, $p->refresh()];
    }

    private function issueToken(User $creator, Protocol $protocol): string {
        /** @var ProtocolSignatureTokenService $tokens */
        $tokens = app(ProtocolSignatureTokenService::class);
        return $tokens->issue($protocol, $creator, [
            'role' => ProtocolSignatureRole::Customer->value,
            'signer_email' => 'k@example.org',
        ])['token'];
    }
}

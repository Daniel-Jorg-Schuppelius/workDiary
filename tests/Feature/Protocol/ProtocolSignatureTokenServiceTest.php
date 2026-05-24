<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolSignatureTokenServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\{ProtocolSignatureRole, ProtocolType};
use App\Models\{DiaryEntry, Protocol, ProtocolSignatureToken, User};
use App\Services\Protocol\{ProtocolService, ProtocolSignatureTokenService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProtocolSignatureTokenServiceTest extends TestCase {
    use RefreshDatabase;

    public function test_issue_creates_token_and_event(): void {
        [$creator, $protocol] = $this->makeReviewProtocol();
        /** @var ProtocolSignatureTokenService $tokens */
        $tokens = app(ProtocolSignatureTokenService::class);

        $result = $tokens->issue($protocol, $creator, [
            'role' => ProtocolSignatureRole::Customer->value,
            'signer_email' => 'kunde@example.org',
        ]);

        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
        $this->assertDatabaseHas('protocol_signature_tokens', [
            'protocol_id' => $protocol->id,
            'role' => ProtocolSignatureRole::Customer->value,
            'signer_email' => 'kunde@example.org',
        ]);
        $this->assertDatabaseHas('protocol_events', [
            'protocol_id' => $protocol->id,
            'event' => 'protocol.signatureRequested',
        ]);
    }

    public function test_redeem_creates_signature_and_marks_used(): void {
        [$creator, $protocol] = $this->makeReviewProtocol();
        /** @var ProtocolSignatureTokenService $tokens */
        $tokens = app(ProtocolSignatureTokenService::class);
        $result = $tokens->issue($protocol, $creator, [
            'role' => ProtocolSignatureRole::Customer->value,
            'signer_email' => 'kunde@example.org',
        ]);

        $tokens->redeem($result['token'], [
            'signer_name' => 'Max Mustermann',
            'ip' => '127.0.0.1',
        ]);

        $this->assertDatabaseHas('protocol_signatures', [
            'protocol_id' => $protocol->id,
            'signer_name' => 'Max Mustermann',
        ]);
        $this->assertNotNull($result['model']->refresh()->used_at);
    }

    public function test_redeem_twice_throws(): void {
        [$creator, $protocol] = $this->makeReviewProtocol();
        /** @var ProtocolSignatureTokenService $tokens */
        $tokens = app(ProtocolSignatureTokenService::class);
        $r = $tokens->issue($protocol, $creator, [
            'role' => ProtocolSignatureRole::Customer->value,
            'signer_email' => 'k@example.org',
        ]);
        $tokens->redeem($r['token'], ['signer_name' => 'Max', 'ip' => '127.0.0.1']);

        $this->expectException(RuntimeException::class);
        $tokens->redeem($r['token'], ['signer_name' => 'Max', 'ip' => '127.0.0.1']);
    }

    public function test_expired_token_throws(): void {
        [$creator, $protocol] = $this->makeReviewProtocol();
        /** @var ProtocolSignatureTokenService $tokens */
        $tokens = app(ProtocolSignatureTokenService::class);
        $r = $tokens->issue($protocol, $creator, [
            'role' => ProtocolSignatureRole::Customer->value,
            'signer_email' => 'k@example.org',
        ]);
        ProtocolSignatureToken::query()->whereKey($r['model']->id)->update(['expires_at' => now()->subDay()]);

        $this->expectException(RuntimeException::class);
        $tokens->redeem($r['token'], ['signer_name' => 'Max', 'ip' => '127.0.0.1']);
    }

    public function test_open_sets_opened_at_and_logs_event(): void {
        [$creator, $protocol] = $this->makeReviewProtocol();
        /** @var ProtocolSignatureTokenService $tokens */
        $tokens = app(ProtocolSignatureTokenService::class);
        $r = $tokens->issue($protocol, $creator, [
            'role' => ProtocolSignatureRole::Customer->value,
            'signer_email' => 'k@example.org',
        ]);

        $tokens->open($r['token']);
        $this->assertNotNull($r['model']->refresh()->opened_at);
        $this->assertDatabaseHas('protocol_events', [
            'protocol_id' => $protocol->id,
            'event' => 'protocol.signatureLinkOpened',
        ]);
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
}

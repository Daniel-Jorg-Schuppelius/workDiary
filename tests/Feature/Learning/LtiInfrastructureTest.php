<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LtiInfrastructureTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Models\Learning\{LearningLtiKey, LearningLtiNonce};
use App\Services\Learning\{LearningLtiKeyService, LearningLtiNonceStore, LearningLtiRemoteKeys};
use Carbon\CarbonImmutable;
use ELearningToolkit\Lti\{Keys, LtiException};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Jose\Component\Core\JWK;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * LTI 1.3, Stufe 9a (Feature 149): Schlüssel der Instanz, JWKS, Nonces und
 * die Schlüsselmengen der Gegenseite.
 */
class LtiInfrastructureTest extends TestCase {
    use RefreshDatabase;

    protected function tearDown(): void {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_the_jwks_publishes_only_the_public_parts(): void {
        $response = $this->getJson(route('learning.lti.jwks'))->assertOk();

        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
        $keys = $response->json('keys');
        $this->assertIsArray($keys);
        $this->assertCount(1, $keys);
        $this->assertIsArray($keys[0]);

        foreach (['d', 'p', 'q', 'dp', 'dq', 'qi'] as $private) {
            $this->assertArrayNotHasKey($private, $keys[0], "Privater Anteil {$private} gehört nie in die JWKS.");
        }

        $this->assertSame(LearningLtiKey::query()->value('kid'), $keys[0]['kid'] ?? null);
        $this->assertSame('RS256', $keys[0]['alg'] ?? null);
    }

    public function test_the_private_key_is_stored_encrypted_and_never_serialized(): void {
        $key = app(LearningLtiKeyService::class)->active();

        $raw = (string) DB::table('learning_lti_keys')->where('id', $key->id)->value('private_jwk');
        $this->assertStringNotContainsString('"d"', $raw);
        $this->assertTrue($key->privateKey()->has('d'));
        $this->assertArrayNotHasKey('private_jwk', $key->toArray());
    }

    public function test_a_rotated_key_stays_published_until_no_token_can_carry_it(): void {
        $service = app(LearningLtiKeyService::class);
        $old = $service->active();
        $new = $service->rotate();

        $this->assertNotSame($old->kid, $new->kid);
        $this->assertSame($new->kid, $service->active()->kid);
        $this->assertEqualsCanonicalizing([$old->kid, $new->kid], $this->publishedKids());

        $this->travel(LearningLtiKeyService::RETIRED_GRACE_HOURS + 1)->hours();

        $this->assertSame(1, $service->prune());
        $this->assertSame([$new->kid], $this->publishedKids());
    }

    public function test_the_rotation_command_rotates_only_when_due_or_forced(): void {
        $this->artisan('learning:lti-rotate-keys')->assertSuccessful();
        $this->assertSame(1, LearningLtiKey::query()->count(), 'Der erste Lauf legt den Schlüssel an.');

        $this->artisan('learning:lti-rotate-keys')->assertSuccessful();
        $this->assertSame(1, LearningLtiKey::query()->count(), 'Nicht fällig — kein Tausch.');

        $this->artisan('learning:lti-rotate-keys', ['--force' => true])->assertSuccessful();
        $this->assertSame(2, LearningLtiKey::query()->count());
        $this->assertSame(1, LearningLtiKey::query()->whereNull('retired_at')->count());

        config(['learning.lti.key_rotation_days' => 30]);
        $this->travel(31)->days();

        $this->artisan('learning:lti-rotate-keys')->assertSuccessful();
        $this->assertSame(1, LearningLtiKey::query()->whereNull('retired_at')->count());
        $this->assertSame(2, LearningLtiKey::query()->count(), 'Der vor 31 Tagen zurückgezogene Schlüssel ist weg.');
    }

    public function test_a_nonce_is_accepted_exactly_once(): void {
        $store = app(LearningLtiNonceStore::class);
        $expires = CarbonImmutable::now()->addHour();

        $this->assertTrue($store->consume('nonce-1', $expires));
        $this->assertFalse($store->consume('nonce-1', $expires));
        $this->assertTrue($store->consume('nonce-2', $expires));
        $this->assertFalse($store->consume('', $expires));
    }

    public function test_expired_nonces_are_pruned(): void {
        $store = app(LearningLtiNonceStore::class);
        $store->consume('alt', CarbonImmutable::now()->subMinute());
        $store->consume('frisch', CarbonImmutable::now()->addHour());

        $this->artisan('learning:lti-prune-nonces')->assertSuccessful();

        $this->assertSame(1, LearningLtiNonce::query()->count());
    }

    public function test_remote_key_sets_are_cached_and_can_be_refreshed(): void {
        $url = 'https://tool.example.org/jwks';
        $fake = FakePluginHttp::fake([
            $url => FakePluginHttp::response(Keys::publicKeySet([Keys::generate('tool-1')])),
        ]);
        $remote = app(LearningLtiRemoteKeys::class);

        $this->assertTrue($remote->keySet($url)->has('tool-1'));
        $remote->keySet($url);
        $this->assertCount(1, $fake->recorded(), 'Der zweite Abruf kommt aus dem Cache.');

        $remote->keySet($url, true);
        $this->assertCount(2, $fake->recorded(), 'Ein unbekannter kid lädt frisch.');
    }

    public function test_a_broken_remote_key_set_is_rejected(): void {
        FakePluginHttp::fake([
            'https://tool.example.org/kaputt' => FakePluginHttp::response(['error' => 'nope'], 500),
        ]);

        $this->expectException(LtiException::class);

        app(LearningLtiRemoteKeys::class)->keySet('https://tool.example.org/kaputt');
    }

    /** @return list<mixed> */
    private function publishedKids(): array {
        return array_map(static fn (JWK $key): mixed => $key->get('kid'), app(LearningLtiKeyService::class)->published());
    }
}

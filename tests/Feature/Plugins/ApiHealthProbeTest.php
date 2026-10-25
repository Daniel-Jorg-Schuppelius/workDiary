<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiHealthProbeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use APIToolkit\Exceptions\UnauthorizedException;
use App\Plugins\PluginHealth;
use App\Plugins\Support\{ApiHealthProbe, PluginApiException};
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\{Request, Response as Psr7Response};
use Illuminate\Http\Client\{RequestException, Response};
use LogicException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Einordnung der gemeinsamen Leseprobe (Befund 2026-10-24): Vorübergehendes ist `degraded` und zählt nicht zur
 * Abschaltung, Dauerhaftes ist `failing`; die Meldung nennt Status oder Ausnahmeklasse statt einer Sammelmeldung.
 */
final class ApiHealthProbeTest extends TestCase {
    private function failWith(Throwable $e, array $hints = []): PluginHealth {
        return ApiHealthProbe::probe('Beispiel', static fn () => throw $e, $hints);
    }

    public function test_success_uses_the_returned_message_or_the_generic_one(): void {
        $this->assertSame(__('integration.api_health.ok', ['provider' => 'Beispiel']), ApiHealthProbe::probe('Beispiel', static fn () => ['id' => 1])->message);
        $this->assertSame('Verbunden als a@b.de', ApiHealthProbe::probe('Beispiel', static fn () => 'Verbunden als a@b.de')->message);

        $finding = PluginHealth::failing('Add-on fehlt', 'api_addon_missing');
        $this->assertSame($finding, ApiHealthProbe::probe('Beispiel', static fn () => $finding));
    }

    public function test_rejected_sign_in_is_failing_with_status(): void {
        foreach ([401, 403] as $status) {
            $health = $this->failWith(new PluginApiException('x', $status));

            $this->assertTrue($health->isFailing());
            $this->assertSame('auth', $health->code);
            $this->assertStringContainsString((string) $status, $health->message);
        }

        $health = $this->failWith(new UnauthorizedException('No OAuth2 token available', 401));
        $this->assertSame('auth', $health->code);
    }

    public function test_provider_hint_replaces_the_generic_text_and_receives_the_status(): void {
        $health = $this->failWith(new PluginApiException('x', 403), ['auth' => 'Token abgelehnt (:status) — PAT prüfen.']);

        $this->assertSame('Token abgelehnt (403) — PAT prüfen.', $health->message);
    }

    public function test_transient_failures_are_degraded(): void {
        $cases = [
            'rate_limited' => new PluginApiException('x', 429),
            'server_error' => new PluginApiException('x', 503),
            'network' => new ConnectException('cURL error 28', new Request('GET', 'https://api.example.test')),
        ];

        foreach ($cases as $code => $exception) {
            $health = $this->failWith($exception);

            $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->status, $code);
            $this->assertSame($code, $health->code);
        }

        $this->assertSame('server_error', $this->failWith(new PluginApiException('x', 408))->code);
    }

    public function test_wrapped_network_failure_stays_transient(): void {
        // SDKs (S3, CardDAV) packen den Guzzle-Fehler ein; die Ursache entscheidet.
        $wrapped = new RuntimeException('Bucket nicht erreichbar', 0, new ConnectException('cURL error 6', new Request('HEAD', 'https://s3.example.test')));

        $this->assertSame('network', $this->failWith($wrapped)->code);
        $this->assertSame('network', $this->failWith(new PluginApiException('Bucket nicht erreichbar', 0, previous: $wrapped))->code);
    }

    public function test_permanent_client_errors_are_failing(): void {
        $health = $this->failWith(new PluginApiException('x', 404));
        $this->assertTrue($health->isFailing());
        $this->assertSame('api_error', $health->code);
        $this->assertStringContainsString('404', $health->message);

        $health = $this->failWith(new RequestException(new Response(new Psr7Response(410))));
        $this->assertSame('api_error', $health->code);

        $health = $this->failWith(new RequestException(new Response(new Psr7Response(502))));
        $this->assertSame('server_error', $health->code);
    }

    public function test_client_side_abort_keeps_its_own_message(): void {
        $health = $this->failWith(new PluginApiException('Das Ziel der Verbindung zeigt auf eine interne Adresse — Abruf unterlassen.', 0));

        $this->assertTrue($health->isFailing());
        $this->assertSame('aborted', $health->code);
        $this->assertSame('Das Ziel der Verbindung zeigt auf eine interne Adresse — Abruf unterlassen.', $health->message);
    }

    public function test_unexpected_exception_names_only_its_class(): void {
        $health = $this->failWith(new LogicException('enthält vielleicht Antwortinhalt'));

        $this->assertTrue($health->isFailing());
        $this->assertSame('error', $health->code);
        $this->assertStringContainsString('LogicException', $health->message);
        $this->assertStringNotContainsString('Antwortinhalt', $health->message);
    }
}

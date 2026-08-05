<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResellingTransportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\DomainReselling;

use App\Enums\Domain\{DomainCapabilityArea, DomainProviderEnvironment};
use App\Models\Domain\DomainProviderConnection;
use App\Plugins\DomainReselling\Adapters\DomainResellingAdapter;
use App\Plugins\DomainReselling\Api\DomainResellingClient;
use App\Plugins\DomainReselling\Support\DomainResponseParser;
use App\Plugins\Support\Domain\{DomainCapabilityBlockedException, DomainProviderException};
use Psr\Http\Message\RequestInterface;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Transport + Plaintextprotokoll (Feature 083, MVP-385): POST-only,
 * Credentials im Body (nie in der URL), case-insensitive/whitespace-
 * normalisierte Properties, `EOF`-Pflicht und Capability-Gating.
 */
class DomainResellingTransportTest extends TestCase {
    private function connection(): DomainProviderConnection {
        return new DomainProviderConnection([
            'environment' => DomainProviderEnvironment::Production,
            'name' => 'Test',
            'endpoint' => 'domainreselling',
            'login' => 'reseller1',
            'password' => 'secret-pw',
            'default_user' => null,
        ]);
    }

    public function test_parser_reads_code_properties_and_requires_eof(): void {
        $body = "[RESPONSE]\r\ncode = 200\r\nDESCRIPTION = Command completed successfully\r\n"
            . "property[DOMAIN][0]=example.com\r\nProperty[Status][0] = ACTIVE\r\nEOF\r\n";

        $parsed = DomainResponseParser::parse($body);

        $this->assertTrue($parsed->hasEof);
        $this->assertTrue($parsed->isSuccess());
        $this->assertSame(200, $parsed->code);
        $this->assertSame('Command completed successfully', $parsed->description);
        // case-insensitive property lookup:
        $this->assertSame('example.com', $parsed->first('domain'));
        $this->assertSame('ACTIVE', $parsed->first('STATUS'));
    }

    public function test_missing_eof_is_incomplete_and_not_success(): void {
        $parsed = DomainResponseParser::parse("code = 200\r\nproperty[domain][0]=a.com\r\n");

        $this->assertFalse($parsed->hasEof);
        $this->assertFalse($parsed->isSuccess());
    }

    public function test_rows_correlate_properties_by_index(): void {
        $body = "code=200\nproperty[domain][0]=a.com\nproperty[domain][1]=b.com\n"
            . "property[status][0]=ACTIVE\nproperty[status][1]=EXPIRED\nEOF\n";

        $rows = DomainResponseParser::parse($body)->rows();

        $this->assertCount(2, $rows);
        $this->assertSame(['domain' => 'a.com', 'status' => 'ACTIVE'], $rows[0]);
        $this->assertSame(['domain' => 'b.com', 'status' => 'EXPIRED'], $rows[1]);
    }

    public function test_client_posts_credentials_in_body_not_url(): void {
        $fake = FakePluginHttp::fake([
            '*call.cgi' => FakePluginHttp::response("code=200\ndescription=ok\nEOF\n"),
        ]);

        $response = (new DomainResellingClient($this->connection()))->call('StatusUser');

        $this->assertTrue($response->isSuccess());
        $fake->assertSent(function (RequestInterface $request): bool {
            $url = (string) $request->getUri();
            $body = (string) $request->getBody();

            // Handbuch-Parameternamen s_login/s_pw — NICHT login/password
            // (die echte API sieht das Passwort sonst gar nicht, live belegt:
            // „Authentication failed; empty password").
            return $request->getMethod() === 'POST'
                && ! str_contains($url, 'secret-pw')
                && ! str_contains($url, 'password')
                && str_contains($body, 'command=StatusUser')
                && str_contains($body, 's_login=reseller1')
                && str_contains($body, 's_pw=secret-pw')
                && ! str_contains($body, '&login=')
                && ! str_contains($body, '&password=');
        });
    }

    public function test_error_response_without_eof_surfaces_provider_code_on_reads(): void {
        // Live-Verhalten der echten API: Fehlerantworten (z. B. 530) kommen
        // OHNE [RESPONSE]-Header und OHNE EOF-Marker.
        FakePluginHttp::fake([
            '*call.cgi' => FakePluginHttp::response("code = 530\ndescription = Authentication failed"),
        ]);

        $response = (new DomainResellingClient($this->connection()))->call('StatusUser');

        $this->assertSame(530, $response->code);
        $this->assertSame('Authentication failed', $response->description);
        $this->assertFalse($response->isSuccess());
    }

    public function test_mutating_error_without_eof_stays_incomplete(): void {
        FakePluginHttp::fake([
            '*call.cgi' => FakePluginHttp::response("code = 549\ndescription = Command failed"),
        ]);

        $this->expectException(DomainProviderException::class);
        (new DomainResellingClient($this->connection()))->call('AddDomain', ['domain' => 'x.com'], true);
    }

    public function test_incomplete_response_throws(): void {
        FakePluginHttp::fake([
            '*call.cgi' => FakePluginHttp::response("code=200\ndescription=ok\n"), // kein EOF
        ]);

        $this->expectException(DomainProviderException::class);
        (new DomainResellingClient($this->connection()))->call('StatusDomain', ['domain' => 'x.com']);
    }

    public function test_adapter_blocks_undocumented_invoice_capability(): void {
        FakePluginHttp::fake(['*call.cgi' => FakePluginHttp::response("code=200\nEOF\n")]);

        $adapter = new DomainResellingAdapter(new DomainResellingClient($this->connection()), $this->connection());

        $this->assertFalse($adapter->capabilities()->allows(DomainCapabilityArea::Invoices));
        $this->assertTrue($adapter->capabilities()->allows(DomainCapabilityArea::Domains));

        $this->expectException(DomainCapabilityBlockedException::class);
        $adapter->execute('QueryInvoiceList');
    }
}

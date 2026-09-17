<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UrlSafetyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Support;

use App\Support\UrlSafety;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class UrlSafetyTest extends TestCase {
    /** @return array<string, array{string, bool}> */
    public static function configTargets(): array {
        return [
            'public hostname'          => ['https://hooks.example.com/in', true],
            'http hostname'            => ['http://example.org/path', true],
            'nicht auflösbarer host'   => ['https://nicht-existent.invalid/x', true], // DNS erst zur Laufzeit
            'localhost'                => ['http://localhost/x', false],
            'loopback v4 literal'      => ['http://127.0.0.1/x', false],
            'loopback v6 literal'      => ['http://[::1]/x', false],
            'private 10.x literal'     => ['http://10.0.0.5/x', false],
            'private 192.168 literal'  => ['https://192.168.1.10/x', false],
            'cloud-metadata literal'   => ['http://169.254.169.254/latest/meta-data/', false],
            'kein schema'              => ['ftp://example.com/x', false],
            'leerer host'              => ['https:///pfad', false],
            'müll'                     => ['nonsense', false],
        ];
    }

    #[DataProvider('configTargets')]
    public function test_acceptable_external_http_url(string $url, bool $expected): void {
        $this->assertSame($expected, UrlSafety::isAcceptableExternalHttpUrl($url), $url);
    }

    public function test_runtime_check_blocks_ip_literals_in_internal_ranges(): void {
        // Laufzeit-Guard: IP-Literale lösen auf sich selbst auf → privat = blockiert.
        $this->assertFalse(UrlSafety::isPubliclyRoutableHttpUrl('http://127.0.0.1/x'));
        $this->assertFalse(UrlSafety::isPubliclyRoutableHttpUrl('http://10.1.2.3/x'));
        $this->assertFalse(UrlSafety::isPubliclyRoutableHttpUrl('http://169.254.169.254/'));
        $this->assertFalse(UrlSafety::isPubliclyRoutableHttpUrl('http://[::1]/x'));
        // Öffentliche IP-Literale sind erlaubt.
        $this->assertTrue(UrlSafety::isPubliclyRoutableHttpUrl('https://8.8.8.8/x'));
    }

    /** @return array<string, array{string, bool}> */
    public static function redirectTargets(): array {
        return [
            'relativer pfad'        => ['/diary/123', true],
            'relativ mit query'     => ['/notifications?tab=open', true],
            'same-origin absolut'   => ['https://app.local/diary', true],
            'fremder host'          => ['https://evil.example/phish', false],
            'protokoll-relativ'     => ['//evil.example/phish', false],
            'backslash-trick'       => ['/\\evil.example', false],
            'backslash-authority'   => ['https://evil.example\\@app.local', false],
            'tab-trick'             => ["/\t/evil.example", false],
            'zeilenumbruch'         => ["/diary\r\nLocation: https://evil.example", false],
            'userinfo'              => ['https://evil.example@app.local/diary', false],
            'fremdes schema'        => ['javascript://app.local/%0Aalert(1)', false],
            'leer'                  => ['', false],
        ];
    }

    #[DataProvider('redirectTargets')]
    public function test_same_origin_or_relative(string $url, bool $expected): void {
        $this->assertSame($expected, UrlSafety::isSameOriginOrRelative($url, 'app.local'), $url);
    }

    /**
     * Sicherheitsaudit 2026-09-17 (ssrf-5): Prüfung und Verbindung müssen
     * dieselbe Adresse benutzen, sonst wechselt ein Angreifer-DNS dazwischen.
     */
    public function test_pinned_resolution_is_fail_closed(): void {
        $this->assertNull(UrlSafety::pinnedResolution('https://kein-eintrag.invalid/hook'));
        $this->assertNull(UrlSafety::pinnedResolution('https://127.0.0.1/hook'));
        $this->assertNull(UrlSafety::pinnedResolution('ftp://example.com/hook'));

        // IP-Literale sind bereits die Zieladresse — nichts zu binden.
        $this->assertSame([], UrlSafety::pinnedResolution('https://93.184.216.34/hook'));
        // Ausdrücklich freigegebenes internes Ziel bleibt erreichbar.
        $this->assertSame([], UrlSafety::pinnedResolution('http://192.168.0.5:8080/hook', true));
    }

    /**
     * Sicherheitsaudit 2026-09-17 (ssrf-7): CGNAT, NAT64, 6to4 und Teredo
     * gelten der PHP-Filterprüfung als öffentlich, führen aber ins interne Netz
     * (100.100.100.200 ist der Metadatendienst von Alibaba Cloud).
     */
    public function test_transition_and_carrier_ranges_are_not_public(): void {
        foreach ([
            'http://100.100.100.200/latest/meta-data/',
            'http://198.18.0.1/',
            'http://192.0.0.1/',
            'http://[64:ff9b::7f00:1]/',
            'http://[2002:7f00:1::]/',
            'http://[2001:0:1::]/',
            'http://[fec0::1]/',
        ] as $url) {
            $this->assertFalse(UrlSafety::isPubliclyRoutableHttpUrl($url), $url . ' darf nicht als öffentlich gelten.');
            $this->assertNull(UrlSafety::pinnedResolution($url), $url . ' darf nicht gebunden werden.');
        }
    }
}

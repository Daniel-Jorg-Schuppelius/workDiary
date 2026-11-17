<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginNetworksTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Services\Auth\LoginPolicy\{LoginDeviceCookie, LoginNetworks};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature 205 (MVP-1655): Netze (CIDR v4/v6, DNS-Namen nur aus dem Cache,
 * letzter Stand bei DNS-Ausfall) und das signierte Gerätecookie.
 */
final class LoginNetworksTest extends TestCase {
    /** @var array<string, list<string>> */
    public static array $dns = [];

    private function networks(): LoginNetworks {
        return new class extends LoginNetworks {
            protected function lookup(string $host): array {
                return LoginNetworksTest::$dns[$host] ?? [];
            }
        };
    }

    public function test_eintraege_werden_geprueft(): void {
        foreach (['198.51.100.7', '198.51.100.0/24', '2001:db8::/32', '2001:db8::1', 'buero.example.org', 'vpn.xy.de'] as $entry) {
            $this->assertTrue(LoginNetworks::isValidEntry($entry), $entry);
        }
        foreach (['198.51.100.0/33', '2001:db8::/129', '300.1.1.1', 'localhost', 'ex ample.org', '-x.example.org', '198.51.100.0/abc', '', '0.0.0.0/0', '::/0'] as $entry) {
            $this->assertFalse(LoginNetworks::isValidEntry($entry), $entry);
        }
        $this->assertSame('buero.example.org', LoginNetworks::normalizeEntry('  Buero.Example.ORG. '));
    }

    public function test_cidr_und_einzeladressen_fuer_ipv4_und_ipv6(): void {
        $networks = $this->networks();
        $entries = ['198.51.100.0/24', '192.0.2.10', '2001:db8:10::/48'];

        $this->assertTrue($networks->contains(1, $entries, '198.51.100.250'));
        $this->assertTrue($networks->contains(1, $entries, '192.0.2.10'));
        $this->assertFalse($networks->contains(1, $entries, '192.0.2.11'));
        $this->assertTrue($networks->contains(1, $entries, '2001:db8:10:ffff::1'));
        $this->assertFalse($networks->contains(1, $entries, '2001:db8:11::1'));
        $this->assertTrue($networks->contains(1, $entries, '::ffff:198.51.100.3'));
        $this->assertFalse($networks->contains(1, $entries, null));
        $this->assertFalse($networks->contains(1, $entries, 'kein-ip'));
        $this->assertFalse($networks->contains(1, [], '198.51.100.250'));
    }

    public function test_dns_namen_nur_aus_dem_cache_und_letzter_stand_bei_ausfall(): void {
        Cache::flush();
        $networks = $this->networks();
        $entries = ['buero.example.org'];

        // Noch nie aufgelöst: erlaubt nichts, und contains() fragt kein DNS.
        self::$dns = ['buero.example.org' => ['203.0.113.40']];
        $this->assertFalse($networks->contains(7, $entries, '203.0.113.40'));

        $networks->refresh(7, $entries);
        $this->assertTrue($networks->contains(7, $entries, '203.0.113.40'));
        $this->assertNotNull($networks->resolvedAt(7));

        // DNS fällt aus: der letzte Stand bleibt.
        self::$dns = [];
        $networks->refresh(7, $entries);
        $this->assertTrue($networks->contains(7, $entries, '203.0.113.40'));

        // Neue Adresse: die alte gilt nicht mehr.
        self::$dns = ['buero.example.org' => ['203.0.113.41']];
        $networks->refresh(7, $entries);
        $this->assertFalse($networks->contains(7, $entries, '203.0.113.40'));
        $this->assertTrue($networks->contains(7, $entries, '203.0.113.41'));

        // Andere Organisation sieht die Auflösung nicht.
        $this->assertFalse($networks->contains(8, $entries, '203.0.113.41'));

        // Ohne DNS-Namen verschwindet der Eintrag.
        $networks->refresh(7, ['198.51.100.0/24']);
        $this->assertSame([], $networks->resolvedHosts(7));
    }

    public function test_geraetecookie_ist_signiert(): void {
        $request = Request::create('/login', 'POST');
        $hash = LoginDeviceCookie::ensure($request);
        $this->assertSame(64, strlen($hash));
        $this->assertSame($hash, LoginDeviceCookie::ensure($request), 'In derselben Anfrage kein zweites Cookie.');

        $queued = collect(\Illuminate\Support\Facades\Cookie::getQueuedCookies())->firstWhere(fn ($c) => $c->getName() === LoginDeviceCookie::NAME);
        $this->assertNotNull($queued);
        $this->assertTrue($queued->isHttpOnly());
        $value = (string) $queued->getValue();
        $this->assertStringStartsWith('v1.', $value);
        $this->assertStringNotContainsString($hash, $value, 'Das Cookie trägt nicht den gespeicherten Hash.');

        $valid = Request::create('/', 'GET', [], [LoginDeviceCookie::NAME => $value]);
        $this->assertSame($hash, LoginDeviceCookie::hashFrom($valid));

        $tampered = Request::create('/', 'GET', [], [LoginDeviceCookie::NAME => substr($value, 0, -1) . (str_ends_with($value, 'a') ? 'b' : 'a')]);
        $this->assertNull(LoginDeviceCookie::hashFrom($tampered));

        $forged = Request::create('/', 'GET', [], [LoginDeviceCookie::NAME => 'v1.' . str_repeat('A', 43) . '.' . str_repeat('0', 64)]);
        $this->assertNull(LoginDeviceCookie::hashFrom($forged));
    }

    public function test_aufloesung_verfaellt_nach_24_stunden_ohne_erfolg(): void {
        Cache::flush();
        $networks = $this->networks();
        self::$dns = ['dyn.example.org' => ['203.0.113.50']];
        $networks->refresh(9, ['dyn.example.org']);

        self::$dns = [];
        $this->travel(23)->hours();
        $networks->refresh(9, ['dyn.example.org']);
        $this->assertTrue($networks->contains(9, ['dyn.example.org'], '203.0.113.50'));

        $this->travel(2)->hours();
        $networks->refresh(9, ['dyn.example.org']);
        $this->assertFalse($networks->contains(9, ['dyn.example.org'], '203.0.113.50'));
        $this->assertSame([], $networks->resolvedHosts(9));
    }

    public function test_interne_adressen_aus_dns_nur_mit_freigabe(): void {
        Cache::flush();
        $networks = $this->networks();
        self::$dns = ['intern.example.org' => ['10.1.2.3', '127.0.0.1', '198.51.100.9']];

        $networks->refresh(3, ['intern.example.org']);
        $this->assertSame(['intern.example.org' => ['198.51.100.9']], $networks->resolvedHosts(3));
        $this->assertFalse($networks->contains(3, ['intern.example.org'], '127.0.0.1'));

        config(['security.login_policy.dns_allow_private' => true]);
        $networks->refresh(3, ['intern.example.org']);
        $this->assertTrue($networks->contains(3, ['intern.example.org'], '10.1.2.3'));
        $this->assertSame(['intern.example.org' => ['public' => ['198.51.100.9'], 'internal' => 2]], $networks->displayHosts(3));
    }

    public function test_hoechstens_zehn_dns_namen_werden_aufgeloest(): void {
        Cache::flush();
        $networks = $this->networks();
        $hosts = array_map(static fn (int $i): string => "n{$i}.example.org", range(1, 12));
        self::$dns = array_fill_keys($hosts, ['198.51.100.1']);

        $this->assertCount(LoginNetworks::MAX_HOSTNAMES, $networks->refresh(4, $hosts));
    }

    public function test_anzeige_kuerzt_adressen_und_erkennt_interne(): void {
        $this->assertSame('203.0.113.0/24', LoginNetworks::masked('203.0.113.77'));
        $this->assertSame('2001:db8:10::/48', LoginNetworks::masked('2001:db8:10:ffff::1'));
        $this->assertSame('198.51.100.0/24', LoginNetworks::masked('::ffff:198.51.100.3'));
        $this->assertNull(LoginNetworks::masked('kein-ip'));
        $this->assertFalse(LoginNetworks::isPublic('10.0.0.5'));
        $this->assertFalse(LoginNetworks::isPublic('127.0.0.1'));
        $this->assertTrue(LoginNetworks::isPublic('198.51.100.20'));
    }
}

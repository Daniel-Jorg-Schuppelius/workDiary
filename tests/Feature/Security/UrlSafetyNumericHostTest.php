<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UrlSafetyNumericHostTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Support\UrlSafety;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sicherheitsscan 2026-08-23, S-03: **der SSRF-Guard lief fail-open.**
 *
 * `inet_aton` — und damit libcurl — nimmt eine IPv4-Adresse auch in Hex-,
 * Oktal- und Kurzform entgegen: `0xa9fea9fe` ist 169.254.169.254, der
 * Cloud-Metadatendienst. `FILTER_VALIDATE_IP` lehnt diese Schreibweisen ab und
 * `gethostbynamel()` löst sie nicht auf; die Prüfschleife lief also über eine
 * leere Adressliste und gab „öffentlich routbar" zurück. Betroffen war jede
 * geschützte Senke: Webhooks, Katalogabruf, SSO-Discovery, News-Feed,
 * Update-Check.
 */
class UrlSafetyNumericHostTest extends TestCase {
    /** @return array<string, array{0: string}> */
    public static function bypassProvider(): array {
        return [
            'hex kompakt (Metadatendienst)' => ['http://0xa9fea9fe/latest/meta-data/'],
            'hex kompakt (loopback)' => ['http://0x7f000001/'],
            'dezimal kompakt' => ['http://2852039166/'],
            'oktal punktiert' => ['http://0177.0.0.1/'],
            'kurzform' => ['http://127.1/'],
            'oktal vollständig' => ['http://0251.0376.0251.0376/'],
            'hex gemischt' => ['http://0x7f.0.0.1/'],
        ];
    }

    /** @return array<string, array{0: string}> */
    public static function legitimateProvider(): array {
        return [
            'Name' => ['https://example.com/hook'],
            'Unterdomäne' => ['https://sub.domain.example/path'],
            'öffentliche IP' => ['https://93.184.216.34/x'],
            'öffentlicher Resolver' => ['https://8.8.8.8/'],
        ];
    }

    #[DataProvider('bypassProvider')]
    public function test_zahlenschreibweisen_werden_abgewiesen(string $url): void {
        $this->assertFalse(UrlSafety::isPubliclyRoutableHttpUrl($url), 'Laufzeit-Guard ließ ' . $url . ' durch.');
        $this->assertFalse(UrlSafety::isAcceptableExternalHttpUrl($url), 'Konfigurations-Guard ließ ' . $url . ' durch.');
    }

    #[DataProvider('legitimateProvider')]
    public function test_echte_ziele_bleiben_erlaubt(string $url): void {
        // Die Gegenprobe ist der eigentliche Prüfgegenstand: eine bloße
        // öffentliche IP als Webhook-Ziel ist normal und darf nicht in die
        // Zahlen-Sperre laufen.
        $this->assertTrue(UrlSafety::isAcceptableExternalHttpUrl($url), 'Konfigurations-Guard blockte ' . $url . '.');
        $this->assertTrue(UrlSafety::isPubliclyRoutableHttpUrl($url), 'Laufzeit-Guard blockte ' . $url . '.');
    }

    public function test_auch_blosse_hostnamen_fuer_ftp_sftp(): void {
        $this->assertFalse(UrlSafety::isAcceptableExternalHost('0xa9fea9fe'));
        $this->assertFalse(UrlSafety::isPubliclyRoutableHost('0x7f000001'));
        $this->assertTrue(UrlSafety::isAcceptableExternalHost('sftp.example.com'));
    }

    public function test_die_bekannten_internen_ziele_bleiben_gesperrt(): void {
        foreach (['http://127.0.0.1/', 'http://169.254.169.254/', 'http://10.0.0.1/', 'http://192.168.1.1/', 'http://localhost/'] as $url) {
            $this->assertFalse(UrlSafety::isPubliclyRoutableHttpUrl($url), $url);
        }
    }
}

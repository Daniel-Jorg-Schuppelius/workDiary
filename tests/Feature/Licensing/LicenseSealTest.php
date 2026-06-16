<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseSealTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Services\Licensing\LicenseSeal;
use Tests\TestCase;

final class LicenseSealTest extends TestCase {
    /** @var list<string> */
    private array $written = [];

    protected function tearDown(): void {
        foreach ($this->written as $path) {
            @unlink($path);
        }
        $this->written = [];
        LicenseSeal::flushCache();
        parent::tearDown();
    }

    /**
     * Schreibt eine Seal-Datei mit dem gegebenen rohen PHP-Inhalt und richtet
     * die Konfiguration darauf aus. Jeder Aufruf nutzt einen eindeutigen
     * Dateinamen, damit require/opcache nicht über Testfälle hinweg cachen.
     */
    private function seal(string $rawPhp): void {
        $relative = 'private/license-seal-test-' . substr(md5($rawPhp . count($this->written)), 0, 12) . '.php';
        $absolute = storage_path('app/' . $relative);
        @mkdir(dirname($absolute), 0775, true);
        file_put_contents($absolute, $rawPhp);
        $this->written[] = $absolute;

        config(['license.seal_path' => $relative]);
        LicenseSeal::flushCache();
    }

    public function test_unsealed_when_file_missing(): void {
        config(['license.seal_path' => 'private/does-not-exist-' . uniqid() . '.php']);
        LicenseSeal::flushCache();

        $this->assertFalse(LicenseSeal::isSealed());
        $this->assertSame('', LicenseSeal::publicKey());
        $this->assertSame([], LicenseSeal::files());
        $this->assertSame('', LicenseSeal::sealedAt());
    }

    public function test_reads_valid_seal_file(): void {
        $this->seal(<<<'PHP'
            <?php return [
                'public_key' => 'PUBKEY-XYZ',
                'files' => ['app/Foo.php' => 'abc123', 'app/Bar.php' => 'def456'],
                'sealed_at' => '2026-05-18T10:00:00+00:00',
            ];
            PHP);

        $this->assertTrue(LicenseSeal::isSealed());
        $this->assertSame('PUBKEY-XYZ', LicenseSeal::publicKey());
        $this->assertSame('2026-05-18T10:00:00+00:00', LicenseSeal::sealedAt());
        $this->assertSame(
            ['app/Foo.php' => 'abc123', 'app/Bar.php' => 'def456'],
            LicenseSeal::files(),
        );
    }

    public function test_files_filters_non_string_entries(): void {
        $this->seal(<<<'PHP'
            <?php return [
                'public_key' => 'K',
                'files' => ['ok.php' => 'hash', 'bad.php' => 123, 7 => 'numkey'],
                'sealed_at' => 'now',
            ];
            PHP);

        // Nur String=>String-Paare bleiben erhalten.
        $this->assertSame(['ok.php' => 'hash'], LicenseSeal::files());
    }

    public function test_falls_back_to_unsealed_when_not_an_array(): void {
        $this->seal('<?php return "not-an-array";');

        $this->assertFalse(LicenseSeal::isSealed());
        $this->assertSame([], LicenseSeal::files());
    }

    public function test_falls_back_when_required_keys_missing(): void {
        $this->seal('<?php return ["public_key" => "K"];'); // files/sealed_at fehlen

        $this->assertFalse(LicenseSeal::isSealed());
        $this->assertSame('', LicenseSeal::publicKey());
    }

    public function test_corrupt_file_does_not_throw(): void {
        // Syntaxfehler ⇒ require wirft ParseError (Throwable) ⇒ wird gefangen.
        $this->seal('<?php return [');

        $this->assertFalse(LicenseSeal::isSealed());
        $this->assertSame([], LicenseSeal::files());
    }

    public function test_cache_is_reset_by_flush(): void {
        $this->seal(<<<'PHP'
            <?php return ['public_key' => 'FIRST', 'files' => [], 'sealed_at' => 't'];
            PHP);
        $this->assertSame('FIRST', LicenseSeal::publicKey());

        // Zweite Seal-Datei ⇒ ohne flush bliebe der gecachte Wert bestehen.
        $this->seal(<<<'PHP'
            <?php return ['public_key' => 'SECOND', 'files' => [], 'sealed_at' => 't'];
            PHP);
        $this->assertSame('SECOND', LicenseSeal::publicKey());
    }
}

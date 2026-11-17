<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceLegacyPageImagesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Console\Commands\Invoicing\IncomingInvoicesPruneLegacyPageImagesCommand;
use App\Models\Invoicing\IncomingEInvoice;
use App\Services\Invoicing\Recognition\PageImages;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Seitenbilder der Rechnungserkennung (Feature 208, M5): seit der Ablage je
 * Organisation (`recognition/<organisation>/<sha256>`) räumt nichts mehr die
 * alten Ordner `recognition/<sha256>` — der einmalige Befehl
 * {@see IncomingInvoicesPruneLegacyPageImagesCommand} tut es, mit Trockenlauf.
 */
final class IncomingInvoiceLegacyPageImagesTest extends TestCase {
    private const string SHA_A = 'a3f1c2d4e5b6a7980112233445566778899aabbccddeeff00112233445566778';

    private const string SHA_B = '0000000000000000000000000000000000000000000000000000000000000001';

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $disk = Storage::disk('local');
        // Alte Ablage (ohne Organisation).
        $disk->put('recognition/' . self::SHA_A . '/page-1.png', 'png');
        $disk->put('recognition/' . self::SHA_B . '/page-2.png', 'png');
        // Neue Ablage je Organisation und Fremdes, das nicht wie ein Prüfwert heißt.
        $disk->put('recognition/7/' . self::SHA_A . '/page-1.png', 'png');
        $disk->put('recognition/notizen/liesmich.txt', 'x');
        $disk->put('incoming-pages/7/page-1.png', 'png');
    }

    public function test_new_layout_is_per_organization(): void {
        $incoming = new IncomingEInvoice;
        $incoming->forceFill(['organization_id' => 7, 'sha256' => self::SHA_A]);

        $this->assertSame('recognition/7/' . self::SHA_A, PageImages::directoryFor($incoming));
        $this->assertSame(['recognition/' . self::SHA_B, 'recognition/' . self::SHA_A], PageImages::legacyDirectories());
    }

    public function test_dry_run_lists_without_deleting(): void {
        // Sortiert ausgegeben: SHA_B (0…) vor SHA_A (a…).
        $this->artisan('incoming-invoices:prune-legacy-page-images', ['--dry-run' => true])
            ->expectsOutput('recognition/' . self::SHA_B)
            ->expectsOutput('recognition/' . self::SHA_A)
            ->expectsOutputToContain('[dry-run] 2')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('local')->exists('recognition/' . self::SHA_A . '/page-1.png'));
        $this->assertTrue(Storage::disk('local')->exists('recognition/' . self::SHA_B . '/page-2.png'));
    }

    public function test_prune_removes_only_the_old_folders(): void {
        $this->artisan('incoming-invoices:prune-legacy-page-images')
            ->expectsOutputToContain('2 Ordner der alten Ablage entfernt.')
            ->assertSuccessful();

        $disk = Storage::disk('local');
        $this->assertFalse($disk->exists('recognition/' . self::SHA_A));
        $this->assertFalse($disk->exists('recognition/' . self::SHA_B));
        $this->assertTrue($disk->exists('recognition/7/' . self::SHA_A . '/page-1.png'), 'Die Ablage je Organisation bleibt.');
        $this->assertTrue($disk->exists('recognition/notizen/liesmich.txt'));
        $this->assertTrue($disk->exists('incoming-pages/7/page-1.png'));
        $this->assertSame([], PageImages::legacyDirectories());

        // Ein zweiter Lauf findet nichts mehr.
        $this->artisan('incoming-invoices:prune-legacy-page-images')
            ->expectsOutputToContain('0 Ordner der alten Ablage entfernt.')
            ->assertSuccessful();
    }
}

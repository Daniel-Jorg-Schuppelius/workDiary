<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImporterPruneCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Console\Commands\Reselling\PruneResaleImportsCommand;
use App\Enums\Reselling\{ImportStatus, SubscriptionProvider};
use App\Models\Reselling\ResaleImport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Aufbewahrung der Import-Dateien (Feature 152, Review 2026-09-10 A8):
 * `resale:prune-imports` löscht die abgelegten Anbieter-Exporte nach der
 * Frist, der Datensatz bleibt; Löschen eines Imports nimmt die Datei mit.
 *
 * @see PruneResaleImportsCommand
 */
class ImporterPruneCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake(ResaleImport::DISK);
        $this->travelTo('2026-09-10 12:00:00');
    }

    private function import(string $name, int $ageDays, ?string $path, bool $withFile = true): ResaleImport {
        if ($path !== null && $withFile) {
            Storage::disk(ResaleImport::DISK)->put($path, 'Firma;Produkt');
        }
        $import = ResaleImport::query()->create([
            'organization_id' => $this->organization->id,
            'provider' => SubscriptionProvider::TelekomMarketplace,
            'kind' => ResaleImport::KIND_PURCHASES,
            'file_name' => $name,
            'file_path' => $path,
            'status' => ImportStatus::Done,
            'rows_total' => 3,
            'issues' => ['Zeile 2: unlesbar', 'Zeile 3: unlesbar'],
        ]);
        $import->forceFill(['created_at' => CarbonImmutable::now()->subDays($ageDays)])->save();

        return $import;
    }

    public function test_prune_removes_old_files_but_keeps_the_records(): void {
        $disk = Storage::disk(ResaleImport::DISK);
        $old = $this->import('alt.csv', 100, 'resale/1/a/purchases.csv');
        $recent = $this->import('neu.csv', 10, 'resale/1/b/purchases.csv');
        $orphan = $this->import('weg.csv', 120, 'resale/1/c/purchases.csv', withFile: false);
        $none = $this->import('ohne.csv', 200, null);

        $this->artisan('resale:prune-imports', ['--dry-run' => true])
            ->expectsOutputToContain('2 Import-Dateien älter als 90 Tage')
            ->assertSuccessful();
        $disk->assertExists('resale/1/a/purchases.csv');
        $this->assertSame('resale/1/a/purchases.csv', $old->fresh()?->file_path, '--dry-run ändert nichts');

        $this->artisan('resale:prune-imports')
            ->expectsOutputToContain('1 Import-Dateien gelöscht, 1 Pfade ohne Datei bereinigt')
            ->assertSuccessful();

        $disk->assertMissing('resale/1/a/purchases.csv');
        $disk->assertExists('resale/1/b/purchases.csv');
        $this->assertNull($old->fresh()?->file_path);
        $this->assertNull($orphan->fresh()?->file_path);
        $this->assertSame('resale/1/b/purchases.csv', $recent->fresh()?->file_path, 'jünger als die Frist');
        $this->assertSame(4, ResaleImport::query()->count(), 'Datensätze bleiben als Historie');
        $this->assertSame(3, $old->fresh()?->rows_total);
        $this->assertSame(2, $old->fresh()?->issueCount());
        $this->assertSame(['Zeile 2: unlesbar'], $old->fresh()?->issuesPreview(1));
        $this->assertNull($none->fresh()?->file_path);

        // Kürzere Frist erfasst auch den jungen Import.
        $this->artisan('resale:prune-imports', ['--days' => 5])->expectsOutputToContain('1 Import-Dateien gelöscht')->assertSuccessful();
        $disk->assertMissing('resale/1/b/purchases.csv');

        $this->artisan('resale:prune-imports', ['--days' => 0])->assertFailed();
    }

    public function test_deleting_an_import_removes_its_file(): void {
        $disk = Storage::disk(ResaleImport::DISK);
        $import = $this->import('alt.csv', 1, 'resale/1/d/purchases.csv');
        $disk->assertExists('resale/1/d/purchases.csv');

        $import->delete();

        $disk->assertMissing('resale/1/d/purchases.csv');
        $this->assertSame(0, ResaleImport::query()->count());
    }
}

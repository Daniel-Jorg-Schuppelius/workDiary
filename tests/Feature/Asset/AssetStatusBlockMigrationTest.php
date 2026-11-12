<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetStatusBlockMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Exceptions\AssetNotUsableException;
use App\Models\Asset\{Asset, AssetBlock};
use App\Services\Asset\AssetUsageGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1629: Der Sperren-Nachzug der Migration 2027_04_17_160000 gegen
 * Bestandsdaten — Status „gesperrt“/„in Reparatur“ ohne Sperre bekommt genau
 * eine, Assets mit Status-Sperre oder blockierendem Defekt keine zweite.
 */
final class AssetStatusBlockMigrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function migration(): Migration {
        return require database_path('migrations/2027_04_17_160000_add_asset_decommission_and_return_columns.php');
    }

    public function test_legacy_status_rows_get_exactly_one_block(): void {
        $this->setUpOrganization();
        $migration = $this->migration();
        $migration->down();

        $asset = fn (string $status): int => (int) DB::table('assets')->insertGetId([
            'organization_id' => $this->organization->id, 'asset_no' => 'A-' . uniqid(), 'name' => 'Gerät ' . $status,
            'status' => $status, 'asset_class' => 'device', 'owned_by' => 'org', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $blocked = $asset('blocked');
        $repair = $asset('inRepair');
        $active = $asset('active');
        $alreadyBlocked = $asset('blocked');
        DB::table('asset_blocks')->insert([
            'organization_id' => $this->organization->id, 'asset_id' => $alreadyBlocked, 'reason' => 'manual',
            'source_type' => 'assets', 'source_id' => $alreadyBlocked, 'blocked_from' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $byDefect = $asset('blocked');
        $deletedDefect = $asset('blocked');
        foreach ([[$byDefect, null], [$deletedDefect, now()]] as [$id, $deletedAt]) {
            DB::table('asset_defects')->insert([
                'organization_id' => $this->organization->id, 'asset_id' => $id, 'title' => 'Kabel beschädigt', 'severity' => 'high',
                'status' => 'open', 'blocks_usage' => true, 'reported_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $deletedAt,
            ]);
        }

        $migration->up();

        $count = fn (int $id): int => AssetBlock::query()->where('asset_id', $id)->count();
        $this->assertSame([1, 1, 0, 1, 0, 1], [$count($blocked), $count($repair), $count($active), $count($alreadyBlocked), $count($byDefect), $count($deletedDefect)]);
        $this->assertSame('maintenance', (string) DB::table('asset_blocks')->where('asset_id', $repair)->value('reason'));
        $this->assertSame('manual', (string) DB::table('asset_blocks')->where('asset_id', $blocked)->value('reason'));

        // Die Einsatzprüfung liest nur noch das Sperrmodell.
        $this->expectException(AssetNotUsableException::class);
        app(AssetUsageGuard::class)->ensureUsable(Asset::query()->findOrFail($blocked), 'rental');
    }
}

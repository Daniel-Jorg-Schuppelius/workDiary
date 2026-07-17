<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSessionImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Enums\Asset\AssetClass;
use App\Enums\Import\{ImportEntity, ImportRunState};
use App\Jobs\ProcessCsvImportJob;
use App\Models\{Asset, Customer, ImportRun, RemotePendingSession, TimeEntry, User};
use App\Plugins\RemoteSupport\Providers\AnyDeskClient;
use App\Plugins\RemoteSupport\RemoteSupportService;
use App\Services\Import\EntitySpecRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class RemoteSessionImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();
    }

    /** AnyDesk-Export inkl. `sep=`-Vorzeile: eine bekannte, eine unbekannte Geräte-ID. */
    private function anydeskCsv(): string {
        return implode("\n", [
            'sep=,',
            'Sitzungs-ID,Von ID,Von Alias,Nach ID,Nach Alias,Beginn,Ende,Dauer,Kommentar',
            '"1777163302534484","1755686633",,"362798056","apl7-3@ad","28.05.2026, 09:42:09","28.05.2026, 10:44:08","3719","adrian einrichtung"',
            '"1777161856386827","1755686633",,"999999999",,"27.05.2026, 08:40:26","27.05.2026, 08:46:01","335","unbekanntes gerät"',
        ]) . "\n";
    }

    private function preflight(User $admin): ImportRun {
        $file = UploadedFile::fake()->createWithContent('sessions.csv', $this->anydeskCsv());

        $this->actingAs($admin)
            ->post(route('admin.imports.preflight'), [
                'entity' => ImportEntity::RemoteSessions->value,
                'file' => $file,
            ])->assertRedirect();

        return ImportRun::query()->latest('id')->firstOrFail();
    }

    public function test_preflight_strips_sep_line_and_maps_anydesk_headers(): void {
        $run = $this->preflight($this->orgAdmin());

        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);
        $this->assertSame(ImportEntity::RemoteSessions, $run->entity);
        $this->assertSame(2, $run->rows_total);
        $this->assertSame(0, $run->rows_failed);

        // Die gespeicherte Datei wurde um die `sep=`-Vorzeile bereinigt.
        $stored = (string) Storage::disk('local')->get($run->storage_path);
        $this->assertStringStartsNotWith('sep=', $stored);
        $this->assertStringStartsWith('Sitzungs-ID', $stored);
    }

    public function test_full_import_books_matched_device_and_queues_unknown(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'category_code' => 'notebook',
            'customer_id' => $customer->id,
        ]);
        (new RemoteSupportService)->setRemoteId($asset, AnyDeskClient::ID, '362798056');

        $run = $this->preflight($this->orgAdmin());
        (new ProcessCsvImportJob($run->id))->handle(app(EntitySpecRegistry::class));
        $run->refresh();

        $this->assertSame(ImportRunState::Succeeded, $run->state);
        $this->assertSame(1, $run->rows_created);
        $this->assertSame(1, $run->rows_skipped); // unbekannte ID → Pending

        $project = $asset->customer->defaultProject();
        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(61, $entry->minutes); // 09:42:09 – 10:44:08

        $this->assertDatabaseHas('remote_pending_sessions', [
            'organization_id' => $this->organization->id,
            'provider' => 'anydesk',
            'remote_id' => '999999999',
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);
    }

    public function test_repeated_import_is_idempotent(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'category_code' => 'notebook',
            'customer_id' => $customer->id,
        ]);
        (new RemoteSupportService)->setRemoteId($asset, AnyDeskClient::ID, '362798056');

        $admin = $this->orgAdmin();
        foreach ([0, 1] as $_) {
            $run = $this->preflight($admin);
            (new ProcessCsvImportJob($run->id))->handle(app(EntitySpecRegistry::class));
        }

        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertSame(1, RemotePendingSession::query()->count());
    }

    public function test_preflight_fails_without_device_id_column(): void {
        $csv = "Sitzungs-ID,Beginn,Ende\n\"1\",\"28.05.2026, 09:42:09\",\"28.05.2026, 10:00:00\"\n";
        $file = UploadedFile::fake()->createWithContent('bad.csv', $csv);

        $this->actingAs($this->orgAdmin())
            ->post(route('admin.imports.preflight'), [
                'entity' => ImportEntity::RemoteSessions->value,
                'file' => $file,
            ])->assertRedirect();

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::Failed, $run->state);
        $this->assertGreaterThan(0, $run->errors()->count());
    }
}

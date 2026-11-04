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
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Integration\ImportRun;
use App\Models\Platform\User;
use App\Models\Time\TimeEntry;
use App\Plugins\RemoteSupport\Api\{AnyDeskClient, RustDeskProClient};
use App\Plugins\RemoteSupport\Enums\RemotePendingSessionStatus;
use App\Plugins\RemoteSupport\Models\RemotePendingSession;
use App\Plugins\RemoteSupport\Services\RemoteDeviceRegistry;
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

    /** Verbindungsprotokoll von RustDesk Server Pro, „Export as csv“ mit englischer Konsole (Datum per toLocaleString). */
    private function rustdeskCsvEn(): string {
        return implode("\n", [
            'Type,Controlled Device,Controlling Side,Start Time,End Time,Duration,Authentication,Note',
            '"Normal","123 456 789","TECH-NB (203.0.113.5)","11/2/2026, 10:00:00 AM","11/2/2026, 10:45:00 AM","45m","Permanent Password","Drucker eingerichtet"',
            '"File Transfer","987 654 321","TECH-NB (203.0.113.5)","11/2/2026, 1:00:00 PM","11/2/2026, 1:05:00 PM","5m","Permanent Password",""',
        ]) . "\n";
    }

    /** Derselbe Export aus einer deutschen Konsole: übersetzte Spaltenköpfe, deutsches Datum. */
    private function rustdeskCsvDe(): string {
        return implode("\n", [
            'Type,Gesteuertes Gerät,Steuernde Seite,Startzeit,Endzeit,Duration,Authentication,Note',
            '"Normal","123456789","TECH-NB","2.11.2026, 10:00:00","2.11.2026, 10:45:00","45m","Permanentes Passwort","Drucker eingerichtet"',
        ]) . "\n";
    }

    private function preflight(User $admin, ?string $csv = null): ImportRun {
        $file = UploadedFile::fake()->createWithContent('sessions.csv', $csv ?? $this->anydeskCsv());

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
        (new RemoteDeviceRegistry)->setRemoteId($asset, AnyDeskClient::ID, '362798056');

        $run = $this->preflight($this->orgAdmin());
        app()->call([new ProcessCsvImportJob($run->id), 'handle']);
        $run->refresh();

        $this->assertSame(ImportRunState::Succeeded, $run->state);
        $this->assertSame(1, $run->rows_created);
        $this->assertSame(1, $run->rows_skipped); // unbekannte ID → Pending

        $project = $asset->customer->defaultProject();
        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(61, $entry->minutes); // 09:42:09 – 10:44:08
        // AnyDesk exportiert Ortszeit (Berlin, Sommerzeit) — gebucht wird UTC.
        $this->assertSame('2026-05-28 07:42:09', $entry->getRawOriginal('started_at'));
        $this->assertSame('2026-05-28', $entry->date->toDateString());

        $this->assertDatabaseHas('remote_pending_sessions', [
            'organization_id' => $this->organization->id,
            'provider' => 'anydesk',
            'remote_id' => '999999999',
            'status' => RemotePendingSessionStatus::Open->value,
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
        (new RemoteDeviceRegistry)->setRemoteId($asset, AnyDeskClient::ID, '362798056');

        $admin = $this->orgAdmin();
        foreach ([0, 1] as $_) {
            $run = $this->preflight($admin);
            app()->call([new ProcessCsvImportJob($run->id), 'handle']);
        }

        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertSame(1, RemotePendingSession::query()->count());
    }

    public function test_rustdesk_pro_export_is_detected_by_header_and_booked(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'category_code' => 'workstation',
            'customer_id' => $customer->id,
        ]);
        (new RemoteDeviceRegistry)->setRemoteId($asset, RustDeskProClient::ID, '123456789');

        $run = $this->preflight($this->orgAdmin(), $this->rustdeskCsvEn());
        app()->call([new ProcessCsvImportJob($run->id), 'handle']);
        $run->refresh();

        $this->assertSame(ImportRunState::Succeeded, $run->state);
        $this->assertSame(1, $run->rows_created);
        $this->assertSame(1, $run->rows_skipped);

        $entry = TimeEntry::query()->where('project_id', $asset->customer->defaultProject()->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(45, $entry->minutes);
        // Ortszeit Berlin (Winterzeit) → UTC.
        $this->assertSame('2026-11-02 09:00:00', $entry->getRawOriginal('started_at'));
        $this->assertStringContainsString('Drucker eingerichtet', (string) $entry->description);
        $this->assertStringContainsString('TECH-NB', (string) $entry->description);
        $this->assertEqualsCanonicalizing(['Remote', 'RustDesk'], $entry->tags()->pluck('name')->all());

        $this->assertDatabaseHas('remote_pending_sessions', [
            'organization_id' => $this->organization->id,
            'provider' => 'rustdesk',
            'remote_id' => '987654321',
            'status' => RemotePendingSessionStatus::Open->value,
        ]);
    }

    public function test_rustdesk_pro_export_with_german_headers_is_detected(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'category_code' => 'workstation',
            'customer_id' => $customer->id,
        ]);
        (new RemoteDeviceRegistry)->setRemoteId($asset, RustDeskProClient::ID, '123456789');

        $run = $this->preflight($this->orgAdmin(), $this->rustdeskCsvDe());
        app()->call([new ProcessCsvImportJob($run->id), 'handle']);
        $run->refresh();

        $this->assertSame(ImportRunState::Succeeded, $run->state);
        $this->assertSame(1, $run->rows_created);
        $entry = TimeEntry::query()->firstOrFail();
        $this->assertSame(45, $entry->minutes);
        $this->assertSame('2026-11-02 09:00:00', $entry->getRawOriginal('started_at'));
        $this->assertEqualsCanonicalizing(['Remote', 'RustDesk'], $entry->tags()->pluck('name')->all());
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

    /** k1-05: ohne Überlaufprüfung wurde aus dem 31.02. still der 3. März. */
    public function test_impossible_date_is_a_format_error(): void {
        $spec = app(\App\Plugins\RemoteSupport\Import\RemoteSessionSpec::class);

        $row = $spec->normalize(['remote_id' => '362798056', 'start' => '31.02.2026, 09:42:09', 'end' => '28.05.2026']);
        $this->assertNull($row['started_at']);
        $this->assertNull($row['ended_at']);
        $this->assertCount(2, $spec->validateRow($row, $this->organization));

        $row = $spec->normalize(['remote_id' => '362798056', 'start' => '28.05.2026, 09:42', 'end' => '2026-05-28 10:44:08']);
        $this->assertSame('2026-05-28 09:42:00', $row['started_at']?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-28 10:44:08', $row['ended_at']?->format('Y-m-d H:i:s'));
    }
}

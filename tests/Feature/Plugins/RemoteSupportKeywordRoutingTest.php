<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportKeywordRoutingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Asset\AssetClass;
use App\Models\{Asset, Customer, Project, TimeEntry, User};
use App\Plugins\RemoteSupport\Providers\{RemoteSession, TeamViewerClient};
use App\Plugins\RemoteSupport\{RemoteDeviceRegistry, RemoteSessionImporter};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Schlüsselwort-Zuordnung der Fernwartung (MVP-483): die Sitzungsnotiz
 * entscheidet über das Projekt, bevor das Standardprojekt greift.
 */
class RemoteSupportKeywordRoutingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Asset $asset;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $this->customer->id,
        ]);
        (new RemoteDeviceRegistry)->setRemoteId($this->asset, TeamViewerClient::ID, '424242424');
    }

    private function project(string $name): Project {
        return Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => $name,
            'is_default' => false,
        ]);
    }

    private function bookWithNote(?string $note, string $sessionId): TimeEntry {
        $session = new RemoteSession(
            provider: TeamViewerClient::ID,
            sessionId: $sessionId,
            remoteId: '424242424',
            startedAt: CarbonImmutable::parse('2026-07-20 10:00:00'),
            endedAt: CarbonImmutable::parse('2026-07-20 11:00:00'),
            note: $note,
        );

        $result = (new RemoteSessionImporter)->importSessions(
            $this->organization,
            ['default_user_id' => null, 'default_billable' => true],
            [$session],
        );
        $this->assertSame(1, $result['created']);

        return TimeEntry::query()->latest('id')->firstOrFail();
    }

    public function test_notiz_mit_schluesselwort_bucht_ins_projekt(): void {
        $datev = $this->project('DATEV');

        $entry = $this->bookWithNote('DATEV Hotfixinstallation', 's1');

        $this->assertSame($datev->id, $entry->project_id);
    }

    public function test_ohne_treffer_bleibt_das_standardprojekt(): void {
        $this->project('DATEV');

        $entry = $this->bookWithNote('Drucker eingerichtet', 's2');

        $this->assertSame($this->customer->defaultProjectOrCreate()->id, $entry->project_id);
    }

    public function test_mehrdeutiger_treffer_bleibt_beim_standardprojekt(): void {
        $this->project('Umbau');
        $this->project('Anbau');

        $entry = $this->bookWithNote('Umbau und Anbau besprochen', 's3');

        $this->assertSame($this->customer->defaultProjectOrCreate()->id, $entry->project_id);
    }

    public function test_abgeschalteter_schalter_bucht_ins_standardprojekt(): void {
        $this->project('DATEV');
        $this->organization->update(['settings' => ['project' => ['keyword_matching' => ['enabled' => false]]]]);

        $entry = $this->bookWithNote('DATEV Hotfixinstallation', 's4');

        $this->assertSame($this->customer->defaultProjectOrCreate()->id, $entry->project_id);
    }
}

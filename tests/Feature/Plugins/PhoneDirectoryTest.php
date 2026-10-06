<?php
/*
 * Created on   : Mon Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhoneDirectoryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Plugins\Fritzbox\Services\{FritzboxGroupBooker, FritzboxImportService, FritzboxSuggestionService};
use App\Plugins\Fritzbox\Sources\FritzboxCall;
use App\Services\Contacts\ExternalPhoneContactDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Telefonauskunft-Plugin: Rückwärts-Auflösung über den Rufnummern-Aggregator und
 * die drei Konsumenten (FRITZ!Box-Inbox, Stammdaten; CTI in CtiCallerPopupTest).
 */
final class PhoneDirectoryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const LOOKUP_URL = 'https://directory.example/lookup';

    private User $owner;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->owner->id])->save();
        Cache::flush();
    }

    private function enableDirectory(): void {
        $this->pluginSecret('phonedirectory', ['endpoints' => self::LOOKUP_URL]);
    }

    private function fakeHit(string $name = 'Muster GmbH'): FakePluginHttp {
        return FakePluginHttp::fake([
            self::LOOKUP_URL . '*' => FakePluginHttp::response(['name' => $name]),
        ]);
    }

    private function directory(): ExternalPhoneContactDirectory {
        return app(ExternalPhoneContactDirectory::class);
    }

    // ── Aggregator / Resolver ────────────────────────────────────────────────

    public function test_aggregator_resolves_name_via_directory(): void {
        $this->enableDirectory();
        $this->fakeHit('Muster GmbH');

        $match = $this->directory()->find($this->organization, '+492219567000');

        $this->assertNotNull($match);
        $this->assertSame('Muster GmbH', $match->displayName);
        $this->assertNull($match->target); // reiner Namens-Hinweis, kein verknüpftes Ziel
        $this->assertContains(__('Telefonauskunft'), $match->sourceLabels);
    }

    public function test_aggregator_miss_returns_null(): void {
        $this->enableDirectory();
        $this->fakeHit(''); // leerer Name = kein Treffer

        $this->assertNull($this->directory()->find($this->organization, '+492219567000'));
    }

    public function test_disabled_plugin_sends_no_request(): void {
        // Kein enableDirectory(): Opt-in aus — die Nummer darf den Dienst nicht erreichen.
        $fake = $this->fakeHit('Muster GmbH');

        $this->assertNull($this->directory()->find($this->organization, '+492219567000'));
        $fake->assertNotSent(fn(RequestInterface $r): bool => str_contains((string) $r->getUri(), 'directory.example'));
    }

    // ── FRITZ!Box-Inbox (über den Aggregator) ────────────────────────────────

    public function test_fritzbox_inbox_group_is_enriched_with_directory_name(): void {
        $this->enableDirectory();
        $this->fakeHit('Muster GmbH');
        $this->stageFritzboxCall();

        $group = $this->booker()->groups($this->organization)->firstOrFail();

        $this->assertSame('Muster GmbH', $group['name']);
        $this->assertContains(__('Telefonauskunft'), $group['contact_sources']);
    }

    public function test_fritzbox_inbox_suggests_customer_matching_the_directory_name(): void {
        $this->enableDirectory();
        $this->fakeHit('Muster GmbH');
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster GmbH',
        ]);
        $this->stageFritzboxCall();

        $group = $this->booker()->groups($this->organization)->firstOrFail();

        $this->assertSame((string) $customer->sqid, $group['suggested_customer_sqid']);
    }

    // ── Stammdaten-Anreicherung ──────────────────────────────────────────────

    public function test_customer_master_data_fill_sets_empty_company(): void {
        $this->enableDirectory();
        $this->fakeHit('Muster GmbH');
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kundenkontakt',
            'company' => null,
            'phone' => '+492219567000',
        ]);

        $this->actingAs($admin)
            ->post(route('customers.phonedirectory.fill', $customer))
            ->assertRedirect();

        $customer->refresh();
        $this->assertSame('Muster GmbH', $customer->company);
    }

    public function test_customer_master_data_fill_reports_when_no_hit(): void {
        $this->enableDirectory();
        $this->fakeHit(''); // kein Treffer
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kundenkontakt',
            'company' => null,
            'phone' => '+492219567000',
        ]);

        $this->actingAs($admin)
            ->post(route('customers.phonedirectory.fill', $customer))
            ->assertSessionHas('error');

        $customer->refresh();
        $this->assertNull($customer->company);
    }

    // ── Helfer ───────────────────────────────────────────────────────────────

    private function booker(): FritzboxGroupBooker {
        return new FritzboxGroupBooker(new FritzboxImportService, new FritzboxSuggestionService);
    }

    private function stageFritzboxCall(): void {
        $startedAt = CarbonImmutable::parse('2026-07-20 09:00:00', 'UTC');
        $call = new FritzboxCall(
            type: FritzboxCall::TYPE_INCOMING,
            direction: FritzboxCall::DIR_IN,
            startedAt: $startedAt,
            endedAt: $startedAt->addMinutes(10),
            durationMinutes: 10,
            numberRaw: '02219567000',
            e164: '+492219567000',
            name: null,
            ownLine: '97911585',
        );

        $config = [
            'default_billable' => true,
            'default_user_id' => null,
            'min_call_minutes' => 2,
            'call_lead_minutes' => 15,
            'own_number_allowlist' => [],
            'type3_outgoing' => false,
        ];

        $status = (new FritzboxImportService)->bookCall($this->organization, $config, $call, $this->owner->id);
        $this->assertSame('pending', $status);
    }
}

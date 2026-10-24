<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflySyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\Firefly;

use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Models\Platform\User;
use App\Plugins\Firefly\Enums\{FireflyMatchStatus, FireflyTransactionKind};
use App\Plugins\Firefly\FireflyPlugin;
use App\Plugins\Firefly\Models\{FireflyConnection, FireflyTransaction};
use App\Plugins\Firefly\Services\FireflySyncService;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Firefly III (MVP-1333): Bewegungen des Zeitfensters spiegeln, Umbuchungen
 * auslassen, Zuordnung nur über eine eindeutige Belegnummer, Abgänge nur nach
 * vollständigem Lesen entfernen, nichts schreiben.
 */
class FireflySyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://firefly.example.test';

    /** @var list<array<string, mixed>> */
    private array $groups = [];

    private ?int $totalOverride = null;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->freezeSecond();
        $this->setUpOrganization();
        $this->pluginSecret(FireflyPlugin::ID, ['base_url' => self::BASE, 'access_token' => 'ff-token', 'window_days' => 30]);
        $this->admin = $this->orgAdmin();
        app()->instance('currentOrganization', $this->organization);
        config(['plugins.firefly.page_size' => 2]);

        Invoice::factory()->create(['organization_id' => $this->organization->id, 'number' => 'RE-2026-0042']);
        IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => Document::factory()->create(['organization_id' => $this->organization->id])->id,
            'sha256' => hash('sha256', 'firefly'), 'source' => 'upload', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'invoice_number' => 'LF-881', 'seller_name' => 'Lieferant GmbH', 'currency' => 'EUR', 'amount_gross' => '59.50',
        ]);
        $this->groups = [
            $this->group(1, 101, 'deposit', '119.00', 'Zahlung RE-2026-0042 danke', 'Kundin AG', 'Geschäftskonto'),
            $this->group(2, 102, 'withdrawal', '59.50', 'Rechnung LF-881', 'Geschäftskonto', 'Lieferant GmbH'),
            $this->group(3, 103, 'transfer', '500.00', 'Umbuchung', 'Geschäftskonto', 'Tagesgeld'),
        ];
    }

    /** @return array<string, mixed> */
    private function group(int $groupId, int $journalId, string $type, string $amount, string $description, string $source, string $destination): array {
        return ['type' => 'transactions', 'id' => (string) $groupId, 'attributes' => ['transactions' => [[
            'transaction_journal_id' => (string) $journalId,
            'type' => $type,
            'date' => CarbonImmutable::today()->subDays(2)->toIso8601String(),
            'amount' => $amount,
            'currency_code' => 'EUR',
            'description' => $description,
            'source_name' => $source,
            'destination_name' => $destination,
            'notes' => null,
            'internal_reference' => null,
            'external_id' => null,
        ]]]];
    }

    private function connect(): FireflyConnection {
        return FireflyConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => SelfHostedInstance::key(self::BASE),
            'source_name' => 'firefly.example.test',
            'status' => OAuthConnectionStatus::Active,
        ]);
    }

    private function serve(): FakePluginHttp {
        return FakePluginHttp::fake([
            self::BASE . '/api/v1/about/user' => FakePluginHttp::response(['data' => ['type' => 'users', 'id' => '1', 'attributes' => ['email' => 'chef@example.test']]]),
            self::BASE . '/api/v1/about' => FakePluginHttp::response(['data' => ['version' => '6.1.21']]),
            // Wie Firefly: geblättert und gezählt wird je Buchung (Split), gruppiert erst danach.
            self::BASE . '/api/v1/transactions*' => function (RequestInterface $request) {
                parse_str($request->getUri()->getQuery(), $q);
                $page = (int) ($q['page'] ?? 1);
                $limit = (int) ($q['limit'] ?? 50);
                $splits = [];
                foreach ($this->groups as $group) {
                    foreach ($group['attributes']['transactions'] as $split) {
                        $splits[] = [$group, $split];
                    }
                }
                $data = [];
                foreach (array_slice($splits, ($page - 1) * $limit, $limit) as [$group, $split]) {
                    $last = array_key_last($data);
                    if ($last !== null && $data[$last]['id'] === $group['id']) {
                        $data[$last]['attributes']['transactions'][] = $split;
                    } else {
                        $group['attributes']['transactions'] = [$split];
                        $data[] = $group;
                    }
                }

                return FakePluginHttp::response([
                    'data' => $data,
                    'meta' => ['pagination' => ['total' => $this->totalOverride ?? count($splits), 'total_pages' => (int) max(1, ceil(count($splits) / $limit)), 'current_page' => $page]],
                ]);
            },
        ]);
    }

    public function test_verify_records_instance_version_and_account(): void {
        $this->serve();
        $this->actingAs($this->admin)->post(route('admin.firefly.verify'))->assertSessionHas('success');

        $connection = FireflyConnection::query()->sole();
        $this->assertSame('firefly.example.test (Firefly III 6.1.21)', $connection->source_name);
        $this->assertSame('chef@example.test', $connection->account_name);
        $this->assertTrue($connection->isReady());
    }

    public function test_sync_mirrors_and_matches_by_document_number_only(): void {
        $sepa = $this->group(8, 108, 'deposit', '10.00', 'Überweisung', 'Kundin AG', 'Geschäftskonto');
        $sepa['attributes']['transactions'][0]['sepa_ct_id'] = 'RE-2026-0042';
        $this->groups[] = $sepa;
        $this->groups[0]['attributes']['transactions'][0]['foreign_amount'] = '130.20';
        $this->groups[0]['attributes']['transactions'][0]['foreign_currency_code'] = 'USD';
        $this->connect();
        $http = $this->serve();

        $counters = app(FireflySyncService::class)->run($this->organization);

        $this->assertSame(3, $counters['created'], 'Umbuchungen bleiben außen vor.');
        $this->assertSame(3, $counters['matched']);
        $deposit = FireflyTransaction::query()->where('remote_journal_id', '101')->sole();
        $this->assertSame(FireflyTransactionKind::Deposit, $deposit->kind);
        $this->assertSame(FireflyMatchStatus::Matched, $deposit->match_status);
        $this->assertSame(Invoice::query()->where('number', 'RE-2026-0042')->value('id'), $deposit->invoice_id);
        $this->assertSame('Kundin AG', $deposit->counterparty_name);
        $this->assertSame('119.00', $deposit->booked_amount->getAmount());
        $this->assertSame(['130.20', 'USD'], [$deposit->foreign_amount?->getAmount(), $deposit->foreign_currency?->value]);
        $this->assertNull(FireflyTransaction::query()->where('remote_journal_id', '102')->sole()->foreign_amount);
        $this->assertSame(FireflyMatchStatus::Matched, FireflyTransaction::query()->where('remote_journal_id', '108')->sole()->match_status, 'Belegnummer aus der SEPA-End-to-End-ID.');
        $withdrawal = FireflyTransaction::query()->where('remote_journal_id', '102')->sole();
        $this->assertSame(IncomingEInvoice::query()->value('id'), $withdrawal->incoming_einvoice_id);
        $this->assertSame('Lieferant GmbH', $withdrawal->counterparty_name);
        $http->assertSent(static function (RequestInterface $r): bool {
            parse_str($r->getUri()->getQuery(), $q);

            return str_ends_with($r->getUri()->getPath(), '/transactions') && ($q['start'] ?? null) === CarbonImmutable::today()->subDays(30)->toDateString() && ($q['page'] ?? null) === '2';
        });
        $http->assertNotSent(static fn (RequestInterface $r): bool => $r->getMethod() !== 'GET');
    }

    public function test_ambiguous_numbers_stay_open_and_vanished_movements_go_only_after_complete_reads(): void {
        Invoice::factory()->create(['organization_id' => $this->organization->id, 'number' => 'RE-2026-0043']);
        $this->groups[0]['attributes']['transactions'][0]['description'] = 'RE-2026-0042 und RE-2026-0043';
        $this->connect();
        $this->serve();
        app(FireflySyncService::class)->run($this->organization);
        $this->assertSame(FireflyMatchStatus::Ambiguous, FireflyTransaction::query()->where('remote_journal_id', '101')->sole()->match_status);

        array_shift($this->groups);
        $this->totalOverride = 9;
        $this->serve();
        app(FireflySyncService::class)->run($this->organization);
        $this->assertSame(2, FireflyTransaction::query()->count(), 'Unvollständig gelesen: nichts wird entfernt.');
        $this->assertFalse(FireflyConnection::query()->sole()->is_last_run_complete);

        $this->totalOverride = null;
        $this->serve();
        $this->assertSame(1, app(FireflySyncService::class)->run($this->organization)['removed']);
        $this->assertNull(FireflyTransaction::query()->where('remote_journal_id', '101')->first());
    }

    public function test_a_split_booking_across_pages_is_a_complete_read_and_only_the_window_is_cleaned(): void {
        $splitGroup = function (int $groupId, int $firstJournal): array {
            $group = $this->group($groupId, $firstJournal, 'deposit', '10.00', 'Sammelzahlung Teil 1', 'Kundin AG', 'Geschäftskonto');
            $group['attributes']['transactions'][] = $this->group($groupId, $firstJournal + 1, 'deposit', '20.00', 'Sammelzahlung Teil 2', 'Kundin AG', 'Geschäftskonto')['attributes']['transactions'][0];

            return $group;
        };
        // Seiten zu je zwei Buchungen: 106/107 | 101/102 | 103/104 | 105 — Gruppe 6 auf einer Seite, Gruppe 4 geteilt.
        $this->groups = [$splitGroup(6, 106), ...$this->groups, $splitGroup(4, 104)];
        $connection = $this->connect();
        $outside = FireflyTransaction::query()->forceCreate(['organization_id' => $this->organization->id, 'remote_journal_id' => '990', 'kind' => FireflyTransactionKind::Deposit, 'booked_on' => CarbonImmutable::today()->addDay()->toDateString(), 'booked_amount' => '5.00', 'currency' => 'EUR', 'match_status' => FireflyMatchStatus::Unmatched]);
        $gone = FireflyTransaction::query()->forceCreate(['organization_id' => $this->organization->id, 'remote_journal_id' => '991', 'kind' => FireflyTransactionKind::Deposit, 'booked_on' => CarbonImmutable::today()->subDay()->toDateString(), 'booked_amount' => '5.00', 'currency' => 'EUR', 'match_status' => FireflyMatchStatus::Unmatched]);
        $this->serve();

        $counters = app(FireflySyncService::class)->run($this->organization);

        $this->assertTrue($connection->refresh()->is_last_run_complete, 'total zählt Buchungen, nicht Gruppen.');
        $this->assertSame(['104', '105'], FireflyTransaction::query()->where('remote_group_id', '4')->orderBy('remote_journal_id')->pluck('remote_journal_id')->all(), 'Die Gruppe stand geteilt auf zwei Seiten.');
        $this->assertSame(1, $counters['removed']);
        $this->assertNull($gone->fresh(), 'Im Fenster nicht mehr gelesen: entfällt.');
        $this->assertNotNull($outside->fresh(), 'Außerhalb des gelesenen Fensters bleibt der Spiegel unberührt.');
    }

    public function test_page_command_and_search(): void {
        $this->groups[0]['attributes']['transactions'][0]['foreign_amount'] = '130.20';
        $this->groups[0]['attributes']['transactions'][0]['foreign_currency_code'] = 'USD';
        $this->connect();
        $this->serve();

        $this->artisan('firefly:sync')->assertSuccessful();

        $this->actingAs($this->admin)->get(route('admin.firefly.index', ['match' => 'matched']))->assertOk()
            ->assertSee('RE-2026-0042')
            ->assertSee('LF-881')
            ->assertSee(\CommonToolkit\ValueObjects\Money::of('130.20', \CommonToolkit\Enums\CurrencyCode::from('USD'))->format(), false);
        $domain = app(\App\Plugins\Firefly\Search\FireflyTransactionSearchDomain::class);
        $this->assertCount(1, $domain->search($this->admin, (int) $this->organization->id, 'Kundin', new \App\Services\Search\Dto\SearchDomainFilters(), 10));
    }
}

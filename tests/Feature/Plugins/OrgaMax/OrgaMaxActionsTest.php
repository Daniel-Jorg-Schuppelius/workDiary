<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxActionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\OrgaMax;

use App\Models\{ExternalReference, IntegrationOutboxEntry, OrgaMaxConnection, User};
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\OrgaMax\Services\OrgaMaxOutboxDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-310/312/313/315: getrennte Faktura-Aktionen mit eigenen
 * Berechtigungen, idempotenter Outbox-Zustellung, irreversiblem Sperren nur
 * als direkte Nutzeraktion und blockierter Expense-Übergabe.
 */
class OrgaMaxActionsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private OrgaMaxConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        $this->connection = OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'api_key' => 'k',
            'api_secret' => 's',
            'ownership_id' => 'own-1',
            'bearer_token' => 'token',
            'token_expires_at' => Carbon::now()->addHour(),
            'status' => OrgaMaxConnection::STATUS_ACTIVE,
            'capabilities' => [
                'billing' => ['enabled' => true, 'leader' => 'orgamax'],
                'payments' => ['enabled' => true, 'leader' => 'workdiary'],
            ],
        ]);
    }

    private function makeEntry(string $operation, array $payload, string $key): IntegrationOutboxEntry {
        return IntegrationOutboxEntry::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => OrgaMaxPlugin::ID,
            'operation' => $operation,
            'payload' => $payload,
            'idempotency_key' => $key,
            'status' => \App\Enums\Integration\IntegrationOutboxStatus::Pending->value,
            'attempts' => 0,
        ]);
    }

    public function test_convert_dispatch_is_idempotent(): void {
        $fake = FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/order/om-1/invoice*' => FakePluginHttp::response(['id' => 'inv-9'], 201),
        ]);

        $dispatcher = app(OrgaMaxOutboxDispatcher::class);
        $entry = $this->makeEntry('invoice.convert', ['order_id' => 'om-1'], 'orgamax:convert:1:om-1');

        $this->assertTrue($dispatcher->dispatch($entry));
        $this->assertSame(1, ExternalReference::query()->where('external_type', 'orgamax_converted_invoice')->count());

        // Zweite Zustellung (Retry/unklarer Ausgang): kein zweiter API-Call.
        $this->assertTrue($dispatcher->dispatch($entry));
        $fake->assertSentCount(1);
    }

    public function test_lock_requires_permission_and_calls_api_directly(): void {
        $fake = FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/invoice/inv-1/lock*' => FakePluginHttp::response(['id' => 'inv-1']),
        ]);

        $this->post(route('admin.orgamax.invoices.lock', 'inv-1'))
            ->assertRedirect()
            ->assertSessionHas('success');
        $fake->assertSent(fn(RequestInterface $r) => $r->getMethod() === 'PUT' && str_contains((string) $r->getUri(), '/invoice/inv-1/lock'));

        // Nutzer ohne Berechtigung: 403, kein API-Call.
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $before = FakePluginHttp::fake([]);
        $this->actingAs($user)->post(route('admin.orgamax.invoices.lock', 'inv-1'))->assertForbidden();
        $before->assertNothingSent();
    }

    public function test_send_enqueues_outbox_and_dispatcher_delivers(): void {
        // Fake VOR dem POST: der Sync-Queue-Worker stellt den Outbox-Eintrag
        // im selben Request zu.
        $fake = FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/invoice/inv-1/send*' => FakePluginHttp::response(['ok' => true]),
        ]);

        $this->post(route('admin.orgamax.invoices.send', 'inv-1'), [
            'recipient' => 'kunde@acme.test',
        ])->assertRedirect()->assertSessionHas('success');

        $entry = IntegrationOutboxEntry::query()
            ->where('plugin_id', OrgaMaxPlugin::ID)
            ->where('operation', 'invoice.send')
            ->firstOrFail();
        $this->assertSame('inv-1', $entry->payload['invoice_id'] ?? null);
        $fake->assertSent(fn(RequestInterface $r) => str_contains((string) $r->getUri(), '/invoice/inv-1/send'));
    }

    public function test_payment_push_checks_leader_and_duplicates(): void {
        $fake = FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/invoice/inv-1/payment*' => FakePluginHttp::response(['ok' => true], 201),
        ]);
        $dispatcher = app(OrgaMaxOutboxDispatcher::class);

        $entry = $this->makeEntry('payment.push', ['invoice_id' => 'inv-1', 'amount' => '119.00', 'date' => '2026-07-01'], 'p1');
        $this->assertTrue($dispatcher->dispatch($entry));

        // Dublette (gleicher Betrag + Datum): bestätigt ohne zweiten Call.
        $entry2 = $this->makeEntry('payment.push', ['invoice_id' => 'inv-1', 'amount' => '119.00', 'date' => '2026-07-01'], 'p2');
        $this->assertTrue($dispatcher->dispatch($entry2));
        $fake->assertSentCount(1);

        // orgaMAX-geführte Zahlungen: push unzulässig.
        $this->connection->forceFill([
            'capabilities' => ['payments' => ['enabled' => true, 'leader' => 'orgamax']],
        ])->save();
        $entry3 = $this->makeEntry('payment.push', ['invoice_id' => 'inv-1', 'amount' => '5.00', 'date' => '2026-07-02'], 'p3');
        $this->expectException(RuntimeException::class);
        $dispatcher->dispatch($entry3);
    }

    public function test_expense_push_is_blocked_until_receipt_pilot_confirmed(): void {
        FakePluginHttp::fake([]);
        $entry = $this->makeEntry('expense.push', ['expense_id' => 5], 'e1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/blockiert|MVP-312/');
        app(OrgaMaxOutboxDispatcher::class)->dispatch($entry);
    }
}

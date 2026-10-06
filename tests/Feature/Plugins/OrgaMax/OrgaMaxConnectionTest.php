<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxConnectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\OrgaMax;

use App\Enums\Finance\TransferTarget;
use App\Models\Customer\Customer;
use App\Models\Finance\BillingTransfer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\{OrgaMaxConnection, OrgaMaxInvoice};
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\OrgaMax\Services\{OrgaMaxInvoiceProjector, OrgaMaxTarget};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-306/315: Verbindungsabsicht, iid-Callback (Anti-Fremd-iid),
 * Kontobestätigung, Scope-Preflight und Secret-Redaktion.
 */
class OrgaMaxConnectionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    /** JWT mit exp-Claim (nur Struktur — Signatur wird nicht geprüft). */
    private function jwt(int $expiresInSeconds = 3600): string {
        $payload = rtrim(strtr(base64_encode((string) json_encode(['exp' => time() + $expiresInSeconds])), '+/', '-_'), '=');

        return 'eyJhbGciOiJIUzI1NiJ9.' . $payload . '.sig';
    }

    public function test_full_connection_flow_intent_callback_confirm(): void {
        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/auth/token*' => FakePluginHttp::response(['token' => $this->jwt()]),
            'https://api.orgamax.de/openapi/setting/account*' => FakePluginHttp::response([
                'name' => 'Muster GmbH',
                'scopes' => ['customer:read', 'order:read', 'order:write', 'invoice:read'],
            ]),
        ]);

        // 1. Verbindungsabsicht (privater Pilotmodus).
        $this->post(route('admin.orgamax.connect'), [
            'mode' => 'private',
            'api_key' => 'key-1',
            'api_secret' => 'secret-1',
        ])->assertRedirect()->assertSessionHas('orgamax_callback_url');

        $callbackUrl = (string) session('orgamax_callback_url');
        $connection = OrgaMaxConnection::query()->firstOrFail();
        $this->assertSame(OrgaMaxConnectionStatus::PendingCallback, $connection->status);

        // 2. iid-Callback mit gültigem State-Token.
        $this->get($callbackUrl . '&iid=ownership-42')->assertRedirect(route('admin.orgamax.index'));
        $connection->refresh();
        $this->assertSame(OrgaMaxConnectionStatus::PendingConfirmation, $connection->status);
        $this->assertSame('Muster GmbH', $connection->account_snapshot['name'] ?? null);
        $this->assertNotNull($connection->bearer_token);

        // 3. Ausdrückliche Kontobestätigung → aktiv (keine Capability aktiviert → keine Scope-Lücke).
        $this->post(route('admin.orgamax.confirm'))->assertRedirect();
        $this->assertSame(OrgaMaxConnectionStatus::Active, $connection->fresh()->status);
    }

    public function test_callback_with_wrong_state_binds_nothing(): void {
        $this->post(route('admin.orgamax.connect'), [
            'mode' => 'private',
            'api_key' => 'key-1',
            'api_secret' => 'secret-1',
        ]);

        $fake = FakePluginHttp::fake([]);

        $this->get(route('admin.orgamax.callback', ['state' => 'falsches-token', 'iid' => 'fremd-99']))
            ->assertRedirect(route('admin.orgamax.index'))
            ->assertSessionHas('error');

        $connection = OrgaMaxConnection::query()->firstOrFail();
        $this->assertSame(OrgaMaxConnectionStatus::PendingCallback, $connection->status);
        $this->assertNull($connection->bearer_token);
        $fake->assertNothingSent();
    }

    public function test_callback_without_intent_is_rejected(): void {
        OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'status' => OrgaMaxConnectionStatus::Active,
        ]);

        $this->get(route('admin.orgamax.callback', ['state' => 'egal', 'iid' => 'fremd-1']))
            ->assertRedirect(route('admin.orgamax.index'))
            ->assertSessionHas('error');
    }

    /** Die Rechnungs-Projektion blättert — früher endete sie still nach den 50 jüngsten Rechnungen. */
    public function test_invoice_projection_pages(): void {
        OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'status' => OrgaMaxConnectionStatus::Active,
        ]);
        foreach (range(1, 51) as $i) {
            ExternalReference::link(
                $this->organization,
                OrgaMaxPlugin::ID,
                OrgaMaxInvoiceProjector::EXT_TYPE_INVOICE,
                OrgaMaxInvoice::query()->create(['organization_id' => $this->organization->id, 'external_id' => 'inv-' . $i]),
                'inv-' . $i,
                ['number' => sprintf('RE-%03d', $i), 'status' => 'open'],
                Carbon::parse('2026-09-01 08:00:00')->addMinutes($i),
            );
        }

        $first = $this->get(route('admin.orgamax.index'))->assertOk();
        $this->assertSame(51, $first->viewData('invoices')->total());
        $this->assertCount(25, $first->viewData('invoices')->items());
        $first->assertSee('RE-051')->assertDontSee('RE-001');

        $third = $this->get(route('admin.orgamax.index', ['page' => 3]))->assertOk();
        $this->assertSame(['inv-1'], $third->viewData('invoices')->pluck('external_id')->all());
        $third->assertSee('RE-001')->assertSee(route('admin.orgamax.invoices.pdf', 'inv-1'));
    }

    /** „Übergebene Aufträge“ blättert in der Karte — früher endete die Liste still nach 50. */
    public function test_handed_over_orders_page_independently_of_the_invoice_page(): void {
        OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'status' => OrgaMaxConnectionStatus::Active,
        ]);
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);
        // Je Referenz ein eigener Träger (Unique-Index extref_unique je Zielmodell).
        foreach (range(1, 26) as $i) {
            $transfer = BillingTransfer::factory()->create([
                'organization_id' => $this->organization->id,
                'customer_id' => $customer->id,
                'target' => TransferTarget::OrgaMax,
            ]);
            ExternalReference::link(
                $this->organization,
                OrgaMaxPlugin::ID,
                OrgaMaxTarget::EXT_TYPE_ORDER,
                $transfer,
                'order-' . $i,
                ['marker' => sprintf('WD-ORDER-%03d', $i)],
                Carbon::parse('2026-09-01 08:00:00')->addMinutes($i),
            );
        }
        ExternalReference::link(
            $this->organization,
            OrgaMaxPlugin::ID,
            OrgaMaxInvoiceProjector::EXT_TYPE_INVOICE,
            OrgaMaxInvoice::query()->create(['organization_id' => $this->organization->id, 'external_id' => 'inv-1']),
            'inv-1',
            ['number' => 'RE-001', 'status' => 'locked'],
        );

        $first = $this->get(route('admin.orgamax.index'))->assertOk();
        $this->assertSame(26, $first->viewData('orders')->total());
        $this->assertCount(25, $first->viewData('orders')->items());
        $first->assertSee('WD-ORDER-026')->assertDontSee('WD-ORDER-001');

        $second = $this->get(route('admin.orgamax.index', ['orders_page' => 2]))->assertOk();
        $this->assertSame(['order-1'], $second->viewData('orders')->pluck('external_id')->all());
        $second->assertSee('WD-ORDER-001')->assertDontSee('WD-ORDER-026');
        $this->assertStringContainsString('orders_page=1', (string) $second->viewData('orders')->previousPageUrl());

        // Die Rechnungs-Projektion bleibt auf Seite 1 und zeigt ihren Status übersetzt.
        $this->assertSame(1, $second->viewData('invoices')->currentPage());
        $second->assertSee('RE-001');
        $this->assertMatchesRegularExpression('/badge badge-sm badge-info">\s*Finalisiert\s*</', (string) $second->getContent(), 'Status aus dem Enum: Ton und Label statt roher SDK-Wert.');
    }

    /** Die Verbindungskarte verkettete den Status früher mit dem Textschlüssel. */
    public function test_index_shows_status_label_and_tone(): void {
        $connection = OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'status' => OrgaMaxConnectionStatus::PendingCallback,
        ]);

        $expected = [
            [OrgaMaxConnectionStatus::PendingCallback, 'badge-warning', 'Wartet auf Callback'],
            [OrgaMaxConnectionStatus::PendingConfirmation, 'badge-warning', 'Wartet auf Kontobestätigung'],
            [OrgaMaxConnectionStatus::Blocked, 'badge-error', 'Blockiert'],
            [OrgaMaxConnectionStatus::Active, 'badge-success', 'Aktiv'],
        ];
        foreach ($expected as [$status, $tone, $label]) {
            $connection->forceFill(['status' => $status])->save();
            $html = (string) $this->get(route('admin.orgamax.index'))->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/badge badge-sm ' . $tone . '">\s*' . preg_quote($label, '/') . '\s*</', $html, $status->value);
            $this->assertSame(
                $status === OrgaMaxConnectionStatus::PendingConfirmation,
                str_contains($html, route('admin.orgamax.confirm')),
                $status->value,
            );
        }

        // Getrennt zeigt den Verbindungsdialog statt des Stands und keine Capability-Matrix.
        $connection->forceFill(['status' => OrgaMaxConnectionStatus::Disconnected])->save();
        $this->get(route('admin.orgamax.index'))
            ->assertOk()
            ->assertSee(route('admin.orgamax.connect'), false)
            ->assertDontSee(route('admin.orgamax.disconnect'), false)
            ->assertDontSee(route('admin.orgamax.capabilities'), false);
    }

    public function test_missing_scopes_block_activation(): void {
        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/auth/token*' => FakePluginHttp::response(['token' => $this->jwt()]),
            'https://api.orgamax.de/openapi/setting/account*' => FakePluginHttp::response([
                'name' => 'Muster GmbH',
                'scopes' => ['customer:read'], // order:*-Scopes fehlen
            ]),
        ]);

        $this->post(route('admin.orgamax.connect'), ['mode' => 'private', 'api_key' => 'k', 'api_secret' => 's']);
        $connection = OrgaMaxConnection::query()->firstOrFail();
        $this->get((string) session('orgamax_callback_url') . '&iid=own-1');

        // Faktura-Capability aktivieren → Preflight verlangt order:*-Scopes.
        $connection->refresh();
        $caps = $connection->capabilities;
        $caps['billing'] = ['enabled' => true, 'leader' => 'orgamax'];
        $connection->forceFill(['capabilities' => $caps])->save();

        $this->post(route('admin.orgamax.confirm'))->assertRedirect();

        $connection->refresh();
        $this->assertSame(OrgaMaxConnectionStatus::Blocked, $connection->status);
        $this->assertStringContainsString('order:write', (string) $connection->blocked_reason);
    }

    /** Aktiv wird eine blockierte Verbindung nur über die Capabilities, nie über die Kontobestätigung. */
    public function test_confirm_needs_a_pending_confirmation(): void {
        $connection = OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'status' => OrgaMaxConnectionStatus::Blocked,
            'blocked_reason' => 'token_refresh_failed',
        ]);

        $this->post(route('admin.orgamax.confirm'))->assertRedirect()->assertSessionHas('error');

        $this->assertSame(OrgaMaxConnectionStatus::Blocked, $connection->refresh()->status);
        $this->assertNull($connection->confirmed_at);
    }

    public function test_secrets_never_appear_in_serialization_or_audit(): void {
        $connection = OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'api_key' => 'geheimer-key',
            'api_secret' => 'geheimes-secret',
            'ownership_id' => 'own-42',
            'bearer_token' => 'jwt-token',
            'status' => OrgaMaxConnectionStatus::Active,
        ]);

        $serialized = json_encode($connection->toArray());
        $this->assertStringNotContainsString('geheimer-key', (string) $serialized);
        $this->assertStringNotContainsString('geheimes-secret', (string) $serialized);
        $this->assertStringNotContainsString('own-42', (string) $serialized);
        $this->assertStringNotContainsString('jwt-token', (string) $serialized);

        // Audit-Payload der Anlage enthält ebenfalls keine Secrets.
        $log = \App\Models\Audit\AuditLog::query()
            ->where('auditable_type', MorphMap::stableKey($connection::class))
            ->where('auditable_id', $connection->id)
            ->latest('id')
            ->first();
        if ($log !== null) {
            $this->assertStringNotContainsString('geheimer-key', (string) json_encode($log->getAttribute('changes')));
        }
    }

    public function test_non_admin_cannot_manage_connection(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('admin.orgamax.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.orgamax.connect'), ['mode' => 'private'])->assertForbidden();
    }
}

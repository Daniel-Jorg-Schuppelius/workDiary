<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainConnectionLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\Domain\{DomainCapabilityArea, DomainConnectionStatus, DomainProviderCommandStatus};
use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Domain\{DomainAccountingEntry, DomainContactProjection, DomainProjection, DomainProviderCommand, DomainProviderConnection, DomainResellerAccount};
use App\Models\Platform\User;
use App\Models\Reselling\{ResalePurchaseEntry, ResaleSubscription};
use App\Services\Domain\{DomainActionException, DomainCommandService};
use App\Services\Reselling\Register\DomainSubscriptionSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeDomainResellingTransport;
use Tests\TestCase;

/**
 * Domain-Anbieter trennen ohne Datenverlust (MVP-1491, ul-02) und Zugangsdaten
 * erneuern (MVP-1492, ul-04). Vorher löschte „Trennen“ die Verbindung samt
 * Projektionen, Befehlsjournal und Buchungen; die Hilfe versprach den Erhalt.
 */
final class DomainConnectionLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
        $this->admin = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin->givePermissionTo([Permission::DomainProviderView->value, Permission::DomainProviderManage->value]);
    }

    private function connection(DomainConnectionStatus $status = DomainConnectionStatus::Active): DomainProviderConnection {
        return DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id, 'login' => 'reseller-nord', 'status' => $status]);
    }

    public function test_disconnect_keeps_projections_journal_bookings_and_reselling_links(): void {
        $connection = $this->connection();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $projection = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_domain' => 'gartenbau-beispiel.de',
            'domain_hash' => DomainProjection::hashFor('gartenbau-beispiel.de'), 'customer_id' => $customer->id,
            'registration_at' => '2024-03-15', 'expiration_at' => '2027-03-15', 'renewal_price' => '8.87',
        ]);
        DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id]);
        DomainContactProjection::query()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_handle' => 'P-OWNER1', 'snapshot' => ['type' => 'ORG']]);
        $command = app(DomainCommandService::class)->create($connection, DomainCapabilityArea::Dns, 'UpdateDNSZone', 'gartenbau-beispiel.de', subject: $projection);
        $booking = DomainAccountingEntry::query()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_user' => 'reseller', 'accounting_id' => 'acc-1', 'domain_projection_id' => $projection->id,
            'entry_date' => '2026-03-16', 'type' => 'RENEWAL', 'description' => 'gartenbau-beispiel.de', 'reference' => 'INV-1', 'quantity' => 1, 'net_amount' => 8.87, 'currency' => 'EUR', 'raw_hash' => 'h1',
        ]);
        app(DomainSubscriptionSync::class)->sync($this->organization);
        $this->artisan('resale:sync-purchases', ['--org' => $this->organization->id])->assertSuccessful();
        $subscription = ResaleSubscription::query()->where('domain_projection_id', $projection->id)->sole();
        $purchase = ResalePurchaseEntry::query()->where('domain_accounting_entry_id', $booking->id)->sole();

        $this->actingAs($this->admin)->delete(route('admin.domain-provider.destroy', $connection))
            ->assertRedirect(route('admin.domain-provider.index'))
            ->assertSessionHas('success', 'Verbindung getrennt. Domains, Befehlsjournal und Buchungen bleiben erhalten.');

        $connection->refresh();
        $this->assertSame(DomainConnectionStatus::Disconnected, $connection->status);
        $this->assertNull($connection->password, 'Zugangsdaten geleert');
        $this->assertSame(1, $connection->projections()->count());
        $this->assertSame(1, $connection->resellerAccounts()->count());
        $this->assertSame(1, DomainContactProjection::query()->where('connection_id', $connection->id)->count());
        $this->assertTrue(DomainProviderCommand::query()->whereKey($command->id)->exists(), 'Befehlsjournal bleibt');
        $this->assertSame('domain_projections', $command->fresh()?->subject_type, 'Subjekt als Morph-Alias');
        $this->assertTrue(DomainAccountingEntry::query()->whereKey($booking->id)->exists(), 'Buchungen bleiben');
        $this->assertSame($projection->id, $subscription->fresh()?->domain_projection_id, 'Reselling-Abo behält die Domain');
        $this->assertSame($booking->id, $purchase->fresh()?->domain_accounting_entry_id, 'Einkaufszeile behält die Buchung');

        // Erneut trennen ist ohne Wirkung.
        $this->actingAs($this->admin)->delete(route('admin.domain-provider.destroy', $connection))->assertRedirect();
        $this->assertSame(DomainConnectionStatus::Disconnected, $connection->fresh()?->status);
    }

    public function test_disconnected_connection_does_not_reach_the_provider(): void {
        $connection = $this->connection(DomainConnectionStatus::Disconnected);
        $connection->forceFill(['password' => null])->save();
        $command = app(DomainCommandService::class)->create($connection, DomainCapabilityArea::Dns, 'UpdateDNSZone', 'gartenbau-beispiel.de');
        $fake = FakeDomainResellingTransport::fake([]);

        $this->artisan('domain:sync')->assertSuccessful();
        $this->artisan('domain:events')->assertSuccessful();
        $this->actingAs($this->admin)->post(route('admin.domain-provider.sync', $connection))->assertSessionHas('error', 'Verbindung ist nicht betriebsbereit.');
        $this->actingAs($this->admin)->post(route('admin.domain-provider.test', $connection))
            ->assertSessionHas('error', 'Die Verbindung ist getrennt. Hinterlegen Sie zuerst neue Zugangsdaten.');

        try {
            app(DomainCommandService::class)->dispatch($command);
            $this->fail('Befehl über eine getrennte Verbindung gesendet.');
        } catch (DomainActionException) {
            $this->assertSame(DomainProviderCommandStatus::Approved, $command->fresh()?->status, 'kein hängender Pending-Stempel');
        }

        $fake->assertNothingSent();
        $this->assertSame(DomainConnectionStatus::Disconnected, $connection->fresh()?->status);
    }

    public function test_index_offers_only_new_credentials_for_a_disconnected_connection(): void {
        $connection = $this->connection(DomainConnectionStatus::Disconnected);

        $this->actingAs($this->admin)->get(route('admin.domain-provider.index'))->assertOk()
            ->assertSee('Getrennt')
            ->assertSee(route('admin.domain-provider.credentials', $connection), false)
            ->assertDontSee(route('admin.domain-provider.test', $connection), false)
            // Die Trennen-URL ist Präfix der Dialog-URL — deshalb über das Formularziel.
            ->assertDontSee('action="' . route('admin.domain-provider.destroy', $connection) . '"', false);
    }

    public function test_evidence_tables_restrict_deleting_the_connection(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('RESTRICT nur unter MySQL/MariaDB (siehe Migration 2027_04_05_100000).');
        }
        foreach (['domain_provider_commands', 'domain_accounting_entries', 'domain_events', 'domain_external_invoices'] as $table) {
            $fk = collect(Schema::getForeignKeys($table))->first(static fn (array $fk): bool => $fk['columns'] === ['connection_id']);
            $this->assertSame('restrict', strtolower((string) ($fk['on_delete'] ?? '')), $table);
        }
        $connection = $this->connection();
        app(DomainCommandService::class)->create($connection, DomainCapabilityArea::Dns, 'UpdateDNSZone', 'gartenbau-beispiel.de');

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('domain_provider_connections')->where('id', $connection->id)->delete();
    }

    public function test_credentials_dialog_requires_the_manage_permission(): void {
        $connection = $this->connection();
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(Permission::DomainProviderView->value);

        $this->actingAs($viewer)->get(route('admin.domain-provider.credentials', $connection))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.domain-provider.rotate', $connection), ['password' => 'neu'])->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.domain-provider.credentials', $connection))->assertOk()
            ->assertSee('Zugangsdaten erneuern')
            ->assertSee('Neues Passwort');
    }

    public function test_new_credentials_reconnect_a_disconnected_connection_after_the_check(): void {
        $connection = $this->connection(DomainConnectionStatus::Disconnected);
        $connection->forceFill(['password' => null])->save();
        $fake = FakeDomainResellingTransport::fake(['StatusUser' => "code=200\ndescription=ok\nEOF\n"]);

        $this->actingAs($this->admin)->post(route('admin.domain-provider.rotate', $connection), ['password' => 'neues-passwort'])
            ->assertRedirect(route('admin.domain-provider.index'))
            ->assertSessionHas('success', 'Zugangsdaten aktualisiert.');

        $connection->refresh();
        $this->assertSame(DomainConnectionStatus::Active, $connection->status);
        $this->assertSame('neues-passwort', $connection->password);
        $this->assertSame('reseller-nord', $connection->login, 'leerer Login bleibt unverändert');
        $fake->assertSent(static fn ($request): bool => str_contains((string) $request->getBody(), 's_pw=neues-passwort'));
    }

    public function test_failed_check_blocks_the_connection_and_says_so(): void {
        $connection = $this->connection();
        FakeDomainResellingTransport::fake(['StatusUser' => "code=530\ndescription=Authentication failed\nEOF\n"]);

        $this->actingAs($this->admin)->post(route('admin.domain-provider.rotate', $connection), ['password' => 'falsch'])
            ->assertSessionHas('error', 'Verbindung fehlgeschlagen — Zugangsdaten/Umgebung prüfen.');

        $this->assertSame(DomainConnectionStatus::Blocked, $connection->fresh()?->status);
    }

    public function test_rotation_validates_password_and_login_uniqueness(): void {
        $connection = $this->connection();
        DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id, 'login' => 'reseller-sued']);

        $this->actingAs($this->admin)->post(route('admin.domain-provider.rotate', $connection), [])->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post(route('admin.domain-provider.rotate', $connection), ['login' => 'reseller-sued', 'password' => 'x'])->assertSessionHasErrors('login');
        $this->assertSame('reseller-nord', $connection->fresh()?->login);
    }
}

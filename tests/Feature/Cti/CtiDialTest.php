<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiDialTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Cti;

use App\Models\{CtiConnection, Customer, User};
use App\Services\Cti\Dial\{CtiDialException, CtiDialService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Click-to-Dial (Feature 056/MVP-118; Audit 2026-08, W4.5): ausgehender
 * Anruf-Start über die Telefonanlage der Organisation.
 */
class CtiDialTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        // Mitarbeiter-Rolle: Wählen verlangt ein Kundenkontakt-Sichtrecht (E8).
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function connection(array $attributes = []): CtiConnection {
        [$connection] = CtiConnection::issue((int) $this->organization->id, 'Anlage', 'sipgate', (int) $this->user->id);
        $connection->forceFill(array_merge([
            'dial_enabled' => true,
            'api_token' => 'tok-123',
            'dial_extension' => 'e17',
        ], $attributes))->save();

        return $connection->fresh();
    }

    public function test_dial_posts_to_the_phone_system_with_normalized_number(): void {
        $this->connection();

        $fake = FakePluginHttp::fake([
            'https://api.sipgate.com/v2/sessions/calls' => FakePluginHttp::response([], 200),
        ]);

        app(CtiDialService::class)->dial($this->organization, '0511 / 12345678');

        $fake->assertSent(function ($request): bool {
            $body = (array) json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                // Die Anlage ruft zuerst die eigene Durchwahl, dann das Ziel.
                && ($body['caller'] ?? null) === 'e17'
                && ($body['callee'] ?? null) === '+4951112345678';
        });
    }

    public function test_dial_without_enabled_connection_is_refused(): void {
        $this->connection(['dial_enabled' => false]);
        $fake = FakePluginHttp::fake();

        $this->expectException(CtiDialException::class);
        try {
            app(CtiDialService::class)->dial($this->organization, '+4951112345678');
        } finally {
            $fake->assertNothingSent();
        }
    }

    public function test_dial_without_extension_is_refused(): void {
        $this->connection(['dial_extension' => null]);
        $fake = FakePluginHttp::fake();

        $this->expectException(CtiDialException::class);
        try {
            app(CtiDialService::class)->dial($this->organization, '+4951112345678');
        } finally {
            $fake->assertNothingSent();
        }
    }

    public function test_unusable_number_is_refused_before_any_request(): void {
        $this->connection();
        $fake = FakePluginHttp::fake();

        $this->expectException(CtiDialException::class);
        try {
            app(CtiDialService::class)->dial($this->organization, 'kein telefon');
        } finally {
            $fake->assertNothingSent();
        }
    }

    public function test_rejection_by_the_phone_system_surfaces_as_error(): void {
        $this->connection();
        FakePluginHttp::fake([
            'https://api.sipgate.com/v2/sessions/calls' => FakePluginHttp::response(['error' => 'busy'], 409),
        ]);

        $this->expectException(CtiDialException::class);
        app(CtiDialService::class)->dial($this->organization, '+4951112345678');
    }

    public function test_endpoint_reports_error_without_connection(): void {
        // Keine Anbindung eingerichtet: freundliche Meldung statt Serverfehler.
        $this->actingAs($this->user)
            ->post(route('cti.dial'), ['number' => '+4951112345678'])
            ->assertRedirect();
        $this->assertNotNull(session('error'));
    }

    public function test_endpoint_dials_and_reports_success(): void {
        $this->connection();
        FakePluginHttp::fake([
            'https://api.sipgate.com/v2/sessions/calls' => FakePluginHttp::response([], 200),
        ]);

        $this->actingAs($this->user)
            ->post(route('cti.dial'), ['number' => '+4951112345678'])
            ->assertRedirect();
        $this->assertNotNull(session('success'));
    }

    /**
     * Vollscan 2026-08-23, E8: Wählen über die Org-Nebenstelle verlangt
     * Kunden-Sichtrecht, ist gedrosselt und wird mit Auslöser auditiert.
     */
    public function test_endpoint_requires_customer_view_permission_and_audits_the_caller(): void {
        $this->connection();
        FakePluginHttp::fake([
            'https://api.sipgate.com/v2/sessions/calls' => FakePluginHttp::response([], 200),
        ]);

        $stranger = User::factory()->create(['organization_id' => $this->organization->id]);
        $stranger->syncRoles([]);
        $stranger->syncPermissions([]);

        $this->actingAs($stranger)->post(route('cti.dial'), ['number' => '+4951112345678'])->assertForbidden();

        $this->actingAs($this->user)->post(route('cti.dial'), ['number' => '+4951112345678'])->assertRedirect();
        $this->assertNotNull(session('success'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'cti.dial_started', 'user_id' => $this->user->id]);
    }

    public function test_customer_detail_shows_call_button_only_with_connection(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '+4951112345678',
        ]);

        $this->actingAs($this->user)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee(route('cti.dial'));

        $this->connection();

        $this->actingAs($this->user)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(route('cti.dial'));
    }
}

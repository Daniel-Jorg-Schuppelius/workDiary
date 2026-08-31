<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicSerialPassportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{SerialSource, SerialStatus};
use App\Models\{Article, ArticleVariant, Customer, Warehouse};
use App\Services\Inventory\{SerialPassportService, SerialService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Öffentlicher Geräte-Pass (Feature 047/048, E2): opt-in pro Org, ohne PII,
 * unbekannter/deaktivierter Zugang liefert 404.
 */
final class PublicSerialPassportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'serial_required' => true]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
    }

    public function test_enabled_passport_shows_status_without_pii(): void {
        $this->enablePassport('TOK-123');
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Geheim Kunde GmbH']);
        $serial = app(SerialService::class)->register($this->variant, 'PASS-1', SerialSource::Manufactured, $this->warehouse);
        $serial->forceFill(['status' => SerialStatus::Shipped, 'customer_id' => $customer->id])->save();

        $response = $this->get('/serial-passport/TOK-123?serial=PASS-1');

        $response->assertOk()->assertSee('PASS-1')->assertDontSee('Geheim Kunde GmbH');
    }

    public function test_disabled_passport_returns_404(): void {
        $this->organization->update(['settings' => [
            SerialPassportService::HASH_KEY => SerialPassportService::fingerprint('TOK-9'),
            SerialPassportService::ENABLED_KEY => false,
        ]]);

        $this->get('/serial-passport/TOK-9?serial=PASS-1')->assertNotFound();
    }

    /** Der Klartext-Token steht nicht mehr in der Datenbank (S-44). */
    public function test_token_is_stored_only_as_fingerprint(): void {
        $this->enablePassport('TOK-123');

        $settings = (array) $this->organization->refresh()->settings;

        $this->assertArrayNotHasKey('serial_passport_token', $settings);
        $this->assertNotContains('TOK-123', $settings, 'Der Klartext darf nirgends in den Einstellungen stehen.');
        $this->assertSame(SerialPassportService::fingerprint('TOK-123'), $settings[SerialPassportService::HASH_KEY]);
    }

    /** Ausstellen liefert den Klartext genau einmal und entwertet den alten Link. */
    public function test_issuing_rotates_the_link(): void {
        $service = app(SerialPassportService::class);
        $first = $service->issue($this->organization);
        $service->setEnabled($this->organization, true);

        $this->get('/serial-passport/' . $first . '?serial=PASS-1')->assertOk();

        $second = $service->issue($this->organization->refresh());
        $this->assertNotSame($first, $second);

        $this->get('/serial-passport/' . $first . '?serial=PASS-1')->assertNotFound();
        $this->get('/serial-passport/' . $second . '?serial=PASS-1')->assertOk();
    }

    /** Entzug schaltet zugleich ab — ein halber Zustand wäre eine Falle. */
    public function test_revoking_closes_the_public_page(): void {
        $service = app(SerialPassportService::class);
        $token = $service->issue($this->organization);
        $service->setEnabled($this->organization, true);

        $service->revoke($this->organization->refresh());

        $this->get('/serial-passport/' . $token . '?serial=PASS-1')->assertNotFound();
        $this->assertFalse($service->status($this->organization->refresh())['enabled']);
    }

    /** Ohne ausgestellten Token lässt sich die Seite nicht freischalten. */
    public function test_cannot_enable_without_a_token(): void {
        $service = app(SerialPassportService::class);
        $service->setEnabled($this->organization, true);

        $this->assertFalse($service->status($this->organization->refresh())['enabled']);
    }

    public function test_unknown_token_returns_404(): void {
        $this->enablePassport('TOK-123');

        $this->get('/serial-passport/NOPE?serial=PASS-1')->assertNotFound();
    }

    /** Die Verwaltung ist Org-Admin-Sache — Lagerrechte allein genügen nicht. */
    public function test_passport_settings_require_organization_update(): void {
        $this->actingAs($this->orgUser())->get(route('serials.passport.edit'))->assertForbidden();
        $this->actingAs($this->orgUser())->post(route('serials.passport.rotate'))->assertForbidden();
        $this->actingAs($this->orgAdmin())->get(route('serials.passport.edit'))->assertOk();
    }

    /** Über die Oberfläche: ausstellen, freischalten, entziehen. */
    public function test_passport_can_be_issued_and_revoked_over_http(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->post(route('serials.passport.rotate'))
            ->assertRedirect(route('serials.passport.edit'))
            ->assertSessionHas('serial_passport_token');

        $token = session('serial_passport_token');
        $this->assertIsString($token);

        $this->actingAs($admin)->patch(route('serials.passport.toggle'), ['enabled' => 1])->assertRedirect();
        $this->get('/serial-passport/' . $token . '?serial=PASS-1')->assertOk();

        $this->actingAs($admin)->delete(route('serials.passport.revoke'))->assertRedirect();
        $this->get('/serial-passport/' . $token . '?serial=PASS-1')->assertNotFound();
    }

    private function enablePassport(string $token): void {
        $this->organization->update(['settings' => [
            SerialPassportService::HASH_KEY => SerialPassportService::fingerprint($token),
            SerialPassportService::HINT_KEY => mb_substr($token, 0, 6),
            SerialPassportService::ENABLED_KEY => true,
        ]]);
    }
}

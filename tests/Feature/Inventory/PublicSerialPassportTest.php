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
use App\Services\Inventory\SerialService;
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
        $this->organization->update(['settings' => ['serial_passport_token' => 'TOK-9', 'serial_passport_enabled' => false]]);

        $this->get('/serial-passport/TOK-9?serial=PASS-1')->assertNotFound();
    }

    public function test_unknown_token_returns_404(): void {
        $this->enablePassport('TOK-123');

        $this->get('/serial-passport/NOPE?serial=PASS-1')->assertNotFound();
    }

    private function enablePassport(string $token): void {
        $this->organization->update(['settings' => ['serial_passport_token' => $token, 'serial_passport_enabled' => true]]);
    }
}

<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalLinkGuardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\{Customer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * S-47 (Sicherheitsscan 2026-08-23): nutzergesetzte URLs landeten ohne
 * Schema-Prüfung im `href`. Blade escaped die Attributzeichen, aber nicht das
 * Schema — `javascript:…` blieb ein ausführbarer Link.
 */
class ExternalLinkGuardTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function urls(): array {
        return [
            'https' => ['https://example.test/pfad', true],
            'http' => ['http://example.test', true],
            'mailto' => ['mailto:info@example.test', true],
            'tel' => ['tel:+4930123456', true],
            'relativ' => ['/kunden/7', true],
            'javascript' => ['javascript:alert(1)', false],
            'javascript gemischt' => ['JaVaScRiPt:alert(1)', false],
            'data' => ['data:text/html;base64,PHNjcmlwdD4=', false],
            'vbscript' => ['vbscript:msgbox(1)', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('urls')]
    public function test_nur_erlaubte_schemata_werden_verlinkt(string $url, bool $expectLink): void {
        $html = Blade::render('<x-external-link :url="$u" />', ['u' => $url]);

        if ($expectLink) {
            $this->assertStringContainsString('href="' . e($url) . '"', $html, 'Erlaubtes Schema muss verlinkt werden.');

            return;
        }

        $this->assertStringNotContainsString('href=', $html, 'Unerlaubtes Schema darf kein href erzeugen.');
        // Die Adresse bleibt sichtbar — verschweigen wäre schlechter als
        // entschärfen: wer sie eingetragen hat, soll sie korrigieren können.
        $this->assertStringContainsString(e($url), $html);
    }

    public function test_kundenseite_verlinkt_kein_javascript(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'homepage' => 'javascript:alert(document.cookie)',
        ]);

        $this->actingAs($admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee('href="javascript:', false);
    }
}

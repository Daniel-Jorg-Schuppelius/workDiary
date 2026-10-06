<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LayoutValidationErrorsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validierungsfehler eines Vollseiten-POST erscheinen immer: die Seite zeigt
 * sie selbst (`<x-validation-errors>`), sonst das Layout
 * (Konsolidierungs-Audit 2026-10, k4-08 — rund 270 Seiten hatten keine Anzeige).
 */
final class LayoutValidationErrorsTest extends TestCase {
    use RefreshDatabase;

    private const MESSAGE = 'Statuswechsel ist nicht mehr möglich.';

    /**
     * Die Sitzung serialisiert als JSON; der Fehler-Bag liegt dort in seiner
     * Array-Form und wird beim Laden wieder zum Objekt.
     *
     * @return array{errors: array<string, array{format: string, messages: array<string, list<string>>}>}
     */
    private function errorSession(): array {
        return ['errors' => ['default' => ['format' => ':message', 'messages' => ['status' => [self::MESSAGE]]]]];
    }

    public function test_layout_shows_errors_the_page_does_not_render(): void {
        $admin = User::factory()->admin()->create();

        $html = (string) $this->actingAs($admin)->withSession($this->errorSession())->get(route('dashboard'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, self::MESSAGE));
        $this->assertStringContainsString('role="alert"', $html);
    }

    public function test_page_with_its_own_error_block_shows_them_once(): void {
        $admin = User::factory()->admin()->create();

        $html = (string) $this->actingAs($admin)->withSession($this->errorSession())->get(route('customers.duplicates.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, self::MESSAGE));
    }

    public function test_no_errors_no_block(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertDontSee(self::MESSAGE);
    }
}

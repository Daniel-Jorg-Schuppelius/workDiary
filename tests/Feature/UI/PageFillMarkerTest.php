<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PageFillMarkerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\UI;

use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Der Marker `partials.page-fill` setzt die Voll-Höhe-Klassen am Layout —
 * auch als eingebundene Teilvorlage (Konsolidierungs-Audit 2026-10, k4-15).
 */
final class PageFillMarkerTest extends TestCase {
    use RefreshDatabase;

    private const MAIN = 'class="wd-surface min-h-0 flex flex-col lg:overflow-clip"';

    public function test_list_page_with_the_marker_fills_the_viewport(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('customers.index'))->assertOk()->assertSee(self::MAIN, false);
    }

    public function test_page_without_the_marker_keeps_the_plain_main(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertDontSee(self::MAIN, false);
    }
}

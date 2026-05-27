<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberFormatControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Admin;

use App\Enums\Numbering\NumberScope;
use App\Models\{Organization, User};
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberFormatControllerTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->app->instance('currentOrganization', $this->org);
    }

    public function test_index_lists_all_scopes_with_defaults(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $response = $this->actingAs($admin)->get(route('admin.number-formats.index'));
        $response->assertOk();

        $rows = $response->viewData('rows');
        $this->assertIsArray($rows);
        $this->assertCount(count(NumberScope::cases()), $rows);
    }

    public function test_update_persists_format_and_affects_next_number(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $this->actingAs($admin)
            ->put(route('admin.number-formats.update'), [
                'scope' => NumberScope::Asset->value,
                'prefix' => 'INV',
                'prefix_separator' => '-',
                'include_year' => '1',
                'year_separator' => '-',
                'padding' => 6,
                'reset_per_year' => '1',
                'starts_at' => 0,
            ])
            ->assertRedirect(route('admin.number-formats.index'));

        $this->assertDatabaseHas('number_formats', [
            'organization_id' => $this->org->id,
            'scope' => NumberScope::Asset->value,
            'prefix' => 'INV',
            'padding' => 6,
        ]);

        $next = app(NumberSequenceService::class)
            ->next($this->org, NumberScope::Asset, now());
        $this->assertStringStartsWith('INV-', $next);
        $this->assertSame(6, strlen(substr($next, strrpos($next, '-') + 1)));
    }

    public function test_update_requires_manage_permission(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->org->id]);

        $this->actingAs($member)
            ->put(route('admin.number-formats.update'), [
                'scope' => NumberScope::Asset->value,
                'padding' => 4,
                'starts_at' => 0,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('number_formats', 0);
    }
}

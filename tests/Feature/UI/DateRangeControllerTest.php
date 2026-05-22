<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DateRangeControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Models\User;
use App\Services\UI\DateRangeContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DateRangeControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 15, 12, 0, 0));
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    protected function tearDown(): void {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_preset_persists_in_session(): void {
        $this->actingAs($this->user)
            ->from(route('dashboard'))
            ->post(route('ui.date-range.update'), ['preset' => 'last_month'])
            ->assertRedirect(route('dashboard'));

        $range = $this->app->make(DateRangeContext::class)->current();
        $this->assertSame(DateRangeContext::PRESET_LAST_MONTH, $range['preset']);
        $this->assertSame('2026-04-01', $range['from']->toDateString());
    }

    public function test_custom_range_validates_dates(): void {
        $this->actingAs($this->user)
            ->from(route('dashboard'))
            ->post(route('ui.date-range.update'), [
                'preset' => 'custom',
                'from' => '2026-06-01',
                'to' => '2026-05-01',
            ])
            ->assertSessionHasErrors('to');
    }

    public function test_custom_range_stores_explicit_dates(): void {
        $this->actingAs($this->user)
            ->from(route('dashboard'))
            ->post(route('ui.date-range.update'), [
                'preset' => 'custom',
                'from' => '2026-01-01',
                'to' => '2026-03-31',
            ])
            ->assertRedirect(route('dashboard'));

        $range = $this->app->make(DateRangeContext::class)->current();
        $this->assertSame(DateRangeContext::PRESET_CUSTOM, $range['preset']);
        $this->assertSame('2026-01-01', $range['from']->toDateString());
        $this->assertSame('2026-03-31', $range['to']->toDateString());
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void {
        $this->post(route('ui.date-range.update'), ['preset' => 'today'])
            ->assertRedirect(route('login'));
    }
}

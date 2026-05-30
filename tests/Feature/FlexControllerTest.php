<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : GitHub Copilot
 * Filename     : FlexControllerTest.php
 * License      : AGPL-3.0-or-later
 */

namespace Tests\Feature;

use App\Models\User;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class FlexControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_admin_can_select_other_user_via_sqid(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)->get(route('flex.index', [
            'user' => Sqid::encode(User::class, $other->id),
        ]));

        $response->assertOk();
        $response->assertViewHas('user', static fn(User $user): bool => (int) $user->id === (int) $other->id);
    }

    public function test_admin_can_select_other_user_via_numeric_fallback(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)->get(route('flex.index', [
            'user' => (string) $other->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('user', static fn(User $user): bool => (int) $user->id === (int) $other->id);
    }

    public function test_non_admin_cannot_switch_user_via_numeric_fallback(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($user)->get(route('flex.index', [
            'user' => (string) $other->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('user', static fn(User $resolved): bool => (int) $resolved->id === (int) $user->id);
    }
}

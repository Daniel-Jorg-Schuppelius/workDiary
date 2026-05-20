<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RolesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\DiaryEntry;
use App\Models\User;
use App\Enums\User\UserRole;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_user_factory_admin_state_assigns_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasRole(UserRole::Admin->value));
    }

    public function test_user_factory_user_state_assigns_user_role(): void
    {
        $user = User::factory()->user()->create();

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->hasRole(UserRole::User->value));
    }

    public function test_owner_can_update_and_delete_own_entry(): void
    {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::create([
            'user_id' => $owner->id,
            'content' => 'mine',
            'status' => 2,
            'start_at' => now(),
        ]);

        $this->assertTrue($owner->can('update', $entry));
        $this->assertTrue($owner->can('delete', $entry));
        $this->assertTrue($owner->can('view', $entry));
    }

    public function test_other_user_cannot_touch_foreign_entry(): void
    {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        $entry = DiaryEntry::create([
            'user_id' => $owner->id,
            'content' => 'mine',
            'status' => 2,
            'start_at' => now(),
        ]);

        $this->assertFalse($other->can('update', $entry));
        $this->assertFalse($other->can('delete', $entry));
        $this->assertFalse($other->can('view', $entry));
    }

    public function test_admin_can_touch_any_entry_via_before_hook(): void
    {
        $owner = User::factory()->user()->create();
        $admin = User::factory()->admin()->create();
        $entry = DiaryEntry::create([
            'user_id' => $owner->id,
            'content' => 'foreign',
            'status' => 2,
            'start_at' => now(),
        ]);

        $this->assertTrue($admin->can('update', $entry));
        $this->assertTrue($admin->can('delete', $entry));
        $this->assertTrue($admin->can('view', $entry));
    }
}

<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypeAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Admin;

use App\Enums\Diary\Priority;
use App\Enums\Diary\Status;
use App\Models\DiaryEntry;
use App\Models\EntryType;
use App\Models\User;
use Database\Seeders\EntryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class EntryTypeAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(EntryTypeSeeder::class);
    }

    public function test_non_admin_cannot_access_index(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('admin.entry-types.index'))
            ->assertForbidden();
    }

    public function test_admin_can_list_entry_types(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('admin.entry-types.index'))
            ->assertOk()
            ->assertSee('Eintragstypen');
    }

    public function test_admin_can_create_entry_type(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->post(route('admin.entry-types.store'), [
                'slug' => 'plumbing_job',
                'label' => 'Sanitär-Auftrag',
                'icon' => 'plumbing',
                'color' => 'info',
                'description' => 'Wasserrohr & Co.',
                'sort' => 50,
                'is_active' => '1',
                'requires_customer' => '1',
                'requires_address' => '1',
                'requires_schedule' => '1',
                'allow_priority' => '1',
                'allow_tour' => '1',
                'default_status' => Status::Open->value,
                'default_service_minutes' => 90,
                'default_priority' => 'high',
            ])
            ->assertRedirect(route('admin.entry-types.index'));

        $type = EntryType::query()->where('slug', 'plumbing_job')->firstOrFail();
        $this->assertSame('Sanitär-Auftrag', $type->label);
        $this->assertTrue($type->requires_customer);
        $this->assertTrue($type->requires_address);
        $this->assertTrue($type->requires_schedule);
        $this->assertTrue($type->allow_priority);
        $this->assertSame(90, $type->default_service_minutes);
        $this->assertSame(Priority::High, $type->default_priority);
    }

    public function test_slug_must_be_unique_per_organization(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->from(route('admin.entry-types.create'))
            ->post(route('admin.entry-types.store'), [
                'slug' => EntryType::SLUG_SERVICE,
                'label' => 'Duplicate',
                'icon' => 'build',
                'color' => 'primary',
                'default_status' => Status::Open->value,
            ])
            ->assertRedirect(route('admin.entry-types.create'))
            ->assertSessionHasErrors('slug');
    }

    public function test_admin_can_update_entry_type(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $type = EntryType::query()->where('slug', EntryType::SLUG_GENERAL)->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.entry-types.update', $type), [
                'slug' => $type->slug,
                'label' => 'Allgemein (geändert)',
                'icon' => $type->icon,
                'color' => 'accent',
                'sort' => 5,
                'is_active' => '1',
                'allow_priority' => '1',
                'default_status' => Status::Open->value,
            ])
            ->assertRedirect(route('admin.entry-types.index'));

        $type->refresh();
        $this->assertSame('Allgemein (geändert)', $type->label);
        $this->assertSame('accent', $type->color);
        $this->assertSame(5, $type->sort);
        $this->assertTrue($type->allow_priority);
    }

    public function test_admin_can_delete_unused_entry_type(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $type = EntryType::create([
            'organization_id' => $this->organization->id,
            'slug' => 'temp_type',
            'label' => 'Temporär',
            'icon' => 'task_alt',
            'color' => 'ghost',
            'sort' => 999,
            'is_active' => true,
            'default_status' => Status::Open->value,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.entry-types.destroy', $type))
            ->assertRedirect(route('admin.entry-types.index'));

        $this->assertDatabaseMissing('entry_types', ['id' => $type->id]);
    }

    public function test_cannot_delete_entry_type_with_diary_entries(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $type = EntryType::query()->where('slug', EntryType::SLUG_GENERAL)->firstOrFail();

        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $admin->id,
            'entry_type_id' => $type->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.entry-types.destroy', $type))
            ->assertRedirect(route('admin.entry-types.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('entry_types', ['id' => $type->id]);
    }
}

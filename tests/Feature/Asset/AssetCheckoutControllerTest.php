<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetCheckoutControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\{AssetStatus, DefectSeverity};
use App\Enums\User\UserRole;
use App\Models\{Asset, AssetDefect, User};
use App\Services\Asset\AssetAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AssetCheckoutControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_aussendienst_can_check_out_asset(): void {
        $user = $this->userWithRole(UserRole::Aussendienst->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'status' => AssetStatus::Active->value]);
        $target = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('assets.checkout.store', $asset), [
                'assigned_to_user_id' => $target->sqid,
            ])
            ->assertRedirect(route('assets.show', $asset));

        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $asset->id,
            'assigned_to_user_id' => $target->id,
            'returned_at' => null,
        ]);
    }

    public function test_user_without_checkout_permission_is_forbidden(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $target = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('assets.checkout.store', $asset), ['assigned_to_user_id' => $target->sqid])
            ->assertForbidden();
    }

    public function test_checkin_releases_asset(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'status' => AssetStatus::Active->value]);
        $target = User::factory()->create(['organization_id' => $this->organization->id]);

        $service = app(AssetAssignmentService::class);
        $assignment = $service->checkOut($asset, $user, $target);

        $this->actingAs($user)
            ->post(route('assets.checkout.return', [$asset, $assignment]), ['condition_in' => 'fine'])
            ->assertRedirect(route('assets.show', $asset));

        $this->assertNotNull($assignment->refresh()->returned_at);
    }

    public function test_blocked_asset_checkout_returns_validation_error(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'status' => AssetStatus::Active->value]);
        $target = User::factory()->create(['organization_id' => $this->organization->id]);

        app(AssetAssignmentService::class)->reportDefect($asset, $user, [
            'severity' => DefectSeverity::High->value,
            'title' => 'kaputt',
            'blocks_usage' => true,
        ]);

        $this->actingAs($user)
            ->post(route('assets.checkout.store', $asset), ['assigned_to_user_id' => $target->sqid])
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertDatabaseMissing('asset_assignments', ['asset_id' => $asset->id, 'returned_at' => null]);
    }

    public function test_defect_report_stores_uploaded_photo(): void {
        Storage::fake('local');
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('assets.defects.store', $asset), [
                'severity' => DefectSeverity::Medium->value,
                'title' => 'Kratzer im Gehäuse',
                'photos' => [UploadedFile::fake()->image('schaden.jpg', 320, 240)],
            ])
            ->assertRedirect(route('assets.show', $asset));

        $defect = AssetDefect::query()->where('asset_id', $asset->id)->firstOrFail();
        $attachment = $defect->attachmentByMeta(AssetDefect::PHOTO_META);
        $this->assertNotNull($attachment);
        $this->assertSame('schaden.jpg', $attachment->original_name);
        $this->assertTrue(Storage::disk('local')->exists($attachment->path));
    }

    public function test_defect_report_rejects_non_image_upload(): void {
        Storage::fake('local');
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('assets.defects.store', $asset), [
                'severity' => DefectSeverity::Medium->value,
                'title' => 'x',
                'photos' => [UploadedFile::fake()->create('exploit.exe', 12, 'application/x-msdownload')],
            ])
            ->assertSessionHasErrors('photos.0');

        $this->assertSame(0, AssetDefect::query()->count());
    }

    public function test_aussendienst_cannot_report_defect(): void {
        $user = $this->userWithRole(UserRole::Aussendienst->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('assets.defects.store', $asset), [
                'severity' => DefectSeverity::Medium->value,
                'title' => 'x',
            ])
            ->assertForbidden();
    }

    public function test_teamleitung_can_report_and_resolve_defect(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('assets.defects.store', $asset), [
                'severity' => DefectSeverity::High->value,
                'title' => 'Lüfter defekt',
                'blocks_usage' => '1',
            ])
            ->assertRedirect(route('assets.show', $asset));

        $defect = AssetDefect::query()->where('asset_id', $asset->id)->firstOrFail();
        $this->assertTrue($defect->isBlocking());

        $this->actingAs($user)
            ->post(route('assets.defects.transition', [$asset, $defect]), [
                'action' => 'resolve',
                'resolution_note' => 'Lüfter getauscht',
            ])
            ->assertRedirect(route('assets.show', $asset));

        $this->assertTrue($defect->refresh()->status->isClosed());
    }

    public function test_resolve_without_note_fails_validation(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $defect = AssetDefect::factory()->blocking()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'reported_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('assets.defects.transition', [$asset, $defect]), [
                'action' => 'resolve',
                'resolution_note' => '',
            ])
            ->assertSessionHasErrors('resolution_note');
    }

    private function userWithRole(string $role): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);

        $orgRole = Role::query()
            ->where('name', $role)
            ->where('team_id', $this->organization->id)
            ->firstOrFail();

        $user->syncRoles([$orgRole]);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user;
    }
}

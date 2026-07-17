<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PermitControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Permit;

use App\Enums\Permit\PermitStatus;
use App\Enums\User\UserRole;
use App\Models\{Event, Permit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class PermitControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_user_without_permission_cannot_view_index(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('permits.index'))
            ->assertForbidden();
    }

    public function test_teamleitung_can_view_index_with_filter(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        Permit::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Sondernutzung Marktplatz',
        ]);
        Permit::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'GEMA Sommerfest',
        ]);

        $this->actingAs($user)
            ->get(route('permits.index', ['q' => 'Sondernutzung']))
            ->assertOk()
            ->assertSee('Sondernutzung Marktplatz')
            ->assertDontSee('GEMA Sommerfest');
    }

    public function test_store_creates_permit(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        $response = $this->actingAs($user)->post(route('permits.store'), [
            'title' => 'Schankerlaubnis',
            'permit_type' => 'schankerlaubnis',
            'authority' => 'Gewerbeamt',
            'status' => PermitStatus::Applied->value,
            'applied_at' => now()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
        ]);

        $response->assertRedirect(route('permits.index'));
        $this->assertDatabaseHas('permits', [
            'organization_id' => $this->organization->id,
            'title' => 'Schankerlaubnis',
            'authority' => 'Gewerbeamt',
            'status' => PermitStatus::Applied->value,
        ]);
    }

    public function test_store_rejects_invalid_date_range(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        $response = $this->actingAs($user)->post(route('permits.store'), [
            'title' => 'Falscher Zeitraum',
            'status' => PermitStatus::Required->value,
            'valid_from' => now()->addMonth()->toDateString(),
            'valid_until' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('valid_until');
    }

    public function test_store_links_event_via_sqid(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $event = Event::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($user)->post(route('permits.store'), [
            'title' => 'Mit Event',
            'status' => PermitStatus::Required->value,
            'event_id' => $event->sqid,
        ]);

        $response->assertRedirect(route('permits.index'));
        $this->assertDatabaseHas('permits', [
            'organization_id' => $this->organization->id,
            'title' => 'Mit Event',
            'event_id' => $event->id,
        ]);
    }

    public function test_store_uploads_evidence_document(): void {
        Storage::fake('local');
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        $response = $this->actingAs($user)->post(route('permits.store'), [
            'title' => 'Mit Nachweis',
            'status' => PermitStatus::Granted->value,
            'evidence_document' => UploadedFile::fake()->create('bescheid.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect(route('permits.index'));
        $permit = Permit::query()->where('title', 'Mit Nachweis')->firstOrFail();
        $this->assertNotNull($permit->evidence());
        $this->assertSame('bescheid.pdf', $permit->evidence()?->original_name);
    }

    public function test_update_changes_status(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $permit = Permit::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Bleibt',
            'status' => PermitStatus::Required->value,
        ]);

        $response = $this->actingAs($user)->put(route('permits.update', $permit), [
            'title' => 'Bleibt',
            'status' => PermitStatus::Granted->value,
        ]);

        $response->assertRedirect(route('permits.index'));
        $this->assertSame(PermitStatus::Granted, $permit->refresh()->status);
    }

    public function test_destroy_deletes_permit(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $permit = Permit::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->delete(route('permits.destroy', $permit))
            ->assertRedirect(route('permits.index'));

        $this->assertDatabaseMissing('permits', ['id' => $permit->id]);
    }
}

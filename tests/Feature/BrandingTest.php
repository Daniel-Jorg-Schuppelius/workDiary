<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BrandingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Organization;
use App\Models\User;
use App\Services\BrandingService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Storage::fake('local');
    }

    private function makeOrg(): Organization {
        $org = Organization::factory()->create();
        $this->app->instance('currentOrganization', $org);

        return $org;
    }

    public function test_branding_service_returns_defaults_for_unconfigured_org(): void {
        $org = $this->makeOrg();
        $user = User::factory()->user()->create(['organization_id' => $org->id]);
        $this->actingAs($user);

        $service = app(BrandingService::class);

        // Ohne explizit gesetzten app_name fällt der Service auf den
        // Organisationsnamen zurück.
        $this->assertSame($org->name, $service->appName());
        $this->assertNull($service->logoUrl());
        $this->assertSame(config('branding.colors.primary'), $service->primaryColor());
    }

    public function test_branding_service_uses_org_overrides_when_present(): void {
        $org = Organization::factory()->create([
            'settings' => [
                'branding' => [
                    'app_name' => 'Acme Corp',
                    'colors' => ['primary' => '#ff00ff'],
                ],
            ],
        ]);
        $this->app->instance('currentOrganization', $org);
        $user = User::factory()->user()->create(['organization_id' => $org->id]);
        $this->actingAs($user);

        $service = app(BrandingService::class);

        $this->assertSame('Acme Corp', $service->appName());
        $this->assertSame('#ff00ff', $service->primaryColor());
    }

    public function test_admin_can_update_branding_settings(): void {
        $org = $this->makeOrg();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)
            ->put(route('admin.branding.update'), [
                'branding' => [
                    'app_name' => 'My Company',
                    'slogan' => 'Wir bauen Zukunft',
                    'contact' => ['email' => 'info@example.com'],
                    'colors' => ['primary' => '#112233'],
                    'pdf' => [
                        'timesheet' => ['logo' => 'light', 'show_contact' => '1', 'show_footer' => '1'],
                        'invoice' => ['logo' => 'none', 'show_contact' => '0', 'show_footer' => '1'],
                        'diary' => ['logo' => 'dark', 'show_contact' => '1', 'show_footer' => '0'],
                    ],
                ],
            ])
            ->assertRedirect();

        $org->refresh();
        $this->assertSame('My Company', data_get($org->settings, 'branding.app_name'));
        $this->assertSame('#112233', data_get($org->settings, 'branding.colors.primary'));
        $this->assertFalse((bool) data_get($org->settings, 'branding.pdf.invoice.show_contact'));
        $this->assertFalse((bool) data_get($org->settings, 'branding.pdf.diary.show_footer'));
    }

    public function test_non_admin_cannot_update_branding(): void {
        $org = $this->makeOrg();
        $regular = User::factory()->user()->create(['organization_id' => $org->id]);

        $this->actingAs($regular)
            ->put(route('admin.branding.update'), ['branding' => ['app_name' => 'Hacked']])
            ->assertForbidden();
    }

    public function test_admin_can_upload_logo_for_organization(): void {
        $org = $this->makeOrg();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $file = UploadedFile::fake()->image('logo.png', 200, 80);

        $this->actingAs($admin)
            ->post(
                route('attachments.store', ['type' => 'organization', 'id' => $org->id]),
                ['file' => $file, 'meta_type' => Attachment::META_LOGO],
            )
            ->assertRedirect();

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => Organization::class,
            'attachable_id' => $org->id,
            'meta_type' => Attachment::META_LOGO,
        ]);
    }

    public function test_uploading_logo_replaces_previous(): void {
        $org = $this->makeOrg();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $first = UploadedFile::fake()->image('logo1.png', 200, 80);
        $second = UploadedFile::fake()->image('logo2.png', 200, 80);

        $this->actingAs($admin)
            ->post(route('attachments.store', ['type' => 'organization', 'id' => $org->id]), [
                'file' => $first,
                'meta_type' => Attachment::META_LOGO,
            ])->assertRedirect();

        $firstAttachment = Attachment::query()->where('meta_type', Attachment::META_LOGO)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('attachments.store', ['type' => 'organization', 'id' => $org->id]), [
                'file' => $second,
                'meta_type' => Attachment::META_LOGO,
            ])->assertRedirect();

        $this->assertDatabaseMissing('attachments', ['id' => $firstAttachment->id]);
        $this->assertSame(
            1,
            Attachment::query()
                ->where('attachable_type', Organization::class)
                ->where('attachable_id', $org->id)
                ->where('meta_type', Attachment::META_LOGO)
                ->count(),
        );
    }

    public function test_non_admin_cannot_upload_organization_logo(): void {
        $org = $this->makeOrg();
        $regular = User::factory()->user()->create(['organization_id' => $org->id]);

        $file = UploadedFile::fake()->image('logo.png', 200, 80);

        $this->actingAs($regular)
            ->post(route('attachments.store', ['type' => 'organization', 'id' => $org->id]), [
                'file' => $file,
                'meta_type' => Attachment::META_LOGO,
            ])
            ->assertForbidden();
    }

    public function test_user_can_upload_own_avatar(): void {
        $this->makeOrg();
        $user = User::factory()->user()->create();
        $file = UploadedFile::fake()->image('avatar.png', 120, 120);

        $this->actingAs($user)
            ->post(route('attachments.store', ['type' => 'user', 'id' => $user->id]), [
                'file' => $file,
                'meta_type' => Attachment::META_AVATAR,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => User::class,
            'attachable_id' => $user->id,
            'meta_type' => Attachment::META_AVATAR,
        ]);
    }

    public function test_user_cannot_upload_avatar_of_other_user(): void {
        $this->makeOrg();
        $a = User::factory()->user()->create();
        $b = User::factory()->user()->create();

        $file = UploadedFile::fake()->image('avatar.png', 120, 120);

        $this->actingAs($a)
            ->post(route('attachments.store', ['type' => 'user', 'id' => $b->id]), [
                'file' => $file,
                'meta_type' => Attachment::META_AVATAR,
            ])
            ->assertForbidden();
    }

    public function test_svg_logo_is_rejected(): void {
        $org = $this->makeOrg();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg></svg>');

        $this->actingAs($admin)
            ->post(route('attachments.store', ['type' => 'organization', 'id' => $org->id]), [
                'file' => $svg,
                'meta_type' => Attachment::META_LOGO,
            ])
            ->assertSessionHasErrors();
    }

    public function test_user_preferences_can_be_updated(): void {
        $this->makeOrg();
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->put(route('account.profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'preferences' => [
                    'theme' => 'dark',
                    'locale' => 'de',
                    'date_format' => 'Y-m-d',
                    'startpage' => 'dashboard',
                ],
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('dark', $user->preferences['theme'] ?? null);
        $this->assertSame('dashboard', $user->preferences['startpage'] ?? null);
    }
}

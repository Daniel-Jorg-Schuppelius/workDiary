<?php

/*
 * Filename     : StartPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Navigation;

use App\Enums\User\UserRole;
use App\Models\{Organization, User};
use App\Services\Navigation\StartPageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Startseiten (MVP-799, Feature 037). Vorher wurde die persönliche Wahl im
 * Profil gespeichert, aber nie gelesen — jede Person landete fest auf der
 * Arbeitsliste bzw. dem Dashboard.
 */
class StartPageTest extends TestCase {
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void {
        parent::setUp();
        $this->organization = Organization::factory()->enterprise()->create();
    }

    public function test_login_goes_through_the_start_route(): void {
        $user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'start@firma.de',
            'is_new_system' => true,
            'password' => Hash::make('Sicher!2026Pass'),
        ]);

        $this->post('/login', ['username' => $user->email, 'password' => 'Sicher!2026Pass'])
            ->assertRedirect(route('start'));
    }

    public function test_without_any_choice_the_previous_defaults_stay(): void {
        $user = $this->member();

        $this->actingAs($user)->get(route('start'))->assertRedirect(route('diary.index'));
        $this->actingAs($user)->withSession(['work_mode' => 'new'])->get(route('home'))->assertRedirect(route('dashboard'));
    }

    public function test_personal_choice_from_the_profile_is_honoured(): void {
        $user = $this->member(['preferences' => ['startpage' => 'week.index']]);

        $this->actingAs($user)->get(route('start'))->assertRedirect(route('week.index'));
        $this->actingAs($user)->withSession(['work_mode' => 'new'])->get(route('home'))->assertRedirect(route('week.index'));
    }

    public function test_role_default_of_the_organization_applies_without_personal_choice(): void {
        $this->setRoleDefault(UserRole::User, 'kanban.index');

        $this->actingAs($this->member())->get(route('start'))->assertRedirect(route('kanban.index'));
        $this->actingAs($this->member(['preferences' => ['startpage' => 'week.index']]))
            ->get(route('start'))->assertRedirect(route('week.index'));
    }

    public function test_a_page_the_person_may_not_open_is_skipped(): void {
        // Belegfluss ist Abrechnungsrecht — eine einfache Rolle sieht ihn nicht im Menü.
        $user = $this->member(['preferences' => ['startpage' => 'billing.feed']]);
        $this->actingAs($user);

        $this->assertNotContains('billing.feed', array_keys(app(StartPageResolver::class)->optionsFor($user)));
        $this->get(route('start'))->assertRedirect(route('diary.index'));
    }

    public function test_admin_sets_and_clears_role_defaults_on_the_scope_page(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->get(route('admin.scope.index'))
            ->assertOk()
            ->assertSee('name="startpages[aussendienst]"', false)
            ->assertSee(__('Wochenansicht'));

        $this->actingAs($admin)->post(route('admin.scope.startpages'), ['startpages' => ['aussendienst' => 'week.index']])
            ->assertRedirect();
        $this->assertSame('week.index', data_get($this->organization->refresh()->settings, 'personalization.startpage_roles.aussendienst'));

        $this->actingAs($admin)->post(route('admin.scope.startpages'), ['startpages' => ['aussendienst' => '']])
            ->assertRedirect();
        $this->assertNull(data_get($this->organization->refresh()->settings, 'personalization.startpage_roles.aussendienst'));
    }

    public function test_unknown_routes_are_rejected(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->post(route('admin.scope.startpages'), ['startpages' => ['user' => 'admin.scope.index']])
            ->assertSessionHasErrors('startpages.user');
        $this->assertNull(data_get($this->organization->refresh()->settings, 'personalization.startpage_roles.user'));
    }

    public function test_profile_offers_labels_instead_of_route_names(): void {
        $user = $this->member();

        $this->actingAs($user)->get(route('account.profile.edit'))
            ->assertOk()
            ->assertSee('<option value="week.index"', false)
            ->assertSee(__('Wochenansicht'))
            ->assertDontSee('>week.index<', false);
    }

    /** @param array<string, mixed> $attributes */
    private function member(array $attributes = []): User {
        return User::factory()->user()->create(['organization_id' => $this->organization->id] + $attributes);
    }

    private function setRoleDefault(UserRole $role, string $route): void {
        $settings = (array) ($this->organization->settings ?? []);
        data_set($settings, StartPageResolver::settingKey($role), $route);
        $this->organization->forceFill(['settings' => $settings])->save();
    }
}

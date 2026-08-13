<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserBookmarkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\{User, UserBookmark};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class UserBookmarkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_index_lists_only_own_bookmarks(): void {
        $this->setUpOrganization();
        $org = $this->organization;
        $user = User::factory()->user()->create(['organization_id' => $org->id]);
        $other = User::factory()->user()->create(['organization_id' => $org->id]);

        // Eindeutige URLs — kurze Substrings wie '/z' kollidieren mit
        // regulären Nav-Links (z. B. /zeitkonten).
        UserBookmark::create(['user_id' => $user->id, 'label' => 'A', 'url' => '/eigenes-lesezeichen-a', 'sort_order' => 1]);
        UserBookmark::create(['user_id' => $other->id, 'label' => 'Z', 'url' => '/fremdes-lesezeichen-z', 'sort_order' => 1]);

        $this->actingAs($user)
            ->get(route('bookmarks.index'))
            ->assertOk()
            ->assertSee('/eigenes-lesezeichen-a')
            ->assertDontSee('/fremdes-lesezeichen-z');
    }

    public function test_store_creates_bookmark_for_authenticated_user(): void {
        $this->setUpOrganization();
        $org = $this->organization;
        $user = User::factory()->user()->create(['organization_id' => $org->id]);

        $this->actingAs($user)
            ->post(route('bookmarks.store'), [
                'label' => 'Dashboard',
                'url' => '/dashboard',
                'icon' => 'dashboard',
                'sort_order' => 5,
            ])
            ->assertRedirect(route('bookmarks.index'));

        $this->assertDatabaseHas('user_bookmarks', [
            'user_id' => $user->id,
            'label' => 'Dashboard',
            'url' => '/dashboard',
            'icon' => 'dashboard',
            'sort_order' => 5,
        ]);
    }

    public function test_store_rejects_javascript_and_data_uri_schemes(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', ' javascript:alert(1)', 'vbscript:msgbox(1)'] as $payload) {
            $this->actingAs($user)
                ->from(route('bookmarks.index'))
                ->post(route('bookmarks.store'), ['label' => 'X', 'url' => $payload])
                ->assertSessionHasErrors('url');
        }

        $this->assertDatabaseCount('user_bookmarks', 0);
    }

    public function test_store_accepts_http_and_relative_urls(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        foreach (['https://example.test/x', 'http://example.test', '/intern/pfad'] as $ok) {
            $this->actingAs($user)
                ->post(route('bookmarks.store'), ['label' => 'OK', 'url' => $ok])
                ->assertSessionHasNoErrors();
        }
    }

    public function test_update_changes_own_bookmark(): void {
        $this->setUpOrganization();
        $org = $this->organization;
        $user = User::factory()->user()->create(['organization_id' => $org->id]);
        $bm = UserBookmark::create(['user_id' => $user->id, 'label' => 'Old', 'url' => '/old']);

        $this->actingAs($user)
            ->put(route('bookmarks.update', $bm), [
                'label' => 'New',
                'url' => '/new',
                'sort_order' => 9,
            ])
            ->assertRedirect(route('bookmarks.index'));

        $bm->refresh();
        $this->assertSame('New', $bm->label);
        $this->assertSame('/new', $bm->url);
        $this->assertSame(9, $bm->sort_order);
    }

    public function test_cannot_update_others_bookmark(): void {
        $this->setUpOrganization();
        $org = $this->organization;
        $user = User::factory()->user()->create(['organization_id' => $org->id]);
        $other = User::factory()->user()->create(['organization_id' => $org->id]);
        $bm = UserBookmark::create(['user_id' => $other->id, 'label' => 'X', 'url' => '/x']);

        $this->actingAs($user)
            ->put(route('bookmarks.update', $bm), ['label' => 'Hack', 'url' => '/hack'])
            ->assertForbidden();
    }

    public function test_destroy_removes_own_bookmark(): void {
        $this->setUpOrganization();
        $org = $this->organization;
        $user = User::factory()->user()->create(['organization_id' => $org->id]);
        $bm = UserBookmark::create(['user_id' => $user->id, 'label' => 'Del', 'url' => '/del']);

        $this->actingAs($user)
            ->delete(route('bookmarks.destroy', $bm))
            ->assertRedirect(route('bookmarks.index'));

        $this->assertDatabaseMissing('user_bookmarks', ['id' => $bm->id]);
    }

    public function test_cannot_destroy_others_bookmark(): void {
        $this->setUpOrganization();
        $org = $this->organization;
        $user = User::factory()->user()->create(['organization_id' => $org->id]);
        $other = User::factory()->user()->create(['organization_id' => $org->id]);
        $bm = UserBookmark::create(['user_id' => $other->id, 'label' => 'X', 'url' => '/x']);

        $this->actingAs($user)
            ->delete(route('bookmarks.destroy', $bm))
            ->assertForbidden();
    }

    public function test_guest_redirects_to_login(): void {
        $this->get(route('bookmarks.index'))->assertRedirect(route('login'));
    }

    public function test_store_validates_required_fields(): void {
        $this->setUpOrganization();
        $org = $this->organization;
        $user = User::factory()->user()->create(['organization_id' => $org->id]);

        $this->actingAs($user)
            ->post(route('bookmarks.store'), ['label' => '', 'url' => ''])
            ->assertSessionHasErrors(['label', 'url']);
    }
}

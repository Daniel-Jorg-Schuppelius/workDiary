<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ListReturnRedirectTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Rückmeldung 2026-09-19: Aktionen aus einer gefilterten Liste landeten auf
 * der ungefilterten Liste. RememberListUrl merkt sich die Listen-URL,
 * redirect()->toList() führt dorthin zurück.
 */
class ListReturnRedirectTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();

        Route::middleware('web')->group(function (): void {
            Route::get('/_list-return/{group}', static fn(): string => 'liste')->name('list-return.index');
            Route::post('/_list-return/{group}/act', static fn(string $group) => redirect()->toList('list-return.index', ['group' => $group]))
                ->name('list-return.act');
        });
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs(User::factory()->create());
    }

    public function test_without_remembered_list_the_plain_route_is_used(): void {
        $this->post('/_list-return/a/act')->assertRedirect(route('list-return.index', ['group' => 'a']));
    }

    public function test_action_returns_to_the_filtered_list(): void {
        $this->get('/_list-return/a?status=open&sort=name&page=2')->assertOk();

        $this->post('/_list-return/a/act')->assertRedirect(url('/_list-return/a?page=2&sort=name&status=open'));
    }

    public function test_resetting_the_filters_forgets_the_list(): void {
        $this->get('/_list-return/a?status=open')->assertOk();
        $this->get('/_list-return/a')->assertOk();

        $this->post('/_list-return/a/act')->assertRedirect(route('list-return.index', ['group' => 'a']));
    }

    public function test_remembered_query_only_applies_to_the_same_path(): void {
        $this->get('/_list-return/a?status=open')->assertOk();

        $this->post('/_list-return/b/act')->assertRedirect(route('list-return.index', ['group' => 'b']));
    }

    public function test_ajax_requests_do_not_overwrite_the_list(): void {
        $this->get('/_list-return/a?status=open')->assertOk();
        $this->get('/_list-return/a?status=closed', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->post('/_list-return/a/act')->assertRedirect(url('/_list-return/a?status=open'));
    }
}

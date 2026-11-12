<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimActionFollowUpOrderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Claims;

use App\Models\Claims\{ClaimAction, ClaimCase};
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\Claims\ClaimCaseService;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1623 (gl-135, `claim_actions.follow_up_*`): Eine Maßnahme der
 * Reklamationsakte nennt ihren Folgeauftrag — darüber läuft der
 * Serviceeinsatz in Disposition und Tourplanung (Feature 072, MVP-251).
 */
final class ClaimActionFollowUpOrderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private ClaimCase $claim;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->claim = app(ClaimCaseService::class)->open($this->organization, $this->admin, [
            'title' => 'Kessel verliert Druck',
            'source' => 'manual',
            'priority' => 'normal',
            'severity' => 'minor',
            'customer_id' => $this->customer->id,
        ]);
        $this->actingAs($this->admin);
    }

    public function test_action_links_its_follow_up_order_and_the_file_shows_it(): void {
        $order = $this->order($this->customer, 'Nacharbeit Kessel');
        $assignee = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->post(route('claims.actions.store', $this->claim), [
            'kind' => 'service_visit',
            'title' => 'Serviceeinsatz vor Ort',
            'assigned_user_id' => $assignee->sqid,
            'follow_up_order_id' => $order->sqid,
        ])->assertSessionHasNoErrors();

        $action = ClaimAction::query()->firstOrFail();
        $this->assertTrue(MorphMap::is($action->follow_up_type, DiaryEntry::class));
        $this->assertSame((int) $order->id, (int) $action->follow_up_id);
        $this->assertSame((int) $assignee->id, (int) $action->assigned_user_id);

        $this->get(route('claims.show', $this->claim))
            ->assertOk()
            ->assertSee(__('claims.action.follow_up_order'))
            ->assertSee('Nacharbeit Kessel')
            ->assertSee('name="follow_up_order_id"', false);
    }

    public function test_status_update_relinks_or_clears_the_follow_up_order(): void {
        $action = $this->claim->actions()->create([
            'organization_id' => $this->organization->id,
            'kind' => 'repair',
            'status' => 'planned',
            'title' => 'Reparatur',
        ]);
        $order = $this->order($this->customer, 'Reparatur Brenner');

        $this->put(route('claims.actions.update', $action), ['status' => 'in_progress', 'follow_up_order_id' => $order->sqid])->assertSessionHasNoErrors();
        $this->assertSame((int) $order->id, (int) $action->refresh()->follow_up_id);

        // Ohne das Feld bleibt der Folgeauftrag stehen, ein leeres Feld löst ihn.
        $this->put(route('claims.actions.update', $action), ['status' => 'done'])->assertSessionHasNoErrors();
        $this->assertSame((int) $order->id, (int) $action->refresh()->follow_up_id);
        $this->put(route('claims.actions.update', $action), ['status' => 'done', 'follow_up_order_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($action->refresh()->follow_up_id);
        $this->assertNull($action->follow_up_type);
    }

    public function test_order_of_another_customer_is_refused(): void {
        $other = $this->order(Customer::factory()->create(['organization_id' => $this->organization->id]), 'Fremder Auftrag');

        $this->post(route('claims.actions.store', $this->claim), [
            'kind' => 'service_visit',
            'title' => 'Serviceeinsatz',
            'follow_up_order_id' => $other->sqid,
        ])->assertSessionHasErrors('follow_up_order_id');

        $this->assertSame(0, ClaimAction::query()->count());
    }

    private function order(Customer $customer, string $title): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'user_id' => $this->admin->id,
            'title' => $title,
        ]);
    }
}

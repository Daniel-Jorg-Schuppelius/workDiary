<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntryPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\User\Permission as P;
use App\Models\{DiaryEntry, Organization, User};
use App\Policies\DiaryEntryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Tagebucheinträge/Aufträge — Kern-Entität: JEDE Methode prüft zuerst
 * sharesOrganization (Fremd-Org immer ✗, auch mit allen Rechten), danach
 * Eigentum/Zuweisung oder diary.viewAny + aktionsspezifisches Recht
 * (order.accept/work/complete/handover/cancel, order.markInvoiced).
 */
final class DiaryEntryPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private DiaryEntryPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new DiaryEntryPolicy;
    }

    private function entry(?User $owner = null, ?User $assignee = null, ?int $orgId = null): DiaryEntry {
        $entry = new DiaryEntry;
        $entry->organization_id = $orgId ?? $this->organization->id;
        $entry->user_id = $owner?->id;
        $entry->assigned_user_id = $assignee?->id;

        return $entry;
    }

    public function test_owner_may_view_edit_and_work_own_entry(): void {
        $owner = $this->actorIn($this->organization);
        $entry = $this->entry($owner);

        $this->assertTrue($this->policy->view($owner, $entry));
        $this->assertTrue($this->policy->update($owner, $entry));
        $this->assertTrue($this->policy->delete($owner, $entry));
        $this->assertTrue($this->policy->archive($owner, $entry));
        $this->assertTrue($this->policy->accept($owner, $entry));
        $this->assertTrue($this->policy->start($owner, $entry));
        $this->assertTrue($this->policy->complete($owner, $entry));
        $this->assertTrue($this->policy->cancel($owner, $entry));
    }

    public function test_assignee_may_work_but_not_edit_foreign_entry(): void {
        $owner = $this->actorIn($this->organization);
        $assignee = $this->actorIn($this->organization);
        $entry = $this->entry($owner, $assignee);

        $this->assertTrue($this->policy->accept($assignee, $entry));
        $this->assertTrue($this->policy->start($assignee, $entry));
        $this->assertTrue($this->policy->pause($assignee, $entry));
        $this->assertTrue($this->policy->resume($assignee, $entry));
        $this->assertTrue($this->policy->complete($assignee, $entry));
        $this->assertTrue($this->policy->handover($assignee, $entry));
        // Bearbeiten/Löschen verlangt Eigentum oder diary.viewAny+diary.update/delete.
        $this->assertFalse($this->policy->view($assignee, $entry));
        $this->assertFalse($this->policy->update($assignee, $entry));
        $this->assertFalse($this->policy->delete($assignee, $entry));
    }

    public function test_dispatcher_needs_view_any_plus_action_permission(): void {
        $owner = $this->actorIn($this->organization);
        $entry = $this->entry($owner);

        $dispatcher = $this->actorIn($this->organization, [P::DiaryViewAny, P::DiaryUpdate, P::OrderCancel]);
        $this->assertTrue($this->policy->view($dispatcher, $entry));
        $this->assertTrue($this->policy->update($dispatcher, $entry));
        $this->assertTrue($this->policy->archive($dispatcher, $entry));
        $this->assertTrue($this->policy->cancel($dispatcher, $entry));
        $this->assertFalse($this->policy->delete($dispatcher, $entry), 'Löschen verlangt diary.delete.');
        $this->assertFalse($this->policy->complete($dispatcher, $entry), 'Abschließen verlangt order.complete.');

        $onlyAction = $this->actorIn($this->organization, [P::OrderCancel]);
        $this->assertFalse($this->policy->cancel($onlyAction, $entry), 'Aktionsrecht ohne diary.viewAny genügt nicht.');
    }

    public function test_mark_invoiced_is_pure_permission_gate(): void {
        $owner = $this->actorIn($this->organization);
        $entry = $this->entry($owner);

        $biller = $this->actorIn($this->organization, [P::OrderMarkInvoiced]);
        $this->assertTrue($this->policy->markInvoiced($biller, $entry));
        $this->assertFalse($this->policy->markInvoiced($owner, $entry), 'Selbst der Eigentümer braucht order.markInvoiced.');
    }

    public function test_foreign_org_entry_is_denied_even_with_all_permissions(): void {
        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, [P::DiaryViewAny, P::DiaryUpdate, P::DiaryDelete, P::OrderAccept, P::OrderWork, P::OrderComplete, P::OrderHandover, P::OrderCancel, P::OrderMarkInvoiced]);
        $owner = $this->actorIn($this->organization);
        $entry = $this->entry($owner); // Primär-Org

        $this->actAsTeam($foreignOrg);
        $this->assertFalse($this->policy->view($attacker, $entry));
        $this->assertFalse($this->policy->update($attacker, $entry));
        $this->assertFalse($this->policy->delete($attacker, $entry));
        $this->assertFalse($this->policy->accept($attacker, $entry));
        $this->assertFalse($this->policy->complete($attacker, $entry));
        $this->assertFalse($this->policy->markInvoiced($attacker, $entry));
        $this->assertFalse($this->policy->cancel($attacker, $entry));
    }
}

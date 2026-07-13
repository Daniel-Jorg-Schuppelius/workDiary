<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\{Organization, TimeEntry, User};
use App\Policies\TimeEntryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Zeiteinträge: view für den Eigentümer oder (same-org) die Abrechnung;
 * update/delete NUR für den Eigentümer und nur solange der Eintrag nicht
 * hart gesperrt ist (exportiert / Timesheet signiert-gesperrt —
 * TimeEntryEditPolicy-Service).
 */
final class TimeEntryPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private TimeEntryPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new TimeEntryPolicy;
    }

    private function entry(User $owner, bool $exported = false, ?int $orgId = null): TimeEntry {
        $entry = new TimeEntry;
        $entry->organization_id = $orgId ?? $this->organization->id;
        $entry->user_id = $owner->id;
        $entry->exported = $exported;

        return $entry;
    }

    public function test_owner_views_and_edits_unlocked_entry(): void {
        $owner = $this->actorIn($this->organization);
        $entry = $this->entry($owner);

        $this->assertTrue($this->policy->viewAny($owner));
        $this->assertTrue($this->policy->create($owner));
        $this->assertTrue($this->policy->view($owner, $entry));
        $this->assertTrue($this->policy->update($owner, $entry));
        $this->assertTrue($this->policy->delete($owner, $entry));
    }

    public function test_exported_entries_are_hard_locked_even_for_owner(): void {
        $owner = $this->actorIn($this->organization);
        $exported = $this->entry($owner, true);

        $this->assertFalse($this->policy->update($owner, $exported), 'Exportierte Einträge sind festgeschrieben.');
        $this->assertFalse($this->policy->delete($owner, $exported));
        $this->assertTrue($this->policy->view($owner, $exported), 'Lesen bleibt erlaubt.');
    }

    public function test_billing_views_same_org_but_never_edits(): void {
        $owner = $this->actorIn($this->organization);
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $entry = $this->entry($owner);

        $this->assertTrue($this->policy->view($accountant, $entry));
        $this->assertFalse($this->policy->update($accountant, $entry), 'Fremde Einträge sind auch für Abrechnung unveränderlich.');
        $this->assertFalse($this->policy->delete($accountant, $entry));
    }

    public function test_foreign_org_billing_is_denied(): void {
        $owner = $this->actorIn($this->organization);
        $entry = $this->entry($owner);

        $foreignOrg = Organization::factory()->create();
        $foreignAccountant = User::factory()->buchhaltung()->create(['organization_id' => $foreignOrg->id]);
        $this->actAsTeam($foreignOrg);

        $this->assertFalse($this->policy->view($foreignAccountant, $entry), 'Abrechnungsrecht endet an der Org-Grenze.');
        $this->assertFalse($this->policy->update($foreignAccountant, $entry));
    }

    public function test_regular_colleague_cannot_view_foreign_entry(): void {
        $owner = $this->actorIn($this->organization);
        $colleague = $this->actorIn($this->organization);
        $entry = $this->entry($owner);

        $this->assertFalse($this->policy->view($colleague, $entry));
        $this->assertFalse($this->policy->update($colleague, $entry));
    }
}

<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssuePermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\OpenIssue;

use App\Enums\User\{Permission, UserRole};
use App\Models\Diary\{DiaryEntry, OpenIssue};
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Offene Punkte prüfen ihre Rechte (lk-06): anlegen mit openIssue.create,
 * eigene und zugewiesene ansehen und bearbeiten mit openIssue.view/update —
 * die hat jedes interne Profil, weil Punkte jedem zugewiesen werden.
 */
class OpenIssuePermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_creating_open_issues_follows_open_issue_create(): void {
        $this->assertTrue(Gate::forUser($this->userWithRole(UserRole::User->value))->allows('create', OpenIssue::class));
        $this->assertFalse(Gate::forUser($this->userWithRole(UserRole::Buchhaltung->value))->allows('create', OpenIssue::class));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('create', OpenIssue::class));
    }

    public function test_assigned_issues_need_view_and_update_rights(): void {
        $accounting = $this->userWithRole(UserRole::Buchhaltung->value);
        $bare = $this->actorIn($this->organization);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);

        $forAccounting = $this->issueAssignedTo($accounting);
        $this->assertTrue(Gate::forUser($accounting)->allows('view', $forAccounting));
        $this->assertTrue(Gate::forUser($accounting)->allows('update', $forAccounting));

        $forBare = $this->issueAssignedTo($bare);
        $this->assertFalse(Gate::forUser($bare)->allows('view', $forBare));
        $this->assertFalse(Gate::forUser($bare)->allows('update', $forBare));

        $this->assertTrue(Gate::forUser($lead)->allows('update', $forBare), 'openIssue.assign');
        $this->assertTrue(Gate::forUser($this->actorIn($this->organization, [Permission::OpenIssueViewAny]))->allows('view', $forBare));
    }

    private function issueAssignedTo(User $assignee): OpenIssue {
        $creator = $this->orgAdmin();
        $entry = DiaryEntry::factory()->for($creator)->create(['organization_id' => $this->organization->id]);
        $issue = OpenIssue::factory()->create([
            'organization_id' => $this->organization->id,
            'subject_id' => $entry->id,
            'assignee_user_id' => $assignee->id,
            'created_by_user_id' => $creator->id,
        ]);
        $this->actAsTeam($this->organization);

        return $issue;
    }
}

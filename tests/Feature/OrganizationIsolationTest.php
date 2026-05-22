<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationIsolationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\DiaryEntry;
use App\Models\Event;
use App\Models\EventReminder;
use App\Models\FlexBalance;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Regression-Suite gegen Cross-Org-Leaks: jede tenant-scoped Tabelle
 * darf von einem in Org A eingeloggten Benutzer keinen Datensatz aus
 * Org B liefern – weder direkt per ID, noch über signierte Download-
 * URLs, noch über Eloquent-Default-Queries.
 *
 * Wenn dieser Test umkippt, ist das ein Sicherheitsproblem und KEIN
 * gewöhnlicher Test-Fail – Ursache ist fast immer ein fehlender Trait
 * oder eine Query, die `withoutGlobalScopes()` ohne Org-Filter mitnimmt.
 */
class OrganizationIsolationTest extends TestCase {
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $userA;

    private User $userB;

    protected function setUp(): void {
        parent::setUp();
        // Reihenfolge wichtig: erst Permissions/Rollen anlegen, dann Orgs.
        // Andernfalls läuft der OrganizationObserver in PermissionDoesNotExist,
        // weil er beim ersten Org-Create bereits Default-Rollen verteilen will.
        $this->seed(PermissionsSeeder::class);

        $this->orgA = Organization::factory()->create(['slug' => 'iso-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'iso-b']);

        $this->userA = User::factory()->user()->create(['organization_id' => $this->orgA->id]);
        $this->userB = User::factory()->user()->create(['organization_id' => $this->orgB->id]);
    }

    public function test_signed_attachment_download_for_other_org_is_blocked(): void {
        // Datensatz in Org B: DiaryEntry + Attachment.
        $entryB = $this->withOrg($this->orgB, fn() => DiaryEntry::factory()->for($this->userB)->create());
        $attachmentB = $this->withOrg($this->orgB, fn() => Attachment::factory()->for($this->userB, 'uploader')->create([
            'attachable_type' => DiaryEntry::class,
            'attachable_id' => $entryB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $attachmentB->organization_id, 'Backfill via Trait setzt organization_id');

        // Selbst eine korrekt signierte URL darf für User aus Org A nicht greifen.
        $url = URL::temporarySignedRoute(
            'attachments.download',
            now()->addMinutes(5),
            ['attachment' => $attachmentB->id],
        );

        $this->actingAs($this->userA);
        app()->instance('currentOrganization', $this->orgA);

        $response = $this->get($url);
        $this->assertContains($response->status(), [403, 404], 'Cross-Org-Download muss 403/404 liefern');
    }

    public function test_direct_eloquent_lookup_does_not_leak_cross_org(): void {
        // Datensätze in Org B.
        $customerB = $this->withOrg($this->orgB, fn() => Customer::factory()->create());
        $entryB = $this->withOrg($this->orgB, fn() => DiaryEntry::factory()->for($this->userB)->create());

        // Im Kontext von Org A: weder ::find noch ::all dürfen Org-B-Daten liefern.
        app()->instance('currentOrganization', $this->orgA);

        $this->assertNull(Customer::find($customerB->id));
        $this->assertNull(DiaryEntry::find($entryB->id));

        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(0, DiaryEntry::query()->count());
    }

    public function test_child_tables_are_scoped_to_organization(): void {
        // Comment, EventReminder, FlexBalance — alle bekommen organization_id
        // automatisch über den Trait, sobald currentOrganization gebunden ist.
        $entryB = $this->withOrg($this->orgB, fn() => DiaryEntry::factory()->for($this->userB)->create());
        $eventB = $this->withOrg($this->orgB, fn() => Event::factory()->create());

        $commentB = $this->withOrg($this->orgB, function () use ($entryB) {
            return Comment::create([
                'commentable_type' => DiaryEntry::class,
                'commentable_id' => $entryB->id,
                'user_id' => $this->userB->id,
                'body' => 'secret',
            ]);
        });

        $reminderB = $this->withOrg($this->orgB, function () use ($eventB) {
            return EventReminder::create([
                'event_id' => $eventB->id,
                'user_id' => $this->userB->id,
                'remind_at' => now()->addHour(),
                'channel' => 'mail',
            ]);
        });

        $flexB = $this->withOrg($this->orgB, function () {
            return FlexBalance::create([
                'user_id' => $this->userB->id,
                'year' => 2026,
                'month' => 5,
                'target_minutes' => 0,
                'actual_minutes' => 0,
                'balance_minutes' => 0,
                'carry_over_minutes' => 0,
                'locked' => false,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $commentB->organization_id);
        $this->assertSame((int) $this->orgB->id, (int) $reminderB->organization_id);
        $this->assertSame((int) $this->orgB->id, (int) $flexB->organization_id);

        app()->instance('currentOrganization', $this->orgA);

        $this->assertNull(Comment::find($commentB->id));
        $this->assertNull(EventReminder::find($reminderB->id));
        $this->assertNull(FlexBalance::find($flexB->id));
    }

    public function test_customer_show_route_is_blocked_for_other_org(): void {
        $customerB = $this->withOrg($this->orgB, fn() => Customer::factory()->create());

        app()->instance('currentOrganization', $this->orgA);
        $this->actingAs($this->userA);

        $response = $this->get(route('customers.show', $customerB));
        $this->assertContains($response->status(), [403, 404], 'Cross-Org-Customer muss verborgen sein');
    }

    public function test_project_show_route_is_blocked_for_other_org(): void {
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->create());

        app()->instance('currentOrganization', $this->orgA);
        $this->actingAs($this->userA);

        $response = $this->get(route('projects.show', $projectB));
        $this->assertContains($response->status(), [403, 404], 'Cross-Org-Project muss verborgen sein');
    }

    /**
     * Führt $callback aus, während $org als currentOrganization gebunden ist.
     * Stellt nach Ablauf den vorherigen Zustand wieder her.
     *
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withOrg(Organization $org, \Closure $callback): mixed {
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $org);
        try {
            return $callback();
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }
}

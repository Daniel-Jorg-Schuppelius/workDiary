<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentTenantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Models\Attachment;
use App\Models\DiaryEntry;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Verteidigung gegen Cross-Org-Attachment-Zugriffe in der Tiefe:
 * - Global Scope filtert Direkt-Lookups.
 * - Massenoperationen treffen keine Fremdmandanten-Datensätze.
 * - Signed-URL-Download wird zusätzlich durch die AttachmentPolicy
 *   abgefangen (siehe AttachmentController::download()).
 *
 * Hintergrund/Referenz: docs/security/tenant-audit-2026.md
 * (Abschnitt „Attachment-/Storage-Pfade").
 */
class AttachmentTenantTest extends TestCase {
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $userA;

    private User $userB;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $this->orgA = Organization::factory()->create(['slug' => 'att-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'att-b']);

        $this->userA = User::factory()->user()->create(['organization_id' => $this->orgA->id]);
        $this->userB = User::factory()->user()->create(['organization_id' => $this->orgB->id]);
    }

    public function test_attachment_direct_lookup_is_blocked_cross_org(): void {
        $attachmentB = $this->makeOrgBAttachment();

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(Attachment::find($attachmentB->id));
        $this->assertSame(0, Attachment::query()->count());
    }

    public function test_attachment_mass_update_does_not_touch_other_org(): void {
        $attachmentB = $this->makeOrgBAttachment();
        $originalName = $attachmentB->original_name;

        app()->instance('currentOrganization', $this->orgA);
        $affected = Attachment::query()->where('id', $attachmentB->id)->update(['original_name' => 'hijacked.pdf']);
        $this->assertSame(0, $affected);

        $reloaded = $this->withOrg($this->orgB, fn() => Attachment::find($attachmentB->id));
        $this->assertNotNull($reloaded);
        $this->assertSame($originalName, $reloaded->original_name);
    }

    public function test_signed_download_url_from_other_org_is_rejected(): void {
        $attachmentB = $this->makeOrgBAttachment();

        $url = URL::temporarySignedRoute(
            'attachments.download',
            now()->addMinutes(5),
            ['attachment' => $attachmentB->id],
        );

        $this->actingAs($this->userA);
        app()->instance('currentOrganization', $this->orgA);

        $response = $this->get($url);
        $this->assertContains(
            $response->status(),
            [403, 404],
            'Cross-Org-Download muss durch Scope/Policy auf 403/404 enden',
        );
    }

    public function test_unsigned_download_url_is_rejected_even_for_own_org(): void {
        $attachmentA = $this->withOrg($this->orgA, fn() => Attachment::factory()->for($this->userA, 'uploader')->create([
            'attachable_type' => DiaryEntry::class,
            'attachable_id' => $this->withOrg($this->orgA, fn() => DiaryEntry::factory()->for($this->userA)->create())->id,
        ]));

        $this->actingAs($this->userA);
        app()->instance('currentOrganization', $this->orgA);

        $response = $this->get(route('attachments.download', $attachmentA));
        $this->assertSame(403, $response->status(), 'Download ohne gültige Signatur muss 403 liefern');
    }

    private function makeOrgBAttachment(): Attachment {
        $entryB = $this->withOrg($this->orgB, fn() => DiaryEntry::factory()->for($this->userB)->create());

        return $this->withOrg($this->orgB, fn() => Attachment::factory()->for($this->userB, 'uploader')->create([
            'attachable_type' => DiaryEntry::class,
            'attachable_id' => $entryB->id,
        ]));
    }

    /**
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

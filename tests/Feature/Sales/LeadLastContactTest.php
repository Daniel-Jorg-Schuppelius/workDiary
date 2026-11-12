<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadLastContactTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Platform\User;
use App\Models\Sales\Lead;
use App\Services\Communication\CommunicationNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Letzter Kontakt eines Leads (MVP-1612): Notiz, inhaltliche Bearbeitung und Angebot ziehen ihn nach. */
final class LeadLastContactTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function lead(): Lead {
        return Lead::query()->create([
            'organization_id' => $this->organization->id,
            'company' => 'Beispiel Werkstatt',
            'source' => 'web',
            'status' => 'new',
            'last_contact_at' => Carbon::now()->subMonths(5),
        ]);
    }

    public function test_communication_note_moves_the_last_contact(): void {
        $lead = $this->lead();

        app(CommunicationNoteService::class)->create($lead, $this->admin, [
            'type' => 'call', 'direction' => 'outbound', 'subject' => 'Rückruf', 'body' => 'Termin abgestimmt',
            'occurred_at' => Carbon::now()->subDay()->toDateTimeString(),
        ]);

        $this->assertTrue($lead->refresh()->last_contact_at->isSameDay(Carbon::now()->subDay()));

        // Eine nachgetragene ältere Notiz verkürzt die Frist nicht.
        app(CommunicationNoteService::class)->create($lead, $this->admin, [
            'type' => 'call', 'direction' => 'outbound', 'subject' => 'Alt', 'body' => 'Nachtrag',
            'occurred_at' => Carbon::now()->subMonths(3)->toDateTimeString(),
        ]);
        $this->assertTrue($lead->refresh()->last_contact_at->isSameDay(Carbon::now()->subDay()));
    }

    public function test_content_edit_counts_as_contact_but_reassignment_does_not(): void {
        $lead = $this->lead();
        $before = $lead->last_contact_at;
        $colleague = User::factory()->create(['organization_id' => $this->organization->id]);
        $form = ['company' => 'Beispiel Werkstatt', 'source' => 'web'];

        $this->actingAs($this->admin)->put(route('leads.update', $lead), $form + ['responsible_user' => $colleague->sqid])->assertRedirect();
        $this->assertSame($colleague->id, $lead->refresh()->responsible_user_id);
        $this->assertTrue($lead->last_contact_at->equalTo($before), 'Nur die Zuständigkeit zu wechseln ist kein Kontakt.');

        $this->actingAs($this->admin)->put(route('leads.update', $lead), $form + ['responsible_user' => $colleague->sqid, 'interest' => 'Wartungsvertrag'])->assertRedirect();
        $this->assertTrue($lead->refresh()->last_contact_at->isToday());
    }
}

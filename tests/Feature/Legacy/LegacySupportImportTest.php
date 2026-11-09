<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Legacy;

use App\Enums\ServiceTicket\{ServiceTicketSource, ServiceTicketStatus, TicketMessageKind};
use App\Legacy\Models\LegacySupportOrder;
use App\Legacy\Services\LegacySupportImportService;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\{ServiceTicket, ServiceTicketMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Config, DB};
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

/** Übernahme des Support-Portals ins neue System: Kunden und Servicetickets, idempotent. */
class LegacySupportImportTest extends TestCase {
    use RefreshDatabase;
    use UsesLegacySqlite;

    private Organization $organization;

    protected function setUp(): void {
        parent::setUp();
        $this->useLegacySqlite();
        Config::set('app.display_timezone', 'Europe/Berlin');

        $this->organization = Organization::factory()->create();

        $legacy = DB::connection('legacy');
        $legacy->table('user')->insert(['id' => 5, 'uname' => 'Monteur Eins', 'userpw' => 'x', 'email' => null]);
        $legacy->table('kp_user')->insert([
            ['id' => 2, 'kunde' => 'self', 'usern' => 'buero', 'passw' => 'geheim'],
            ['id' => 10, 'kunde' => 'Beispiel Haustechnik', 'usern' => 'beispiel', 'passw' => 'kalt1'],
            ['id' => 11, 'kunde' => 'Testkunde Nord', 'usern' => 'nord', 'passw' => 'nord1'],
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function order(string $table, array $overrides): int {
        $row = [
            'kunde' => 10,
            'datum' => '2026-11-02 08:15:00',
            'kname' => 'Frau Beispiel',
            'telefon' => '0123 4567',
            'objekt' => 'Kühlraum Halle 2',
            'auftrag' => 'Verdampfer vereist',
            'termin' => LegacySupportOrder::NO_APPOINTMENT,
            'wer' => '',
            'antwort' => '',
            'intern' => '',
            'dauer' => '',
            'status' => LegacySupportOrder::STATUS_NEW,
            ...$overrides,
        ];

        if ($table === 'kp_archiv') {
            DB::connection('legacy')->table('kp_archiv')->insert($row);

            return (int) $row['id'];
        }

        return (int) DB::connection('legacy')->table('kp_auftrag')->insertGetId($row);
    }

    private function ticket(int $legacyId): ServiceTicket {
        return ServiceTicket::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('ticket_no', LegacySupportImportService::TICKET_PREFIX . $legacyId)
            ->sole();
    }

    private function runImport(): void {
        $this->artisan('legacy:import', ['--support' => true, '--organization' => (string) $this->organization->id])->assertSuccessful();
    }

    public function test_customers_are_matched_or_created_and_orders_become_tickets(): void {
        $existing = Customer::query()->create(['organization_id' => $this->organization->id, 'name' => 'beispiel  haustechnik']);
        $mechanic = User::factory()->create(['organization_id' => $this->organization->id, 'legacy_user_id' => 5]);

        $scheduled = $this->order('kp_auftrag', [
            'status' => LegacySupportOrder::STATUS_SCHEDULED,
            'termin' => '2026-11-12 08:00:00',
            'wer' => 'Monteur Eins',
            'antwort' => 'Wir kommen am Donnerstag.',
            'intern' => 'Ersatzteil mitnehmen',
            'dauer' => '2 h',
        ]);
        $office = $this->order('kp_auftrag', ['kunde' => 11, 'kname' => 'bueradmin', 'objekt' => '']);
        $this->order('kp_archiv', ['id' => 500, 'kunde' => 99, 'status' => LegacySupportOrder::STATUS_ARCHIVED, 'datum' => '2015-03-02 10:00:00']);

        $this->runImport();

        $customers = Customer::query()->withoutGlobalScopes()->where('organization_id', $this->organization->id)->get();
        $this->assertCount(2, $customers, 'Kunde 10 wird dem vorhandenen Kunden zugeordnet, Kunde 11 neu angelegt; Bürokonten nie.');
        $this->assertSame(1, ExternalReference::query()->withoutGlobalScopes()
            ->where('plugin_id', LegacySupportImportService::ORIGIN)
            ->where('external_type', LegacySupportImportService::TYPE_CUSTOMER)
            ->where('external_id', '10')
            ->where('referenceable_id', $existing->id)
            ->count());

        $ticket = $this->ticket($scheduled);
        $this->assertSame(ServiceTicketStatus::Scheduled, $ticket->status);
        $this->assertSame(ServiceTicketSource::CustomerPortal, $ticket->source);
        $this->assertSame($existing->id, $ticket->customer_id);
        $this->assertSame($mechanic->id, $ticket->assigned_to_user_id);
        $this->assertSame('Kühlraum Halle 2', $ticket->title);
        $this->assertStringContainsString('Frau Beispiel', (string) $ticket->description);
        // Ortszeit des Altsystems (Winterzeit, UTC+1) → UTC.
        $this->assertSame('2026-11-12 07:00:00', $ticket->scheduled_for?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 07:15:00', $ticket->reported_at?->format('Y-m-d H:i:s'));

        $messages = ServiceTicketMessage::query()->withoutGlobalScopes()->where('service_ticket_id', $ticket->id)->get()->keyBy(fn($m) => $m->kind->value);
        $this->assertSame('Wir kommen am Donnerstag.', $messages[TicketMessageKind::PublicReply->value]->body);
        $this->assertStringContainsString('Ersatzteil mitnehmen', $messages[TicketMessageKind::InternalNote->value]->body);
        $this->assertStringContainsString('2 h', $messages[TicketMessageKind::InternalNote->value]->body);

        $officeTicket = $this->ticket($office);
        $this->assertSame(ServiceTicketSource::Manual, $officeTicket->source);
        $this->assertSame(ServiceTicketStatus::Reported, $officeTicket->status);
        $this->assertSame('Verdampfer vereist', $officeTicket->title);

        $archived = $this->ticket(500);
        $this->assertSame(ServiceTicketStatus::Closed, $archived->status);
        $this->assertNull($archived->customer_id);
        $this->assertNotNull($archived->closed_at);
    }

    public function test_second_run_updates_instead_of_duplicating(): void {
        $id = $this->order('kp_auftrag', ['antwort' => 'Erste Antwort']);
        $this->runImport();

        DB::connection('legacy')->table('kp_auftrag')->where('id', $id)->update([
            'status' => LegacySupportOrder::STATUS_DONE,
            'antwort' => 'Erledigt am Montag.',
        ]);
        $this->runImport();

        $this->assertSame(1, ServiceTicket::query()->withoutGlobalScopes()->where('organization_id', $this->organization->id)->count());
        $this->assertSame(2, Customer::query()->withoutGlobalScopes()->where('organization_id', $this->organization->id)->count());
        $ticket = $this->ticket($id);
        $this->assertSame(ServiceTicketStatus::Done, $ticket->status);
        $replies = ServiceTicketMessage::query()->withoutGlobalScopes()->where('service_ticket_id', $ticket->id)->get();
        $this->assertCount(1, $replies);
        $this->assertSame('Erledigt am Montag.', $replies->first()->body);
    }

    public function test_support_import_needs_a_target_organization(): void {
        $this->order('kp_auftrag', []);

        $this->artisan('legacy:import', ['--support' => true])->assertFailed();
        $this->artisan('legacy:import', ['--support' => true, '--organization' => 'gibt-es-nicht'])->assertFailed();

        $this->assertSame(0, ServiceTicket::query()->withoutGlobalScopes()->count());
    }

    public function test_platform_operator_runs_the_support_import_from_the_dashboard(): void {
        $this->order('kp_auftrag', []);
        $operator = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($operator)->get(route('admin.legacy-migration.index'))
            ->assertOk()
            ->assertSee(__('legacy_support.import.migration_label'));

        $this->actingAs($operator)
            ->from(route('admin.legacy-migration.index'))
            ->post(route('admin.legacy-migration.run'), ['type' => 'support'])
            ->assertSessionHasErrors('organization');

        $this->actingAs($operator)
            ->from(route('admin.legacy-migration.index'))
            ->post(route('admin.legacy-migration.run'), ['type' => 'support', 'organization' => $this->organization->sqid])
            ->assertRedirect(route('admin.legacy-migration.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, ServiceTicket::query()->withoutGlobalScopes()->where('organization_id', $this->organization->id)->count());
    }
}

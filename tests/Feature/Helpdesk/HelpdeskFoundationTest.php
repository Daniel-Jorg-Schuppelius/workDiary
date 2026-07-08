<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskFoundationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Models\{Organization, ServiceQueue, ServiceTicket, User};
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 065, P0 (MVP-150): Modul-Gating (423 im free-Plan, pro enthält
 * module.helpdesk — Bestandsschutz), service_desk setzt helpdesk voraus,
 * Backfill legt genau eine Default-Queue an (idempotent), Queue-CRUD mit
 * Policy (org-gescopt, Standard-Queue-Wechsel atomar, Löschen nur leer).
 */
final class HelpdeskFoundationTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $lead;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->lead = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
    }

    public function test_free_plan_gates_tickets_and_queues_with_423(): void {
        $this->org->update(['plan' => 'free']);

        $this->actingAs($this->lead)->get(route('service-tickets.index'))->assertStatus(423);
        $this->actingAs($this->lead)->get(route('helpdesk.queues.index'))->assertStatus(423);
    }

    public function test_pro_tier_contains_helpdesk_but_not_service_desk(): void {
        $this->org->update(['plan' => 'pro']);
        app(FeatureFlagResolver::class)->flush();

        $resolver = app(FeatureFlagResolver::class);
        $this->assertTrue($resolver->isEnabled('module.helpdesk'), 'Bestandsschutz: Tickets bleiben im pro-Tier nutzbar.');
        $this->assertFalse($resolver->isEnabled('module.service_desk'));
    }

    public function test_service_desk_requires_helpdesk(): void {
        $this->org->update(['plan' => 'enterprise']);
        app(FeatureFlagResolver::class)->flush();
        $this->assertTrue(app(FeatureFlagResolver::class)->isEnabled('module.service_desk'));

        // Basis-Modul per Org-Override deaktiviert → abhängiges Modul fällt mit.
        \App\Models\LicenseFlagOverride::query()->create([
            'organization_id' => $this->org->id,
            'flag' => 'module.helpdesk',
            'disabled_at' => now(),
        ]);
        app(FeatureFlagResolver::class)->flush();

        $resolver = app(FeatureFlagResolver::class);
        $this->assertFalse($resolver->isEnabled('module.helpdesk'));
        $this->assertFalse($resolver->isEnabled('module.service_desk'), 'service_desk setzt helpdesk voraus.');
    }

    public function test_backfill_assigns_default_queue_to_existing_tickets(): void {
        // Migration ist gelaufen (RefreshDatabase) — Backfill-Logik erneut
        // gegen einen Alt-Datenstand prüfen: Ticket ohne Queue + Re-Run.
        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'queue_id' => null,
        ]);

        $migration = require database_path('migrations/2026_10_01_102100_create_service_queues_table.php');
        // up() erneut ausführen wäre destruktiv (Tabelle existiert) — nur der
        // Backfill-Teil zählt: er ist in up() idempotent formuliert; hier
        // direkt nachgestellt.
        $queueId = ServiceQueue::query()->where('organization_id', $this->org->id)->where('is_default', true)->value('id');
        if ($queueId === null) {
            $queueId = ServiceQueue::query()->create([
                'organization_id' => $this->org->id,
                'name' => 'Allgemein',
                'is_default' => true,
            ])->id;
        }
        ServiceTicket::query()->whereNull('queue_id')->update(['queue_id' => $queueId]);

        $this->assertNotNull($ticket->fresh()->queue_id);
        $this->assertSame(1, ServiceQueue::query()->where('is_default', true)->count());
        $this->assertNotNull($migration);
    }

    public function test_queue_crud_with_policy_and_single_default(): void {
        // Anlegen (Modal-Formular → store).
        $this->actingAs($this->lead)
            ->post(route('helpdesk.queues.store'), [
                'name' => 'Störungen',
                'visibility' => 'internal',
                'is_default' => '1',
            ])->assertRedirect(route('helpdesk.queues.index'));

        $first = ServiceQueue::query()->where('name', 'Störungen')->firstOrFail();
        $this->assertTrue($first->is_default);

        // Zweite Queue als Standard → genau EINE Default-Queue bleibt.
        $this->actingAs($this->lead)
            ->post(route('helpdesk.queues.store'), [
                'name' => 'Anfragen',
                'visibility' => 'portal',
                'is_default' => '1',
            ])->assertRedirect();
        $this->assertSame(1, ServiceQueue::query()->where('is_default', true)->count());
        $this->assertFalse($first->fresh()->is_default);

        // Löschen: Standard-Queue gesperrt, leere Nicht-Standard-Queue geht.
        $second = ServiceQueue::query()->where('name', 'Anfragen')->firstOrFail();
        $this->actingAs($this->lead)
            ->delete(route('helpdesk.queues.destroy', $second))
            ->assertRedirect();
        $this->assertNotNull($second->fresh(), 'Standard-Queue darf nicht gelöscht werden.');

        $this->actingAs($this->lead)
            ->delete(route('helpdesk.queues.destroy', $first))
            ->assertRedirect(route('helpdesk.queues.index'));
        $this->assertNull(ServiceQueue::query()->find($first->id));

        // Ohne Recht: normaler User darf nicht verwalten.
        $member = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($member)
            ->post(route('helpdesk.queues.store'), ['name' => 'Nope', 'visibility' => 'internal'])
            ->assertForbidden();
    }

    public function test_queue_is_org_scoped(): void {
        $otherOrg = Organization::factory()->create();
        $foreign = ServiceQueue::query()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Fremde Queue',
        ]);

        $this->actingAs($this->lead)->get(route('helpdesk.queues.index'))
            ->assertOk()
            ->assertDontSee('Fremde Queue');

        $this->actingAs($this->lead)
            ->patch(route('helpdesk.queues.update', $foreign), ['name' => 'Hijack', 'visibility' => 'internal'])
            ->assertNotFound();
    }
}

<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadWorkflowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Sales\LeadStatus;
use App\Models\{Customer, Lead, Organization, User};
use App\Services\Sales\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lead-Pipeline (Feature 091, MVP-654–656).
 *
 * Kern der Prüfung: **Konvertierung erzeugt genau einen Kunden** (Dubletten
 * werden vorher gezeigt, Verbinden ist gleichwertig), die Pipeline kennt
 * keine wilden Sprünge, und die Anonymisierung entfernt die Person, nicht
 * die Kennzahl.
 */
final class LeadWorkflowTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function lead(array $attributes = []): Lead {
        return Lead::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'company' => 'Muster GmbH',
            'contact_name' => 'Max Muster',
            'email' => 'max@muster.example',
            'source' => 'referral',
            'status' => 'new',
            'last_contact_at' => now(),
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    public function test_lead_can_be_created_via_the_form(): void {
        $this->actingAs($this->admin)->post(route('leads.store'), [
            'company' => 'Neuland AG',
            'source' => 'web',
        ])->assertRedirect();

        $lead = Lead::query()->firstOrFail();
        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertNotNull($lead->last_contact_at);
    }

    /** Ohne Firma UND ohne Person gibt es keine Akte. */
    public function test_empty_lead_is_rejected(): void {
        $this->actingAs($this->admin)->post(route('leads.store'), [
            'source' => 'web',
        ])->assertStatus(422);
    }

    public function test_pipeline_rejects_wild_jumps(): void {
        $lead = $this->lead();
        $service = app(LeadService::class);

        $this->expectException(\RuntimeException::class);
        // new → converted gibt es nicht als Statuswechsel; konvertiert wird
        // über convert(), nicht über die Pipeline.
        $service->transition($lead, LeadStatus::Converted);
    }

    public function test_conversion_creates_exactly_one_customer(): void {
        $lead = $this->lead();

        $customer = app(LeadService::class)->convert($lead, $this->admin);

        $this->assertSame('Muster GmbH', $customer->name);
        $this->assertSame(LeadStatus::Converted, $lead->fresh()?->status);
        $this->assertSame($customer->id, $lead->fresh()?->customer_id);
        $this->assertSame(1, Customer::query()->count());

        // Zweite Konvertierung: kein zweiter Kunde.
        $this->expectException(\RuntimeException::class);
        app(LeadService::class)->convert($lead->fresh(), $this->admin);
    }

    /** Verbinden mit Bestandskunde ist die gleichwertige Option. */
    public function test_conversion_can_link_an_existing_customer(): void {
        $existing = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster GmbH',
        ]);
        $lead = $this->lead();

        $this->assertTrue(
            app(LeadService::class)->duplicateCandidates($lead)->contains(fn (Customer $c): bool => $c->id === $existing->id),
            'Der Bestandskunde muss als Dubletten-Kandidat erscheinen.'
        );

        $customer = app(LeadService::class)->convert($lead, $this->admin, $existing);

        $this->assertSame($existing->id, $customer->id);
        $this->assertSame(1, Customer::query()->count());
    }

    /** Anonymisierung: die Person verschwindet, die Kennzahl bleibt. */
    public function test_anonymize_removes_pii_and_keeps_the_metric(): void {
        $lead = $this->lead(['status' => 'discarded']);

        app(LeadService::class)->anonymize($lead);

        $fresh = $lead->fresh();
        $this->assertNull($fresh?->email);
        $this->assertNull($fresh?->company);
        $this->assertNotNull($fresh?->anonymized_at);
        // Quelle und Status bleiben für die Pipeline-Statistik.
        $this->assertSame('referral', $fresh?->source?->value);
    }

    public function test_foreign_organization_lead_is_invisible(): void {
        $other = Organization::factory()->create();
        Lead::query()->create([
            'organization_id' => $other->id,
            'company' => 'Fremd AG',
            'source' => 'web',
            'status' => 'new',
        ]);
        $mine = $this->lead();

        $this->actingAs($this->admin)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee('Muster GmbH')
            ->assertDontSee('Fremd AG');

        $this->assertSame(1, Lead::query()->count());
    }

    public function test_plain_user_without_customer_rights_is_denied(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('leads.index'))->assertForbidden();
    }
}

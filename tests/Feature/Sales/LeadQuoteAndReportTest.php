<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadQuoteAndReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Sales\{LeadSource, LeadStatus};
use App\Models\Platform\User;
use App\Models\Sales\{Lead, Quote};
use App\Services\Sales\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Lead zu Angebot mit Verlauf (MVP-1295), Lead-Auswertung nach Quelle (MVP-1296). */
final class LeadQuoteAndReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function lead(array $attributes = []): Lead {
        return Lead::query()->create([
            'organization_id' => $this->organization->id, 'company' => 'Muster GmbH', 'contact_first_name' => 'Max', 'contact_last_name' => 'Muster',
            'email' => 'max@muster.example', 'source' => LeadSource::Web->value, 'status' => LeadStatus::New->value, 'created_by' => $this->admin->id,
            ...$attributes,
        ]);
    }

    public function test_quote_from_an_open_lead_converts_it_and_opens_the_dialog(): void {
        $lead = $this->lead();

        $response = $this->actingAs($this->admin)->post(route('leads.quote', $lead))->assertRedirect(route('leads.show', $lead));
        $lead->refresh();
        $this->assertSame(LeadStatus::Converted, $lead->status);
        $this->assertNotNull($lead->customer_id);
        $dialog = (string) session('open_dialog');
        $this->assertStringContainsString('lead=' . $lead->sqid, $dialog);

        $this->actingAs($this->admin)->get($dialog)->assertOk()->assertSee('value="' . $lead->sqid . '"', false);
        $this->actingAs($this->admin)->post(route('quotes.store'), [
            'customer_id' => $lead->customer->sqid,
            'lead_id' => $lead->sqid,
        ])->assertRedirect();

        $quote = Quote::query()->sole();
        $this->assertSame((int) $lead->id, (int) $quote->lead_id);
        $history = collect(app(LeadService::class)->history($lead))->pluck('label');
        $this->assertTrue($history->contains(__('audit-events.lead.quote_created')));
        $this->actingAs($this->admin)->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee($quote->number)
            ->assertSee(__('lead.history.title'));
    }

    public function test_report_counts_conversion_per_source_and_exports(): void {
        $this->lead(['company' => 'A', 'source' => LeadSource::Web->value]);
        $won = $this->lead(['company' => 'B', 'source' => LeadSource::Web->value]);
        $this->lead(['company' => 'C', 'source' => LeadSource::Referral->value, 'status' => LeadStatus::Discarded->value]);
        app(LeadService::class)->convert($won, $this->admin);

        $this->actingAs($this->admin)->get(route('leads.report'))
            ->assertOk()
            ->assertSee(LeadSource::Web->label())
            ->assertSee('50 %')
            ->assertSee('33.3 %');

        $csv = (string) $this->actingAs($this->admin)->get(route('leads.report', ['export' => 'csv']))->assertOk()->getContent();
        $this->assertStringContainsString(LeadSource::Referral->label(), $csv);
        $this->actingAs($this->admin)->get(route('leads.index'))->assertSee(route('leads.report'), false);
    }
}

<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeServiceTicketTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Helpdesk;

use App\Enums\Customer\{IntakeKind, IntakeStatus};
use App\Enums\Fields\FieldType;
use App\Enums\ServiceTicket\{ServiceRequestStatus, ServiceTicketSource};
use App\Models\Customer\{Customer, CustomerIntake};
use App\Models\Form\FormTemplate;
use App\Models\Platform\User;
use App\Models\Procurement\RequestItem;
use App\Models\Sales\{Quote, ServiceOffering};
use App\Models\ServiceTicket\{BusinessService, ServiceQueue, ServiceRequest, ServiceTicket};
use App\Services\Invoicing\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-1077: IT-Anfrage → Rückfrage/Angebot → Annahme → Ticket. Upload-Felder
 * nur im Anfragepfad des Katalogs, kein Service-Request und kein Fulfillment
 * vor der Beauftragung, keine doppelte Anlage; die Kundensicht folgt dem
 * Ticketstatus, die Bestellliste zeigt nicht mehr „erledigt" des Requests.
 */
final class IntakeServiceTicketTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');
        Mail::fake();

        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer, ['intakes', 'tickets']);
        $this->portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Portal', 'visibility' => 'portal']);
        ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Intern', 'is_default' => true]);
    }

    private function catalogItem(): RequestItem {
        $service = BusinessService::query()->firstOrCreate(['organization_id' => $this->organization->id, 'name' => 'Server']);
        $offering = ServiceOffering::query()->create(['organization_id' => $this->organization->id, 'business_service_id' => $service->id, 'name' => 'Serverbetrieb']);
        $template = FormTemplate::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Migration',
            'fields' => [
                ['key' => 'target', 'label' => 'Zielsystem', 'type' => FieldType::Text->value, 'required' => true, 'options' => [], 'help' => null, 'unit' => null],
                ['key' => 'inventory', 'label' => 'Inventarliste', 'type' => FieldType::File->value, 'required' => true, 'options' => [], 'help' => null, 'unit' => null],
            ],
        ]);

        return RequestItem::query()->create([
            'organization_id' => $this->organization->id,
            'service_offering_id' => $offering->id,
            'form_template_id' => $template->id,
            'name' => 'Servermigration',
            'visibility' => ['portal' => true],
            'active' => true,
        ]);
    }

    private function acceptQuoteFor(CustomerIntake $intake): Quote {
        $quotes = app(QuoteService::class);
        $quote = $quotes->create(['customer_id' => $this->customer->id], [['description' => 'Migration', 'quantity' => 1, 'unit_price' => 1500]], $this->admin);
        $quotes->approve($quote, $this->admin);
        $quotes->send($quote->refresh(), $this->admin);
        $this->actingAs($this->admin, 'web')->post(route('customer-intakes.quote.link', $intake), ['quote_id' => $quote->sqid])->assertRedirect();
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.quote.decide', $intake), ['decision' => 'accept'])->assertRedirect();

        return $quote->refresh();
    }

    public function test_it_request_becomes_a_portal_ticket_with_the_customer_files_only_after_acceptance(): void {
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.store'), [
            'kind' => 'it',
            'submission_key' => (string) Str::uuid(),
            'subject' => 'Drucker im Büro fällt aus',
            'values' => ['service' => 'Fehlersuche Netzwerkdrucker', 'system' => 'HP LaserJet', 'impact' => 'single', 'execution' => 'on_site'],
            'uploads' => [UploadedFile::fake()->createWithContent('fehlerprotokoll.txt', 'Error 49.4C02')],
        ])->assertRedirect();
        $intake = CustomerIntake::query()->withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->admin, 'web')->post(route('customer-intakes.message', $intake), [
            'kind' => 'note',
            'body' => 'Intern: Techniker Müller einplanen.',
            'uploads' => [UploadedFile::fake()->createWithContent('intern.txt', 'nur intern')],
        ]);
        $this->assertSame(0, ServiceTicket::query()->count());

        $this->acceptQuoteFor($intake);
        $this->actingAs($this->admin, 'web')->post(route('customer-intakes.handover', $intake))->assertRedirect();
        $this->actingAs($this->admin, 'web')->post(route('customer-intakes.handover', $intake))->assertRedirect();

        $this->assertSame(1, ServiceTicket::query()->count());
        $ticket = ServiceTicket::query()->firstOrFail();
        $this->assertSame(ServiceTicketSource::CustomerPortal, $ticket->source);
        $this->assertSame((int) $this->customer->id, (int) $ticket->customer_id);
        $this->assertSame((int) $this->portalUser->id, (int) $ticket->requester_id);
        $this->assertStringContainsString($intake->number, (string) $ticket->description);
        $this->assertStringContainsString('HP LaserJet', (string) $ticket->description);
        $this->assertSame(['fehlerprotokoll.txt'], $ticket->attachments()->pluck('original_name')->all());
        $this->assertTrue((bool) $ticket->attachments()->firstOrFail()->customer_visible);

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $intake))
            ->assertOk()
            ->assertSee($ticket->ticket_no)
            ->assertSee('Beauftragt — eingeplant');
        $this->actingAs($this->admin, 'web')->get(route('service-tickets.show', $ticket))->assertOk()->assertSee($intake->number);
    }

    public function test_catalog_request_collects_upload_fields_and_creates_the_request_only_after_handover(): void {
        $item = $this->catalogItem();

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.catalog.show', $item))->assertOk()->assertSee('Leistung anfragen');
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.catalog.request', $item))
            ->assertOk()
            ->assertSee('Zielsystem')
            ->assertSee('Inventarliste');

        $payload = [
            'kind' => 'it',
            'submission_key' => (string) Str::uuid(),
            'subject' => 'Servermigration',
            'values' => ['service' => 'Migration', 'impact' => 'planned', 'execution' => 'remote'],
            'catalog' => ['target' => 'Proxmox-Cluster'],
        ];
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.catalog.request.store', $item), $payload)
            ->assertSessionHasErrors('catalog.inventory');

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.catalog.request.store', $item), $payload + [
            'files' => ['inventory' => UploadedFile::fake()->createWithContent('inventar.csv', "host;ram\nsrv1;64")],
        ])->assertRedirect();

        $intake = CustomerIntake::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(IntakeKind::It, $intake->kind);
        $this->assertSame((int) $item->id, (int) $intake->request_item_id);
        $this->assertSame('Proxmox-Cluster', $intake->catalog_form?->values->get('target'));
        $this->assertSame('inventar.csv', $intake->catalog_form->values->get('inventory'));
        $this->assertSame('field:inventory', $intake->attachments()->firstOrFail()->meta_type);
        // Keine vorzeitige Erfüllung: ohne Beauftragung kein Request, kein Ticket.
        $this->assertSame(0, ServiceRequest::query()->count());
        $this->assertSame(0, ServiceTicket::query()->count());

        $this->acceptQuoteFor($intake);
        $this->actingAs($this->admin, 'web')->post(route('customer-intakes.handover', $intake))->assertRedirect();

        $request = ServiceRequest::query()->firstOrFail();
        $this->assertSame(ServiceRequestStatus::Done, $request->status);
        $this->assertSame('Proxmox-Cluster', $request->form_snapshot['answers']['target'] ?? null);
        $ticket = $request->ticket()->firstOrFail();
        $this->assertSame((int) $ticket->id, (int) $intake->fresh()?->target_id);
        $this->assertSame(['inventar.csv'], $ticket->attachments()->pluck('original_name')->all());
        $this->assertSame(IntakeStatus::HandedOver, $intake->fresh()?->status);

        // Kundensicht und Bestellliste folgen dem Ticket, nicht „Erledigt" des Requests.
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $intake))->assertSee('Beauftragt — eingeplant');
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.catalog.index'))
            ->assertOk()
            ->assertSee('Gemeldet')
            ->assertDontSee('Erledigt');
    }

    public function test_catalog_request_needs_the_intake_capability(): void {
        $this->allowPortal($this->customer, ['tickets']);
        $item = $this->catalogItem();

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.catalog.show', $item))->assertOk()->assertDontSee('Leistung anfragen');
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.catalog.request', $item))->assertNotFound();
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.catalog.request.store', $item), [])->assertNotFound();
    }
}

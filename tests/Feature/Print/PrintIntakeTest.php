<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Print;

use App\Enums\Customer\{IntakeKind, IntakeStatus};
use App\Enums\Print\PrintOrderStatus;
use App\Mail\CustomerIntakeNoticeMail;
use App\Models\Article\Article;
use App\Models\Customer\{Customer, CustomerIntake};
use App\Models\Platform\User;
use App\Models\Print\PrintOrder;
use App\Services\Customer\Intake\{CustomerIntakeService, IntakeTemplates};
use App\Services\Fields\{FieldDocument, FieldValues};
use App\Services\Invoicing\QuoteService;
use App\Services\Print\PrintOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-1076: Druckerei-Strecke Anfrage → Angebot → Annahme → Druckauftrag →
 * Datenprüfung → Kundenfreigabe → interne Freigabe. Die Kundenfreigabe gilt
 * nur für Datei-Hash und Parameter; ein Dateitausch verlangt sie neu,
 * wiederholte Übernahme erzeugt keinen zweiten Auftrag.
 */
final class PrintIntakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private const CONTENT_A = "%PDF-1.7\nFlyer Vorderseite";

    private const CONTENT_B = "%PDF-1.7\nFlyer korrigiert";

    private Customer $customer;

    private User $portalUser;

    private User $admin;

    private Article $article;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');
        Mail::fake();

        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer, ['intakes']);
        $this->portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true, 'name' => 'Flyer A5']);
    }

    private function activateProfile(): void {
        $settings = is_array($this->organization->settings) ? $this->organization->settings : [];
        $settings['branch_profile_code'] = PrintOrderService::PROFILE_CODE;
        $settings['branch_profile_versions'] = [PrintOrderService::PROFILE_CODE => 1];
        $this->organization->forceFill(['settings' => $settings])->save();
        $this->admin->unsetRelation('organization');
    }

    /** Eingang mit zwei Dateien, Angebot versandt und vom Kunden im Portal angenommen. */
    private function acceptedIntake(): CustomerIntake {
        $schema = app(IntakeTemplates::class)->schema(IntakeKind::Print);
        $intake = app(CustomerIntakeService::class)->submit($this->portalUser, [
            'kind' => IntakeKind::Print,
            'subject' => '500 Flyer',
            'description' => null,
            'desired_date' => now()->addDays(7)->toDateString(),
            'submission_key' => (string) Str::uuid(),
        ], new FieldDocument($schema, FieldValues::normalize($schema, [
            'product' => 'Flyer', 'quantity' => 500, 'final_format' => 'a5', 'color_mode' => '4_4', 'material' => 'Bilderdruck matt', 'delivery' => 'pickup',
        ])), [
            UploadedFile::fake()->createWithContent('flyer.pdf', self::CONTENT_A),
            UploadedFile::fake()->createWithContent('flyer-korrigiert.pdf', self::CONTENT_B),
        ]);

        $quotes = app(QuoteService::class);
        $quote = $quotes->create(['customer_id' => $this->customer->id], [
            ['article_id' => $this->article->id, 'description' => 'Flyer A5, 4/4, 500 Stück', 'quantity' => 500, 'unit' => 'Stk', 'unit_price' => 0.2],
        ], $this->admin);
        $quotes->approve($quote, $this->admin);
        $quotes->send($quote->refresh(), $this->admin);
        $this->actingAs($this->admin, 'web')->post(route('customer-intakes.quote.link', $intake), ['quote_id' => $quote->sqid])->assertRedirect();
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.quote.decide', $intake), ['decision' => 'accept'])->assertRedirect();

        return $intake->refresh();
    }

    /** @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> */
    private function handOver(CustomerIntake $intake, ?string $file = 'flyer.pdf'): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->admin, 'web')->post(route('customer-intakes.handover', $intake), [
            'article_id' => $this->article->sqid,
            'target_qty' => '500',
            'unit' => 'Stk',
            'output_kind' => 'pickup',
            'production_file' => $file !== null ? $intake->attachments()->where('original_name', $file)->firstOrFail()->sqid : '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function parameters(array $overrides = []): array {
        return array_replace([
            'final_format' => 'DIN A5',
            'quantity' => '500',
            'color_mode' => '4/4',
            'material' => 'Bilderdruck matt 300 g',
        ], $overrides);
    }

    public function test_handover_needs_print_profile(): void {
        $intake = $this->acceptedIntake();

        $this->handOver($intake)->assertSessionHasErrors('handover');
        $this->assertSame(0, PrintOrder::query()->count());
    }

    public function test_full_flow_with_customer_approval_before_internal_release(): void {
        $this->activateProfile();
        $intake = $this->acceptedIntake();

        $this->handOver($intake)->assertRedirect(route('customer-intakes.show', $intake));
        $this->handOver($intake->refresh())->assertRedirect();
        $this->assertSame(1, PrintOrder::query()->count());

        $order = PrintOrder::query()->firstOrFail();
        $intake->refresh();
        $this->assertSame(IntakeStatus::HandedOver, $intake->status);
        $this->assertSame((int) $order->id, (int) $intake->target_id);
        $this->assertTrue($order->is_customer_approval_required);
        $this->assertSame(hash('sha256', self::CONTENT_A), $order->file_hash);
        $this->assertSame((int) $this->customer->id, (int) $order->manufacturingOrder?->customer_id);
        // Der Kundennachweis am Eingang bleibt neben der Produktionskopie bestehen.
        $this->assertSame(2, $intake->attachments()->count());

        $this->actingAs($this->admin, 'web')->get(route('print-orders.show', $order))->assertOk()->assertSee($intake->number)->assertSee('flyer-korrigiert.pdf');
        $this->actingAs($this->admin, 'web')->post(route('print-orders.preflight.run', $order))->assertRedirect();

        // Interne Freigabe ohne Kundenfreigabe ist gesperrt.
        $this->actingAs($this->admin, 'web')->post(route('print-orders.approve', $order), $this->parameters(['due_date' => now()->addDays(5)->toDateString()]))
            ->assertSessionHasErrors('approval');

        $this->actingAs($this->admin, 'web')->post(route('print-orders.customer-approval', $order), $this->parameters())->assertRedirect();
        $order->refresh();
        $this->assertTrue($order->customerApprovalPending());
        $this->assertSame(hash('sha256', self::CONTENT_A), data_get($order->customer_approval_request, 'file.sha256'));
        Mail::assertSent(CustomerIntakeNoticeMail::class, fn (CustomerIntakeNoticeMail $mail): bool => $mail->notice === 'print_approval');

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $intake))
            ->assertOk()
            ->assertSee(hash('sha256', self::CONTENT_A))
            ->assertSee('Druckdaten freigeben');
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.print-file', $intake))->assertOk();
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.print-approval', $intake), ['decision' => 'approve'])->assertRedirect();

        $order->refresh();
        $this->assertTrue($order->customerApprovalMatchesFile());
        $this->assertSame((int) $this->portalUser->id, (int) $order->customer_approval_user_id);
        $this->assertContains('print_approved', $intake->journal()->get()->map->eventKey()->all());

        // Interne Freigabe muss die freigegebenen Parameter übernehmen.
        $this->actingAs($this->admin, 'web')->post(route('print-orders.approve', $order), $this->parameters(['quantity' => '1000', 'due_date' => now()->addDays(5)->toDateString()]))
            ->assertSessionHasErrors('quantity');
        $this->actingAs($this->admin, 'web')->post(route('print-orders.approve', $order), $this->parameters(['due_date' => now()->addDays(5)->toDateString()]))
            ->assertSessionHasNoErrors();
        $this->assertSame(PrintOrderStatus::Approved, $order->fresh()?->status);
    }

    public function test_replacing_the_production_file_requires_a_new_customer_approval(): void {
        $this->activateProfile();
        $intake = $this->acceptedIntake();
        $this->handOver($intake);
        $order = PrintOrder::query()->firstOrFail();
        $this->actingAs($this->admin, 'web')->post(route('print-orders.preflight.run', $order));
        $this->actingAs($this->admin, 'web')->post(route('print-orders.customer-approval', $order), $this->parameters());

        $corrected = $intake->attachments()->where('original_name', 'flyer-korrigiert.pdf')->firstOrFail();
        $this->actingAs($this->admin, 'web')->post(route('print-orders.intake-file', [$order, $corrected]))->assertRedirect();

        $order->refresh();
        $this->assertSame(hash('sha256', self::CONTENT_B), $order->file_hash);
        $this->assertNull($order->customer_approval_requested_at);
        $this->assertSame(2, $order->document?->versions()->count());
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.print-approval', $intake), ['decision' => 'approve'])->assertNotFound();

        // Zurückweisen verlangt eine Begründung und gilt nur für die angeforderte Datei.
        $this->actingAs($this->admin, 'web')->post(route('print-orders.preflight.run', $order));
        $this->actingAs($this->admin, 'web')->post(route('print-orders.customer-approval', $order), $this->parameters());
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.print-approval', $intake), ['decision' => 'decline'])->assertSessionHasErrors('reason');
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.print-approval', $intake), ['decision' => 'decline', 'reason' => 'Logo zu klein'])->assertRedirect();
        $this->assertNotNull($order->fresh()?->customer_declined_at);
        $this->assertFalse($order->refresh()->customerApprovalMatchesFile());
    }

    public function test_foreign_customer_cannot_reach_print_approval(): void {
        $this->activateProfile();
        $intake = $this->acceptedIntake();
        $this->handOver($intake);
        $order = PrintOrder::query()->firstOrFail();
        $this->actingAs($this->admin, 'web')->post(route('print-orders.preflight.run', $order));
        $this->actingAs($this->admin, 'web')->post(route('print-orders.customer-approval', $order), $this->parameters());

        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($other, ['intakes']);
        $otherUser = User::factory()->kunde((int) $other->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);

        $this->actingAs($otherUser, 'customer')->get(route('customer.intakes.print-file', $intake))->assertNotFound();
        $this->actingAs($otherUser, 'customer')->post(route('customer.intakes.print-approval', $intake), ['decision' => 'approve'])->assertNotFound();
        $this->assertTrue($order->fresh()?->customerApprovalPending());
    }
}

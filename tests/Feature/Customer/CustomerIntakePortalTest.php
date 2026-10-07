<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakePortalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\Customer\{IntakeKind, IntakeStatus};
use App\Exceptions\LimitExceededException;
use App\Mail\CustomerIntakeNoticeMail;
use App\Models\Customer\{Customer, CustomerIntake};
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\LimitGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-1074: Kundeneingang im Portal — Default-Deny, Mehrfachupload mit
 * Zweckgrenzen, Fehler je Datei, idempotente Einreichung, Bereinigung bei
 * Abbruch, Mandanten-/Kundengrenzen, Nachreichung nur an freigegebene
 * Eingänge und Mailfehler ohne Datenverlust.
 */
final class CustomerIntakePortalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');
        Mail::fake();

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer, ['intakes']);
        $this->portalUser = $this->portalUserFor($this->customer);
    }

    private function portalUserFor(Customer $customer): User {
        return User::factory()
            ->kunde((int) $customer->id, (int) $customer->organization_id)
            ->create(['organization_id' => $customer->organization_id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function printPayload(array $overrides = []): array {
        return array_replace_recursive([
            'kind' => 'print',
            'submission_key' => (string) Str::uuid(),
            'subject' => '500 Flyer für das Sommerfest',
            'description' => 'Bitte matt und beidseitig.',
            'desired_date' => now()->addDays(10)->toDateString(),
            'values' => [
                'product' => 'Flyer',
                'quantity' => '500',
                'final_format' => 'a5',
                'color_mode' => '4_4',
                'material' => '300 g Bilderdruck matt',
                'delivery' => 'pickup',
            ],
        ], $overrides);
    }

    private function pdf(string $name): UploadedFile {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.7\n" . $name);
    }

    public function test_without_capability_all_intake_routes_are_404(): void {
        $this->allowPortal($this->customer, ['tickets']);

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.index'))->assertNotFound();
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.create', ['kind' => 'print']))->assertNotFound();
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.store'), $this->printPayload())->assertNotFound();
        $this->assertSame(0, CustomerIntake::query()->withoutGlobalScopes()->count());
    }

    public function test_print_request_with_several_files_creates_one_complete_intake(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.intakes.create', ['kind' => 'print']))
            ->assertOk()
            ->assertSee('Endformat')
            ->assertSee('Beratung erforderlich');

        $response = $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.store'), $this->printPayload() + [
            'uploads' => [$this->pdf('vorderseite.pdf'), $this->pdf('rueckseite.pdf'), UploadedFile::fake()->createWithContent('motiv.tif', "II*\0tiff")],
        ]);

        $intake = CustomerIntake::query()->withoutGlobalScopes()->firstOrFail();
        $response->assertRedirect(route('customer.intakes.show', $intake));
        $this->assertSame(IntakeKind::Print, $intake->kind);
        $this->assertSame(IntakeStatus::Submitted, $intake->status);
        $this->assertStringStartsWith('KE-', $intake->number);
        $this->assertSame('a5', $intake->form?->values->get('final_format'));
        $this->assertSame(3, $intake->attachments()->where('customer_visible', true)->count());
        $this->assertSame('submitted', $intake->journal()->first()?->eventKey());
        Mail::assertSent(CustomerIntakeNoticeMail::class, fn (CustomerIntakeNoticeMail $mail): bool => $mail->notice === 'submitted' && $mail->hasTo($this->portalUser->email));

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $intake))
            ->assertOk()
            ->assertSee($intake->number)
            ->assertSee('vorderseite.pdf');
    }

    public function test_shipping_address_is_required_only_for_shipping(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.intakes.store'), $this->printPayload(['values' => ['delivery' => 'shipping']]))
            ->assertSessionHasErrors('values.shipping_address');

        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.intakes.store'), $this->printPayload(['values' => ['delivery' => 'shipping', 'shipping_address' => "Musterweg 1\n12345 Musterstadt"]]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, CustomerIntake::query()->withoutGlobalScopes()->count());
    }

    public function test_disallowed_file_is_named_and_nothing_is_stored(): void {
        $response = $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.store'), $this->printPayload() + [
            'uploads' => [$this->pdf('ok.pdf'), UploadedFile::fake()->createWithContent('schadhaft.exe', 'MZ')],
        ]);

        $response->assertSessionHasErrors('uploads.1');
        $this->assertStringContainsString('schadhaft.exe', (string) session('errors')?->first('uploads.1'));
        $this->assertSame(0, CustomerIntake::query()->withoutGlobalScopes()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_general_list_is_used_for_it_and_print_formats_stay_print_only(): void {
        $response = $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.store'), [
            'kind' => 'it',
            'submission_key' => (string) Str::uuid(),
            'subject' => 'Servermigration',
            'values' => ['service' => 'Migration auf neuen Server', 'impact' => 'planned', 'execution' => 'remote'],
            'uploads' => [UploadedFile::fake()->createWithContent('logo.eps', "%!PS-Adobe-3.0 EPSF-3.0\n")],
        ]);

        $response->assertSessionHasErrors('uploads.0');
        $this->assertSame(0, CustomerIntake::query()->withoutGlobalScopes()->count());
    }

    public function test_resubmitting_the_same_form_returns_the_existing_intake(): void {
        $payload = $this->printPayload() + ['uploads' => [$this->pdf('a.pdf')]];

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.store'), $payload)->assertRedirect();
        $payload['uploads'] = [$this->pdf('a.pdf')];
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.store'), $payload)->assertRedirect();

        $this->assertSame(1, CustomerIntake::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, CustomerIntake::query()->withoutGlobalScopes()->firstOrFail()->attachments()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_aborted_submission_removes_already_stored_files(): void {
        $guard = $this->mock(LimitGuard::class);
        $guard->shouldReceive('ensureCanStoreAttachment')->once()->andReturnNull();
        $guard->shouldReceive('ensureCanStoreAttachment')->once()->andThrow(new LimitExceededException('storage_quota_gb', 2, 1, 'Speicherkontingent erschöpft.'));

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.store'), $this->printPayload() + [
            'uploads' => [$this->pdf('eins.pdf'), $this->pdf('zwei.pdf')],
        ])->assertSessionHasErrors();

        $this->assertSame(0, CustomerIntake::query()->withoutGlobalScopes()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_xhr_submission_answers_with_json_redirect_and_named_file_errors(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->postJson(route('customer.intakes.store'), $this->printPayload() + ['uploads' => [UploadedFile::fake()->createWithContent('virus.exe', 'MZ')]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('uploads.0');

        $response = $this->actingAs($this->portalUser, 'customer')
            ->postJson(route('customer.intakes.store'), $this->printPayload() + ['uploads' => [$this->pdf('druck.pdf')]])
            ->assertOk();
        $intake = CustomerIntake::query()->withoutGlobalScopes()->firstOrFail();
        $response->assertJson(['redirect' => route('customer.intakes.show', $intake)]);
    }

    public function test_other_customers_and_tenants_cannot_see_or_upload(): void {
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.store'), $this->printPayload() + ['uploads' => [$this->pdf('geheim.pdf')]]);
        $intake = CustomerIntake::query()->withoutGlobalScopes()->firstOrFail();
        $file = $intake->attachments()->firstOrFail();

        $neighbour = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($neighbour, ['intakes']);
        $neighbourUser = $this->portalUserFor($neighbour);

        $foreignOrg = Organization::factory()->create();
        $foreignCustomer = Customer::factory()->create(['organization_id' => $foreignOrg->id]);
        $this->allowPortal($foreignCustomer, ['intakes']);
        $foreignUser = $this->portalUserFor($foreignCustomer);

        foreach ([$neighbourUser, $foreignUser] as $other) {
            $this->actingAs($other, 'customer')->get(route('customer.intakes.show', $intake))->assertNotFound();
            $this->actingAs($other, 'customer')->get(route('customer.intakes.files.download', [$intake, $file]))->assertNotFound();
            $this->actingAs($other, 'customer')->post(route('customer.intakes.files.store', $intake), ['uploads' => [$this->pdf('fremd.pdf')]])->assertNotFound();
            $this->actingAs($other, 'customer')->post(route('customer.intakes.upload.store'), ['intake' => $intake->sqid, 'uploads' => [$this->pdf('fremd.pdf')]])->assertNotFound();
        }
        // Der letzte Portal-Request ließ die fremde Organisation gebunden — Zählung ohne Org-Scope.
        $this->assertSame(1, $intake->attachments()->withoutGlobalScopes()->count());

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.files.download', [$intake, $file]))->assertOk();
    }

    public function test_files_can_be_submitted_only_to_released_intakes_without_new_order(): void {
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.store'), $this->printPayload());
        $open = CustomerIntake::query()->withoutGlobalScopes()->firstOrFail();
        $rejected = CustomerIntake::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'status' => IntakeStatus::Rejected,
        ]);

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.upload'))
            ->assertOk()
            ->assertSee($open->number)
            ->assertDontSee($rejected->number);

        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.intakes.upload.store'), ['intake' => $open->sqid, 'uploads' => [$this->pdf('korrektur.pdf')]])
            ->assertRedirect(route('customer.intakes.show', $open));
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.intakes.upload.store'), ['intake' => $rejected->sqid, 'uploads' => [$this->pdf('zu-spaet.pdf')]])
            ->assertNotFound();
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.intakes.files.store', $rejected), ['uploads' => [$this->pdf('zu-spaet.pdf')]])
            ->assertSessionHasErrors('uploads');

        $this->assertSame(2, CustomerIntake::query()->withoutGlobalScopes()->count());
        $this->assertSame(['korrektur.pdf'], $open->attachments()->pluck('original_name')->all());
        $this->assertSame('files_added', $open->journal()->reorder()->latest('id')->first()?->eventKey());
    }

    public function test_handed_over_intake_accepts_files_only_with_open_channel(): void {
        $intake = CustomerIntake::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'status' => IntakeStatus::HandedOver,
        ]);

        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.intakes.files.store', $intake), ['uploads' => [$this->pdf('nachtrag.pdf')]])
            ->assertSessionHasErrors('uploads');

        $intake->forceFill(['is_upload_open' => true])->save();
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.intakes.files.store', $intake), ['uploads' => [$this->pdf('nachtrag.pdf')]])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $intake->attachments()->count());
    }

    public function test_mail_failure_keeps_the_intake_and_marks_it(): void {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP nicht erreichbar'));

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.store'), $this->printPayload())->assertRedirect();

        $intake = CustomerIntake::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertNotNull($intake->mail_failed_at);
        $this->assertContains('mail_failed', $intake->journal()->get()->map->eventKey()->all());
    }

    public function test_withdraw_is_logged_and_only_possible_while_open(): void {
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.store'), $this->printPayload());
        $intake = CustomerIntake::query()->withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.withdraw', $intake))->assertRedirect();
        $this->assertSame(IntakeStatus::Withdrawn, $intake->fresh()?->status);
        $this->assertNotNull($intake->refresh()->closed_at);

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.withdraw', $intake))->assertSessionHasErrors('status');
    }
}

<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeUploadChannelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\Customer\{IntakeKind, IntakeStatus};
use App\Models\Customer\{Customer, CustomerIntake, CustomerIntakeUploadLink};
use App\Models\Platform\User;
use App\Modules\ModuleRegistry;
use App\Services\Customer\Contracts\IntakeUploadChannel;
use App\Services\Customer\Intake\{CustomerIntakeService, IntakeTemplates};
use App\Services\Fields\{FieldDocument, FieldValues};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Storage};
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\Support\FakeIntakeUploadChannel;
use Tests\TestCase;

/**
 * MVP-1078: Upload-Kanal des Kundeneingangs — Kunde öffnet den Link selbst,
 * jede Fassung wird genau einmal übernommen oder mit Grund abgelehnt, nach
 * Abschluss oder Ablauf folgt nach der letzten Übernahme der Widerruf,
 * Fehler sind sichtbar und werden wiederholt.
 */
final class CustomerIntakeUploadChannelTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    private User $lead;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');
        Mail::fake();
        FakeIntakeUploadChannel::reset();
        app(ModuleRegistry::class)->contribute(IntakeUploadChannel::class, FakeIntakeUploadChannel::class);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer, ['intakes']);
        $this->portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    private function intake(): CustomerIntake {
        $schema = app(IntakeTemplates::class)->schema(IntakeKind::Print);

        return app(CustomerIntakeService::class)->submit($this->portalUser, [
            'kind' => IntakeKind::Print,
            'subject' => 'Großformat',
            'description' => null,
            'desired_date' => null,
            'submission_key' => (string) Str::uuid(),
        ], new FieldDocument($schema, FieldValues::normalize($schema, [
            'product' => 'Plakat', 'final_format' => 'a3', 'color_mode' => '4_0', 'delivery' => 'pickup',
        ])));
    }

    private function openLink(CustomerIntake $intake): CustomerIntakeUploadLink {
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.upload-link', $intake))->assertRedirect(route('customer.intakes.show', $intake));

        return CustomerIntakeUploadLink::query()->withoutGlobalScopes()->where('customer_intake_id', $intake->id)->latest('id')->firstOrFail();
    }

    public function test_without_available_channel_there_is_no_link(): void {
        FakeIntakeUploadChannel::$available = false;
        $intake = $this->intake();

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $intake))->assertOk()->assertDontSee('Große Dateien über');
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.upload-link', $intake))->assertSessionHasErrors('uploads');
        $this->assertSame(0, CustomerIntakeUploadLink::query()->withoutGlobalScopes()->count());
    }

    public function test_customer_opens_one_link_with_password_for_own_intake_only(): void {
        $intake = $this->intake();
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $intake))->assertSee('Große Dateien über Testablage hochladen');

        $link = $this->openLink($intake);
        $this->assertSame('share-' . $intake->id, $link->external_id);
        $this->assertSame(16, strlen((string) $link->password));
        $this->assertTrue($link->expires_at?->isFuture());
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $intake))
            ->assertSee('https://cloud.example.test/s/' . $intake->id)
            ->assertSee((string) $link->password);

        $this->openLink($intake);
        $this->assertSame(1, CustomerIntakeUploadLink::query()->withoutGlobalScopes()->count());
        $this->assertContains('cloud_link_opened', $intake->journal()->get()->map->eventKey()->all());

        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($other, ['intakes']);
        $otherUser = User::factory()->kunde((int) $other->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->actingAs($otherUser, 'customer')->post(route('customer.intakes.upload-link', $intake))->assertNotFound();
        $this->actingAs($otherUser, 'customer')->post(route('customer.intakes.upload-link.sync', $intake))->assertNotFound();
    }

    public function test_each_version_is_imported_once_and_bad_files_are_rejected_with_reason(): void {
        $intake = $this->intake();
        $link = $this->openLink($intake);
        FakeIntakeUploadChannel::put('plakat.pdf', "%PDF-1.7\nPlakat", '101');
        FakeIntakeUploadChannel::put('setup.exe', 'MZ', '102');
        FakeIntakeUploadChannel::put('riesig.pdf', "%PDF-1.7\nx", '103', size: 5 * 1024 * 1024 * 1024);

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.upload-link.sync', $intake))->assertRedirect();

        $this->assertSame(['plakat.pdf'], $intake->attachments()->pluck('original_name')->all());
        $this->assertSame('channel:fake', $intake->attachments()->firstOrFail()->meta_type);
        $events = $intake->journal()->get();
        $rejected = $events->first(fn ($event) => $event->eventKey() === 'cloud_files_rejected');
        $this->assertCount(2, (array) ($rejected?->payloadData()['files'] ?? []));
        $this->assertCount(3, (array) $link->refresh()->processed_keys);

        // Wiederholung übernimmt nichts doppelt; eine neue Fassung ist ein neuer Anhang.
        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.upload-link.sync', $intake))->assertRedirect();
        $this->assertSame(1, $intake->attachments()->count());
        FakeIntakeUploadChannel::put('plakat.pdf', "%PDF-1.7\nPlakat korrigiert", '101', 'e2');
        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.upload-link.sync', $intake))->assertRedirect();
        $this->assertSame(2, $intake->attachments()->where('original_name', 'plakat.pdf')->count());
    }

    public function test_closed_or_expired_intake_imports_last_files_then_revokes(): void {
        $closed = $this->intake();
        $closedLink = $this->openLink($closed);
        FakeIntakeUploadChannel::put('letzte.pdf', "%PDF-1.7\nletzte", '201');
        $closed->forceFill(['status' => IntakeStatus::Rejected, 'rejection_reason' => 'Kein Auftrag', 'closed_at' => now()])->save();

        $expired = $this->intake();
        $expiredLink = $this->openLink($expired);
        $expiredLink->forceFill(['expires_at' => now()->subDay()])->save();

        $this->artisan('customer-intakes:sync-uploads')->assertSuccessful();

        $this->assertSame(['letzte.pdf'], $closed->attachments()->pluck('original_name')->all());
        $this->assertNotNull($closedLink->refresh()->revoked_at);
        $this->assertNotNull($expiredLink->refresh()->revoked_at);
        $this->assertEqualsCanonicalizing([$closedLink->external_id, $expiredLink->external_id], FakeIntakeUploadChannel::$revoked);
        $this->assertContains('cloud_link_revoked', $closed->journal()->get()->map->eventKey()->all());

        // Der widerrufene Link erscheint im Portal nicht mehr; der Eingang nimmt keine Dateien.
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $closed))->assertDontSee('https://cloud.example.test/s/' . $closed->id);
    }

    public function test_transport_errors_are_visible_logged_once_and_retried(): void {
        $intake = $this->intake();
        $link = $this->openLink($intake);
        FakeIntakeUploadChannel::put('plan.pdf', "%PDF-1.7\nplan", '301');
        FakeIntakeUploadChannel::$failList = true;

        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.upload-link.sync', $intake))->assertSessionHas('error');
        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.upload-link.sync', $intake));
        $this->assertNotNull($link->refresh()->last_error);
        $this->assertSame(1, $intake->journal()->get()->filter(fn ($event) => $event->eventKey() === 'cloud_sync_failed')->count());
        $this->actingAs($this->lead, 'web')->get(route('customer-intakes.show', $intake))->assertSee('Abholung fehlgeschlagen');

        FakeIntakeUploadChannel::$failList = false;
        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.upload-link.sync', $intake))->assertSessionHas('status');
        $this->assertNull($link->refresh()->last_error);
        $this->assertSame(1, $intake->attachments()->count());
    }

    public function test_staff_can_revoke_the_link(): void {
        $intake = $this->intake();
        $link = $this->openLink($intake);

        $this->actingAs($this->lead, 'web')->delete(route('customer-intakes.upload-link.revoke', $intake))->assertRedirect();

        $this->assertNotNull($link->refresh()->revoked_at);
        $this->assertSame([$link->external_id], FakeIntakeUploadChannel::$revoked);
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.upload-link.sync', $intake))->assertNotFound();
    }
}

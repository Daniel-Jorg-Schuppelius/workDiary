<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalProtocolAcceptanceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Enums\Protocol\{ProtocolSignatureMethod, ProtocolSignatureRole, ProtocolStatus, ProtocolType, ProtocolVisibility};
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Protocol\Protocol;
use App\Services\Protocol\ProtocolService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-1572: Abnahme im Kundenportal — ein zur Prüfung eingereichtes,
 * kundensichtbares Protokoll des eigenen Auftrags oder Objekts ansehen und
 * unterschreiben (Methode `portal`).
 */
class CustomerPortalProtocolAcceptanceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private const string PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private Customer $customer;

    private User $portalUser;

    private User $staff;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);
        $this->staff = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_customer_sees_and_signs_a_protocol_waiting_for_acceptance(): void {
        $diary = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        $protocol = $this->protocol($diary, 'Abnahme Heizungsanlage', ProtocolStatus::InReview, ProtocolVisibility::Customer);
        $this->protocol($diary, 'Entwurf Abnahme', ProtocolStatus::Draft, ProtocolVisibility::Customer);
        $this->protocol($diary, 'Interne Prüfung', ProtocolStatus::InReview, ProtocolVisibility::Internal);

        $url = route('customer.diary.protocols.accept', [$diary, $protocol]);
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.diary.show', $diary))
            ->assertOk()
            ->assertSee('Abnahme Heizungsanlage')
            ->assertSee($url, false)
            ->assertDontSee('Entwurf Abnahme')
            ->assertDontSee('Interne Prüfung');

        $this->actingAs($this->portalUser, 'customer')->get($url)
            ->assertOk()
            ->assertSee('Abnahme Heizungsanlage')
            ->assertSee('x-data="signaturePad"', false);

        $signUrl = route('customer.diary.protocols.accept.sign', [$diary, $protocol]);
        $this->actingAs($this->portalUser, 'customer')->from($url)->post($signUrl, ['signer_name' => 'Max Beispiel', 'accept' => '1'])
            ->assertSessionHasErrors('signature');
        $this->actingAs($this->portalUser, 'customer')->from($url)->post($signUrl, ['signer_name' => 'Max Beispiel', 'signature' => self::PNG, 'accept' => '1'])
            ->assertRedirect(route('customer.diary.show', $diary))
            ->assertSessionHasNoErrors();

        $signature = $protocol->signatures()->with('signatureAttachment')->firstOrFail();
        $this->assertSame(ProtocolSignatureMethod::Portal, $signature->method);
        $this->assertSame(ProtocolSignatureRole::Customer, $signature->role);
        $this->assertSame($this->portalUser->email, $signature->signer_email);
        $this->assertSame((int) $this->portalUser->id, (int) $signature->signatureAttachment?->user_id);
        Storage::disk('local')->assertExists((string) $signature->signatureAttachment?->path);

        // Unterschrieben: kein zweites Mal, und der Abschluss im Haus braucht keine weitere Unterschrift.
        $this->assertSame(ProtocolStatus::InReview, $protocol->fresh()->status);
        $this->actingAs($this->portalUser, 'customer')->get($url)->assertNotFound();
        app('auth')->forgetGuards();
        app(ProtocolService::class)->sign($protocol->fresh(), $this->staff);
        $this->assertSame(ProtocolStatus::Signed, $protocol->fresh()->status);
    }

    public function test_asset_protocol_is_signed_the_same_way_and_foreign_ones_stay_hidden(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        $protocol = $this->protocol($asset, 'Übergabe Anlage', ProtocolStatus::InReview, ProtocolVisibility::Customer, ProtocolType::Handover);

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.assets.show', $asset))
            ->assertOk()
            ->assertSee(route('customer.assets.protocols.accept', [$asset, $protocol]), false);
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.assets.protocols.accept.sign', [$asset, $protocol]), ['signer_name' => 'Max Beispiel', 'signature' => self::PNG, 'accept' => '1'])
            ->assertRedirect(route('customer.assets.show', $asset));
        $this->assertSame(1, $protocol->signatures()->where('method', ProtocolSignatureMethod::Portal->value)->count());

        $foreignCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreignAsset = Asset::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $foreignCustomer->id]);
        $foreign = $this->protocol($foreignAsset, 'Fremde Abnahme', ProtocolStatus::InReview, ProtocolVisibility::Customer);

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.assets.protocols.accept', [$foreignAsset, $foreign]))->assertNotFound();
        // Fremdes Protokoll unter dem eigenen Objekt adressiert: ebenfalls 404.
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.assets.protocols.accept.sign', [$asset, $foreign]), ['signer_name' => 'Max Beispiel', 'signature' => self::PNG, 'accept' => '1'])
            ->assertNotFound();
        $this->assertSame(0, $foreign->signatures()->count());
    }

    public function test_customer_rejects_the_acceptance_and_the_protocol_goes_back_to_the_house(): void {
        $diary = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        $protocol = $this->protocol($diary, 'Abnahme Heizungsanlage', ProtocolStatus::InReview, ProtocolVisibility::Customer);
        $protocol->record(\App\Enums\Protocol\ProtocolEventType::RequestedReview, [], $this->staff);
        $url = route('customer.diary.protocols.accept', [$diary, $protocol]);
        $rejectUrl = route('customer.diary.protocols.accept.reject', [$diary, $protocol]);

        $this->actingAs($this->portalUser, 'customer')->get($url)->assertOk()->assertSee($rejectUrl, false);
        $this->actingAs($this->portalUser, 'customer')->from($url)->post($rejectUrl, ['reason' => ''])->assertSessionHasErrors('reason');

        $spy = $this->spy(\App\Services\Notification\NotificationDispatcher::class);
        $this->actingAs($this->portalUser, 'customer')->from($url)
            ->post($rejectUrl, ['reason' => 'Ventil tropft weiter', 'issues' => ['Ventil im Keller undicht', '']])
            ->assertRedirect(route('customer.diary.show', $diary))
            ->assertSessionHasNoErrors();

        $this->assertSame(ProtocolStatus::Draft, $protocol->fresh()->status);
        $rejected = $protocol->journal()->where('event', \App\Enums\Protocol\ProtocolEventType::SignatureRejected)->firstOrFail();
        $this->assertSame('portal', $rejected->payload['channel']);
        $this->assertSame('Ventil tropft weiter', $rejected->payload['reason']);
        $this->assertSame(1, $rejected->payload['issue_count']);
        $this->assertSame('Ventil tropft weiter', $protocol->journal()->where('event', \App\Enums\Protocol\ProtocolEventType::ReturnedToDraft)->firstOrFail()->payload['reason']);
        $this->assertDatabaseHas('open_issues', ['title' => 'Ventil im Keller undicht', 'source_type' => 'customerRejection']);
        $spy->shouldHaveReceived('notify')->withArgs(fn ($event, $subject, $affected): bool => $event === \App\Enums\Notification\NotificationEvent::ProtocolCustomerRejected
            && $subject->is($protocol) && $affected?->is($this->staff))->once();

        // Zurück im Entwurf: nichts mehr abzunehmen.
        $this->actingAs($this->portalUser, 'customer')->get($url)->assertNotFound();
        $this->actingAs($this->portalUser, 'customer')->post($rejectUrl, ['reason' => 'Noch einmal'])->assertNotFound();
    }

    private function protocol(Model $subject, string $title, ProtocolStatus $status, ProtocolVisibility $visibility, ProtocolType $type = ProtocolType::Acceptance): Protocol {
        return Protocol::factory()->for($subject, 'subject')->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->staff->id,
            'type' => $type->value,
            'title' => $title,
            'status' => $status->value,
            'visibility' => $visibility->value,
        ]);
    }
}

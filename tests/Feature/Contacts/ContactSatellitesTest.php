<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactSatellitesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Contacts;

use App\Models\Applications\JobApplication;
use App\Models\Club\ClubMember;
use App\Models\Contacts\ContactAddress;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Platform\User;
use App\Models\Sales\Lead;
use App\Services\Applications\RecruitingService;
use App\Services\Club\ClubMemberService;
use App\Services\Sales\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-869: Anschriften aller Parteien liegen im Satelliten
 * `contact_addresses`; Formulare schreiben über `WritesContactDetails`,
 * Anonymisierung und Löschung nehmen den Satelliten mit.
 */
final class ContactSatellitesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @var array<string, string> */
    private const ADDRESS = ['address_street' => 'Hauptstraße 1', 'address_zip' => '12345', 'address_city' => 'Musterstadt'];

    public function test_club_member_address_lives_in_the_satellite(): void {
        $this->assertFalse(Schema::hasColumn('club_members', 'street'));
        $this->assertFalse(Schema::hasColumn('club_members', 'city'));

        $member = app(ClubMemberService::class)->create($this->organization, $this->admin, ['first_name' => 'Mia', 'last_name' => 'Muster'] + self::ADDRESS);

        $this->assertSame(['Hauptstraße 1', '12345 Musterstadt'], $member->postalAddressLines());
        $this->assertSame(ContactAddress::KIND_DEFAULT, $member->primaryAddress()?->kind);

        // Teil-Update (Import) ändert nur übergebene Felder.
        app(ClubMemberService::class)->update($member, ['address_city' => 'Neustadt']);
        $this->assertSame(['Hauptstraße 1', '12345 Neustadt'], $member->postalAddressLines());
        $this->assertSame(1, $member->addresses()->count());
    }

    public function test_club_member_form_shows_and_saves_the_address(): void {
        $member = app(ClubMemberService::class)->create($this->organization, $this->admin, ['first_name' => 'Mia', 'last_name' => 'Muster'] + self::ADDRESS);

        $this->actingAs($this->admin)->get(route('club.members.show', $member))
            ->assertOk()->assertSee('Hauptstraße 1, 12345 Musterstadt');

        $this->actingAs($this->admin)->put(route('club.members.update', $member), [
            'first_name' => 'Mia',
            'last_name' => 'Muster',
            'address_street' => 'Ringweg 5',
            'address_zip' => '54321',
            'address_city' => 'Altstadt',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Ringweg 5', '54321 Altstadt'], $member->refresh()->postalAddressLines());
    }

    public function test_lead_form_writes_the_satellite_and_anonymisation_removes_it(): void {
        $this->actingAs($this->admin)->post(route('leads.store'), ['company' => 'Neuland AG', 'source' => 'web'] + self::ADDRESS)
            ->assertRedirect();

        $lead = Lead::query()->firstOrFail();
        $this->assertSame(['Hauptstraße 1', '12345 Musterstadt'], $lead->postalAddressLines());

        app(LeadService::class)->anonymize($lead);
        $this->assertSame(0, $lead->addresses()->count());
    }

    public function test_converted_lead_hands_its_address_to_the_new_customer(): void {
        $this->actingAs($this->admin)->post(route('leads.store'), ['company' => 'Neuland AG', 'source' => 'web'] + self::ADDRESS);
        $lead = Lead::query()->firstOrFail();

        $customer = app(LeadService::class)->convert($lead, $this->admin);

        $this->assertSame(['Hauptstraße 1', '12345 Musterstadt'], $customer->postalAddressLines());
        $this->assertSame(ContactAddress::KIND_BILLING, $customer->primaryAddress()?->kind);
        $this->assertSame('Musterstadt', $customer->refresh()->address_city, 'Projektion am Kunden');
    }

    public function test_foreign_customer_keeps_country_inline_and_promotion_carries_the_address(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->post(route('foreign-customers.store'), [
            'customer_id' => $customer->sqid,
            'name' => 'Endkunde Meier',
            'country' => 'at',
        ] + self::ADDRESS)->assertRedirect();

        $foreign = ForeignCustomer::query()->firstOrFail();
        $this->assertSame('AT', $foreign->country);
        $this->assertSame('AT', $foreign->primaryAddress()?->country_code);
        $this->assertSame(['Hauptstraße 1', '12345 Musterstadt'], $foreign->postalAddressLines());

        $this->actingAs($this->admin)->post(route('foreign-customers.promote', $foreign))->assertRedirect();

        $promoted = Customer::query()->where('name', 'Endkunde Meier')->firstOrFail();
        $this->assertSame(['Hauptstraße 1', '12345 Musterstadt'], $promoted->postalAddressLines());
    }

    public function test_application_anonymisation_removes_the_address(): void {
        $this->actingAs($this->admin)->post(route('recruiting.applications.store'), [
            'candidate_name' => 'Erika Muster',
            'source' => 'website',
        ] + self::ADDRESS);

        $application = JobApplication::query()->firstOrFail();
        $this->assertSame(['Hauptstraße 1', '12345 Musterstadt'], $application->postalAddressLines());

        app(RecruitingService::class)->anonymize($application, $this->admin);
        $this->assertSame(0, $application->addresses()->count());
    }

    public function test_hard_delete_removes_satellites_soft_delete_keeps_them(): void {
        $lead = Lead::query()->create(['organization_id' => $this->organization->id, 'company' => 'Weg AG', 'source' => 'web', 'status' => 'new']);
        $lead->addresses()->create(['organization_id' => $this->organization->id, 'kind' => ContactAddress::KIND_DEFAULT, 'city' => 'Irgendwo', 'is_primary' => true]);

        $member = app(ClubMemberService::class)->create($this->organization, $this->admin, ['first_name' => 'Ben', 'last_name' => 'Muster'] + self::ADDRESS);

        $lead->delete();
        $member->delete();

        $this->assertSame(0, ContactAddress::query()->where('addressable_type', 'leads')->count());
        $this->assertSame(1, ContactAddress::query()->where('addressable_type', 'club_members')->count());

        ClubMember::withTrashed()->findOrFail($member->id)->forceDelete();
        $this->assertSame(0, ContactAddress::query()->where('addressable_type', 'club_members')->count());
    }
}

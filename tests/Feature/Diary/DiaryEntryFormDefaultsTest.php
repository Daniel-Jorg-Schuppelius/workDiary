<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntryFormDefaultsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Diary;

use App\Enums\Diary\Mode;
use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Vorbelegungen des Auftragsdialogs (Pilot 2026-11-03): Einsatzort aus der
 * Kundenanschrift und Einsatzdatum ohne Angabe — im Formular als Vorschlag,
 * im Request als Rückfall für fordernde Eintragstypen.
 */
class DiaryEntryFormDefaultsTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    private EntryType $serviceType;

    protected function setUp(): void {
        parent::setUp();
        // Mittag UTC: der Ortstag (Europe/Berlin) ist derselbe wie der UTC-Tag.
        Carbon::setTestNow('2030-03-10 12:00:00');
        $this->admin = User::factory()->admin()->create();
        $this->serviceType = EntryType::factory()->service()->create(['organization_id' => $this->admin->organization_id, 'slug' => 'einsatz']);
    }

    /** @param array<string, mixed> $overrides */
    private function customer(array $overrides = []): Customer {
        return Customer::factory()->create($overrides + [
            'organization_id' => $this->admin->organization_id,
            'name' => 'Musterkunde GmbH',
            'address_street' => 'Musterweg 7',
            'address_zip' => '12345',
            'address_city' => 'Musterstadt',
            'country' => 'de',
            'address_lat' => '52.5200000',
            'address_lng' => '13.4050000',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Customer $customer, array $overrides = []): array {
        return $overrides + [
            'content' => 'Einsatz beim Kunden',
            'status' => 2,
            'mode' => Mode::Deadline->value,
            'due_date' => '2030-03-20',
            'entry_type_id' => $this->serviceType->sqid,
            'customer_id' => $customer->sqid,
            'scheduled_for' => '2030-03-18',
        ];
    }

    public function test_create_form_carries_customer_address_and_todays_date(): void {
        $customer = $this->customer();

        $content = (string) $this->actingAs($this->admin)->get(route('diary.create'))->assertOk()->getContent();

        $this->assertStringContainsString('data-address-line="Musterweg 7"', $content);
        $this->assertStringContainsString('data-address-city="Musterstadt"', $content);
        $this->assertStringContainsString('data-address-lat="52.5200000"', $content);
        $this->assertStringContainsString($customer->sqid, $content);
        $this->assertStringContainsString('data-scheduled-default="2030-03-10"', $content);
        $this->assertMatchesRegularExpression('/name="scheduled_for"[^>]*value="2030-03-10"/', $content);
        $this->assertStringContainsString('data-customer="Anschrift des Kunden übernommen', $content);
    }

    public function test_edit_form_does_not_propose_a_date_for_an_existing_entry(): void {
        $customer = $this->customer();
        $this->actingAs($this->admin)->post(route('diary.store'), $this->payload($customer, ['scheduled_for' => '']))->assertSessionHasNoErrors();
        $entry = \App\Models\Diary\DiaryEntry::query()->firstOrFail();

        $content = (string) $this->actingAs($this->admin)->get(route('diary.edit', $entry))->assertOk()->getContent();

        $this->assertStringContainsString('data-is-edit="1"', $content);
        $this->assertStringContainsString('data-scheduled-default=""', $content);
    }

    public function test_store_copies_customer_address_when_type_requires_one(): void {
        $customer = $this->customer();

        $this->actingAs($this->admin)
            ->post(route('diary.store'), $this->payload($customer))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diary_entries', [
            'customer_id' => $customer->id,
            'address_line' => 'Musterweg 7',
            'address_zip' => '12345',
            'address_city' => 'Musterstadt',
            'address_country' => 'DE',
            'address_lat' => '52.5200000',
            'address_lng' => '13.4050000',
        ]);
    }

    public function test_store_keeps_an_own_address_over_the_customer_address(): void {
        $customer = $this->customer();

        $this->actingAs($this->admin)
            ->post(route('diary.store'), $this->payload($customer, [
                'address_line' => 'Baustelle 3',
                'address_city' => 'Anderswo',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diary_entries', [
            'address_line' => 'Baustelle 3',
            'address_city' => 'Anderswo',
            'address_zip' => null,
            'address_lat' => null,
        ]);
    }

    public function test_store_still_requires_an_address_when_the_customer_has_none(): void {
        $customer = $this->customer(['address_street' => null, 'address_zip' => null, 'address_city' => null]);

        $this->actingAs($this->admin)
            ->post(route('diary.store'), $this->payload($customer))
            ->assertSessionHasErrors(['address_line', 'address_city']);
    }

    public function test_store_copies_the_address_without_coordinates_when_the_customer_has_only_one(): void {
        $customer = $this->customer(['address_lng' => null]);

        $this->actingAs($this->admin)
            ->post(route('diary.store'), $this->payload($customer))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diary_entries', ['address_line' => 'Musterweg 7', 'address_lat' => null, 'address_lng' => null]);
    }

    public function test_store_defaults_the_schedule_date_to_today(): void {
        $customer = $this->customer();

        $this->actingAs($this->admin)
            ->post(route('diary.store'), $this->payload($customer, ['scheduled_for' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diary_entries', ['scheduled_for' => '2030-03-10 00:00:00']);
    }

    public function test_store_derives_the_schedule_date_from_a_fixed_start_in_local_time(): void {
        $customer = $this->customer();

        // 00:30 Ortszeit (Europe/Berlin) liegt in UTC noch am Vortag — der Einsatz gehört zum Ortstag.
        $this->actingAs($this->admin)
            ->post(route('diary.store'), $this->payload($customer, [
                'mode' => Mode::Fixed->value,
                'start_at' => '2030-03-16T00:30',
                'end_at' => '2030-03-16T02:00',
                'scheduled_for' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diary_entries', ['scheduled_for' => '2030-03-16 00:00:00', 'start_at' => '2030-03-15 23:30:00']);
    }

    public function test_store_leaves_the_schedule_date_empty_when_the_type_does_not_require_one(): void {
        $customer = $this->customer();
        $plainType = EntryType::factory()->create(['organization_id' => $this->admin->organization_id, 'requires_schedule' => false, 'requires_address' => false]);

        $this->actingAs($this->admin)
            ->post(route('diary.store'), $this->payload($customer, ['entry_type_id' => $plainType->sqid, 'scheduled_for' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diary_entries', ['entry_type_id' => $plainType->id, 'scheduled_for' => null, 'address_line' => null]);
    }
}

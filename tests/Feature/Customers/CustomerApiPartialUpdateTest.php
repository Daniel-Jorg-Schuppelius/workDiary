<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerApiPartialUpdateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1724: Ein API-PUT ändert nur gesendete Felder. Ansprechpartner, Währung,
 * Schalter und Anfahrt bleiben ohne Angabe stehen; neu angelegt gelten die
 * Spaltenvorgaben. Das Formular sendet Haken (verstecktes 0) und Ansprechpartner
 * (mindestens eine geleerte Zeile) immer und leert damit weiter ausdrücklich.
 */
final class CustomerApiPartialUpdateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private const array PERSON = ['first_name' => 'Erika', 'last_name' => 'Beispiel', 'email' => 'erika@example.test', 'primary' => true];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_put_without_fields_keeps_persons_currency_flags_and_travel(): void {
        $customer = $this->customer();
        Sanctum::actingAs($this->admin, ['*']);

        $this->putJson(route('api.customers.update', $customer), ['name' => 'Beispiel Handel GmbH'])->assertOk();

        $customer->refresh();
        $this->assertSame('Beispiel Handel GmbH', $customer->name);
        $this->assertCount(1, (array) $customer->contact_persons);
        $this->assertSame('CHF', $customer->currency->value);
        $this->assertFalse($customer->billable);
        $this->assertTrue($customer->no_bulk_mail);
        $this->assertTrue($customer->survey_opt_out);
        $this->assertTrue($customer->exclude_from_reports);
        $this->assertTrue($customer->is_reverse_charge_recipient);
        $this->assertSame('flat', $customer->travel_settings['mode'] ?? null);
    }

    public function test_put_with_empty_list_clears_the_persons(): void {
        $customer = $this->customer();
        Sanctum::actingAs($this->admin, ['*']);

        $this->putJson(route('api.customers.update', $customer), ['name' => 'Beispiel Handel GmbH', 'contact_persons' => [], 'billable' => true])->assertOk();

        $customer->refresh();
        $this->assertSame([], (array) $customer->contact_persons);
        $this->assertTrue($customer->billable, 'Gesendete Schalter gelten weiter.');
    }

    public function test_post_without_flags_uses_the_column_defaults(): void {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson(route('api.customers.store'), ['name' => 'Neukunde GmbH'])->assertCreated();

        $customer = Customer::query()->where('name', 'Neukunde GmbH')->firstOrFail();
        $this->assertTrue($customer->billable, 'Neu angelegt ist ein Kunde abrechenbar wie im Formular.');
        $this->assertSame('EUR', $customer->currency->value);
        $this->assertFalse($customer->no_bulk_mail);
    }

    public function test_consumer_without_reverse_charge_flag_drops_it(): void {
        $customer = $this->customer();
        Sanctum::actingAs($this->admin, ['*']);

        $this->putJson(route('api.customers.update', $customer), ['name' => 'Beispiel Handel GmbH', 'is_consumer' => true])->assertOk();

        $customer->refresh();
        $this->assertTrue($customer->is_consumer);
        $this->assertFalse($customer->is_reverse_charge_recipient, 'Ein Verbraucher schuldet nie nach § 13b.');
    }

    public function test_dialog_clears_persons_and_unchecked_flags(): void {
        $customer = $this->customer();

        // Wie das Formular: eine geleerte Zeile, nicht angehakte Schalter als verstecktes 0.
        $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name' => 'Beispiel Handel GmbH',
            'currency' => 'CHF',
            'contact_persons' => [['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'primary' => '0']],
            'billable' => '0',
            'no_bulk_mail' => '0',
            'survey_opt_out' => '0',
            'exclude_from_reports' => '0',
            'is_reverse_charge_recipient' => '0',
            'travel_settings' => ['mode' => '', 'km_source' => '', 'flat_amount' => '', 'rate_per_km' => ''],
        ])->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame([], (array) $customer->contact_persons);
        $this->assertFalse($customer->no_bulk_mail);
        $this->assertFalse($customer->is_reverse_charge_recipient);
        $this->assertNull($customer->travel_settings, 'Leere Anfahrt heißt: Vorgabe erben.');
    }

    private function customer(): Customer {
        return Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Beispiel Handel GmbH',
            'currency' => 'CHF',
            'contact_persons' => [self::PERSON],
            'billable' => false,
            'no_bulk_mail' => true,
            'survey_opt_out' => true,
            'exclude_from_reports' => true,
            'is_reverse_charge_recipient' => true,
            'is_consumer' => false,
            'travel_settings' => ['mode' => 'flat', 'flat_amount' => '25'],
        ]);
    }
}

<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeitwegIdCheckTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\EInvoiceRecipientProfile;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Invoicing\EInvoice\{LeitwegIdCheck, XRechnungGenerator};
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1687: Die Leitweg-ID wird nach der KoSIT-Formatspezifikation geprüft
 * (php-erechnung-toolkit `LeitwegId`). Im Kundendialog und vor dem Versand ist
 * eine ungültige nur eine Warnung — BT-10 bleibt für private Kunden frei.
 */
final class LeitwegIdCheckTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string VALID = '04011000-12345-03';

    private const string WRONG_CHECK_DIGIT = '04011000-12345-67';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_the_customer_dialog_stores_a_leitweg_id_normalized_and_warns_about_a_wrong_check_digit(): void {
        $this->post(route('customers.store'), ['name' => 'Stadtverwaltung Beispiel', 'currency' => 'EUR', 'buyer_reference' => ' 04011000–12345–67 '])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', (string) __('invoicing.buyer_reference_leitweg_invalid', [
                'reason' => (string) __('invoicing.leitweg_reason.check_digits', ['given' => '67', 'expected' => '03']),
            ]));

        $customer = Customer::query()->where('name', 'Stadtverwaltung Beispiel')->sole();
        $this->assertSame(self::WRONG_CHECK_DIGIT, $customer->buyer_reference);

        $this->put(route('customers.update', $customer), ['name' => 'Stadtverwaltung Beispiel', 'currency' => 'EUR', 'buyer_reference' => '04011000-12345-03'])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('warning');
        $this->assertSame(self::VALID, $customer->refresh()->buyer_reference);
    }

    public function test_a_private_reference_stays_as_entered_without_a_warning(): void {
        $this->post(route('customers.store'), ['name' => 'Beispiel GmbH', 'currency' => 'EUR', 'buyer_reference' => 'Bestellung 4711-a'])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('warning');

        $this->assertSame('Bestellung 4711-a', Customer::query()->where('name', 'Beispiel GmbH')->sole()->buyer_reference);
    }

    public function test_for_a_public_sector_customer_every_reference_counts_as_leitweg_id(): void {
        $this->post(route('customers.store'), [
            'name' => 'Landesamt Beispiel',
            'currency' => 'EUR',
            'einvoice_recipient_profile' => EInvoiceRecipientProfile::PublicSector->value,
            'buyer_reference' => 'abc 123',
        ])->assertSessionHasNoErrors()->assertSessionHas('warning');

        $this->assertSame('ABC123', Customer::query()->where('name', 'Landesamt Beispiel')->sole()->buyer_reference);
    }

    public function test_the_preflight_warns_about_an_invalid_leitweg_id_of_a_public_sector_recipient_addressed_by_email(): void {
        $generator = app(XRechnungGenerator::class);
        $warning = (string) __('invoicing.einvoice.warning.invalid_leitweg_id', [
            'terms' => 'BT-10',
            'reason' => (string) __('invoicing.leitweg_reason.check_digits', ['given' => '67', 'expected' => '03']),
        ]);

        $public = $this->issued($this->customer(self::WRONG_CHECK_DIGIT, ['einvoice_recipient_profile' => EInvoiceRecipientProfile::PublicSector]));
        $this->assertContains($warning, $generator->preflight($public)['warnings']);
        $this->assertSame([], $generator->preflight($public)['errors']);

        // Gegenprobe: gültige Leitweg-ID, oder ein privater Kunde mit derselben Referenz — keine Warnung.
        $valid = $this->issued($this->customer(self::VALID, ['einvoice_recipient_profile' => EInvoiceRecipientProfile::PublicSector]));
        $this->assertSame([], $this->leitwegWarnings($generator->preflight($valid)['warnings']));
        $private = $this->issued($this->customer(self::WRONG_CHECK_DIGIT));
        $this->assertSame([], $this->leitwegWarnings($generator->preflight($private)['warnings']));
    }

    public function test_the_preflight_warns_when_a_peppol_recipient_with_scheme_0204_carries_an_invalid_leitweg_id(): void {
        $invoice = $this->issued($this->customer(self::WRONG_CHECK_DIGIT, ['peppol_participant_id' => '0204:' . self::VALID]));

        $this->assertSame([(string) __('invoicing.einvoice.warning.invalid_leitweg_id', [
            'terms' => 'BT-10',
            'reason' => (string) __('invoicing.leitweg_reason.check_digits', ['given' => '67', 'expected' => '03']),
        ])], $this->leitwegWarnings(app(XRechnungGenerator::class)->preflight($invoice)['warnings']));

        // Dieselbe ungültige Kennung in BT-10 und BT-49 ergibt eine Warnung.
        $same = $this->issued($this->customer(self::WRONG_CHECK_DIGIT, ['peppol_participant_id' => '0204:' . self::WRONG_CHECK_DIGIT]));
        $this->assertCount(1, $this->leitwegWarnings(app(XRechnungGenerator::class)->preflight($same)['warnings']));
        $this->assertStringContainsString('BT-10, BT-49', $this->leitwegWarnings(app(XRechnungGenerator::class)->preflight($same)['warnings'])[0]);
    }

    public function test_the_reason_names_form_address_or_check_digits_in_the_interface_language(): void {
        $this->assertNull(LeitwegIdCheck::problem('04011000-12345-03'));
        $this->assertSame((string) __('invoicing.leitweg_reason.form'), LeitwegIdCheck::problem('Bestellung 4711'));
        // Kennzahl 17 gibt es nicht; Grobadressierung mit 4 Ziffern auch nicht.
        $this->assertSame((string) __('invoicing.leitweg_reason.structure'), LeitwegIdCheck::problem('17011000-12345-03'));
        $this->assertSame((string) __('invoicing.leitweg_reason.structure'), LeitwegIdCheck::problem('0401-12345-03'));

        app()->setLocale('en');
        $this->assertSame('The check digits 67 are wrong, expected 03.', LeitwegIdCheck::problem('04011000-12345-67'));
    }

    /**
     * @param list<string> $warnings
     * @return list<string>
     */
    private function leitwegWarnings(array $warnings): array {
        $prefix = strstr((string) __('invoicing.einvoice.warning.invalid_leitweg_id', ['terms' => '§', 'reason' => '']), '§', true);

        return array_values(array_filter($warnings, static fn (string $warning): bool => str_starts_with($warning, (string) $prefix)));
    }

    /** @param array<string, mixed> $attributes */
    private function customer(string $buyerReference, array $attributes = []): Customer {
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Beispieltechnik GmbH', 'street' => 'Musterstraße 1', 'zip' => '12345', 'city' => 'Berlin', 'country' => 'DE',
            'vat_id' => 'DE136695976', 'contact_name' => 'Max Muster', 'contact_email' => 'rechnung@beispieltechnik.example',
            'contact_phone' => '+49 30 123456', 'iban' => 'DE89370400440532013000', 'bic' => 'COBADEFFXXX',
        ]]]);

        return Customer::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Kunde Beispiel',
            'currency' => 'EUR',
            'email' => 'rechnung@kunde.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Kundenstadt',
            'country' => 'DE',
            'buyer_reference' => $buyerReference,
            'created_by' => $this->admin->id,
        ]);
    }

    private function issued(Customer $customer): Invoice {
        $invoice = app(InvoiceGenerator::class)->emptyDraft($customer);
        $invoice->items()->create([
            'organization_id' => $this->organization->id, 'description' => 'Wartung', 'quantity' => '2', 'unit' => 'Std.',
            'unit_price' => '80.00', 'service_date' => '2026-10-15', 'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return app(InvoiceIssueService::class)->issue($invoice->refresh())->refresh();
    }
}

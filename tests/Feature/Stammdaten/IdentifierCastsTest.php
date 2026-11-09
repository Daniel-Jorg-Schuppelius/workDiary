<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdentifierCastsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Stammdaten;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Article\Article;
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Finance\Gdpdu\CustomersSection;
use App\Support\Crypto\BlindIndex;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Crypt, DB};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kennungs-Casts an den Stammdaten (MVP-1512): Schreiben normalisiert, Lesen
 * liefert den gespeicherten Text, ungültiger Altbestand bleibt lesbar, die
 * Projektion und die Verschlüsselung bleiben, das Protokoll schwärzt weiter.
 */
class IdentifierCastsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beispiel Werkstatt GmbH']);
    }

    public function test_bank_account_is_stored_canonical_encrypted_and_projected(): void {
        $account = $this->customer->bankAccounts()->create([
            'organization_id' => $this->organization->id,
            'account_holder' => 'Beispiel Werkstatt GmbH',
            'iban' => 'de89 3704 0044 0532 0130 00',
            'bic' => ' cobadeffxxx ',
            'is_primary' => true,
        ]);

        $this->assertSame('DE89370400440532013000', $account->fresh()->iban);
        $this->assertSame('COBADEFFXXX', $account->fresh()->bic);
        $raw = (string) DB::table('contact_bank_accounts')->where('id', $account->id)->value('iban');
        $this->assertStringNotContainsString('DE89', $raw, 'at rest verschlüsselt');
        $this->assertSame('DE89370400440532013000', Crypt::decryptString($raw));
        $this->assertSame(BlindIndex::ofIban('DE89370400440532013000'), $account->fresh()->iban_hash);

        // Projektion an den Kunden: gleicher Klartext, weiter verschlüsselt.
        $customer = $this->customer->fresh();
        $this->assertSame('DE89370400440532013000', $customer->bank_iban);
        $this->assertSame('COBADEFFXXX', $customer->bank_bic);
        $this->assertSame('DE89370400440532013000', Crypt::decryptString((string) DB::table('customers')->where('id', $customer->id)->value('bank_iban')));

        // Unverändert gesetzt ist keine Änderung, obwohl jedes Chiffrat anders aussieht.
        $customer->bank_iban = 'DE89370400440532013000';
        $this->assertFalse($customer->isDirty('bank_iban'));
    }

    public function test_master_data_identifiers_are_normalised_on_write(): void {
        $this->customer->update(['vat_id' => ' de 811907980 ', 'tax_number' => '151 / 815 / 08156']);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'gtin' => ' 4006381333931 ']);
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id, 'tax_identification_number' => '86 095 742 719']);

        $this->assertSame('DE811907980', $this->customer->fresh()->vat_id);
        $this->assertSame('151/815/08156', $this->customer->fresh()->tax_number);
        $this->assertSame('4006381333931', $article->fresh()->gtin);
        $this->assertSame('86095742719', $user->fresh()->tax_identification_number);
        $this->assertSame('86095742719', Crypt::decryptString((string) DB::table('users')->where('id', $user->id)->value('tax_identification_number')));
    }

    public function test_invalid_and_legacy_values_stay_readable_and_exports_keep_them(): void {
        DB::table('customers')->where('id', $this->customer->id)->update(['vat_id' => 'de 811 907 980', 'tax_number' => 'KEINE ANGABE']);
        $this->customer->update(['company' => 'Beispiel Werkstatt']);
        // Ungültige Eingabe: nur getrimmt, nicht verworfen.
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'vat_id' => ' DE123 ']);

        $customer = $this->customer->fresh();
        $this->assertSame('de 811 907 980', $customer->vat_id, 'Altbestand bleibt, wie er ist');
        $this->assertSame('KEINE ANGABE', $customer->tax_number);
        $this->assertSame('DE123', $other->fresh()->vat_id);

        Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'status' => InvoiceStatus::Issued, 'issued_on' => '2030-03-05']);
        $rows = iterator_to_array((new CustomersSection)->rows($this->organization, Carbon::parse('2030-03-01'), Carbon::parse('2030-03-31')), false);
        $this->assertCount(1, $rows);
        $this->assertSame('de 811 907 980', $rows[0][3], 'Export liest den gespeicherten Wert');
        $this->assertSame('KEINE ANGABE', $rows[0][4]);
    }

    public function test_audit_log_keeps_redacting_the_encrypted_identifier(): void {
        $this->customer->update(['bank_iban' => 'DE02 1203 0000 0000 2020 51', 'vat_id' => 'DE811907980']);

        $entry = AuditLog::query()
            ->where('auditable_type', MorphMap::stableKey(Customer::class))
            ->where('auditable_id', $this->customer->id)
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(AuditLog::REDACTED, $entry->changes['after']['bank_iban'] ?? null);
        $this->assertSame('DE811907980', $entry->changes['after']['vat_id'] ?? null);
        $this->assertStringNotContainsString('DE02120300000000202051', (string) DB::table('audit_logs')->where('id', $entry->id)->value('changes'));
    }
}

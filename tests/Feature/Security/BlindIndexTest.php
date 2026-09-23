<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BlindIndexTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\Customer\Customer;
use App\Models\Finance\BankAccount;
use App\Models\Platform\Organization;
use App\Support\Crypto\BlindIndex;
use CommonToolkit\Helper\Data\BankHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-13: Neben den verschlüsselten Feldern (IBAN,
 * E-Mail, Durchwahl) stand ein Nachschlage-Abdruck als **ungesalzenes**
 * SHA-256. Bei diesen Werten ist das keine Einbahnstraße: Der Raum ist klein
 * und strukturiert — eine deutsche IBAN hat 22 Zeichen mit fester Prüfsumme,
 * eine Durchwahl drei bis fünf Ziffern. Wer die Datenbank liest, rechnet die
 * Abdrücke zurück und die Verschlüsselung daneben ist wertlos.
 */
class BlindIndexTest extends TestCase {
    use RefreshDatabase;

    private const IBAN = 'DE89370400440532013000';

    private function account(Organization $org, string $iban): BankAccount {
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        return BankAccount::query()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'label' => 'Geschäftskonto',
            'iban' => $iban,
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Muster GmbH',
        ]);
    }

    public function test_stored_fingerprint_is_not_a_plain_hash_of_the_value(): void {
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);

        $account = $this->account($org, self::IBAN);

        $this->assertNotSame(
            (string) BankHelper::hashIBAN(self::IBAN),
            $account->iban_hash,
            'Der Abdruck darf nicht der ungeschlüsselte SHA-256 der IBAN sein.',
        );
        $this->assertSame((string) BlindIndex::ofIban(self::IBAN), $account->iban_hash);
        $this->assertSame(64, strlen((string) $account->iban_hash));
    }

    public function test_legacy_rows_stay_findable_until_they_are_recomputed(): void {
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);

        $account = $this->account($org, self::IBAN);
        // Bestandszeile: alter, ungeschlüsselter Abdruck.
        DB::table('bank_accounts')->where('id', $account->id)
            ->update(['iban_hash' => (string) BankHelper::hashIBAN(self::IBAN)]);

        $this->assertContains(
            (string) BankHelper::hashIBAN(self::IBAN),
            BlindIndex::ibanCandidates(self::IBAN),
            'Das Doppellesen muss den alten Abdruck weiterhin anbieten.',
        );

        $this->artisan('security:rehash-blind-indexes')->assertSuccessful();

        $this->assertSame(
            (string) BlindIndex::ofIban(self::IBAN),
            (string) DB::table('bank_accounts')->where('id', $account->id)->value('iban_hash'),
            'Der Nachlauf muss die Bestandszeile umrechnen.',
        );
    }

    public function test_the_dry_run_changes_nothing(): void {
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);

        $account = $this->account($org, self::IBAN);
        $legacy = (string) BankHelper::hashIBAN(self::IBAN);
        DB::table('bank_accounts')->where('id', $account->id)->update(['iban_hash' => $legacy]);

        $this->artisan('security:rehash-blind-indexes --dry-run')->assertSuccessful();

        $this->assertSame($legacy, (string) DB::table('bank_accounts')->where('id', $account->id)->value('iban_hash'));
    }

    public function test_a_second_run_for_the_same_key_is_skipped_unless_forced(): void {
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        $account = $this->account($org, self::IBAN);

        $this->artisan('security:rehash-blind-indexes')->assertSuccessful();
        $this->assertTrue(\App\Console\Commands\Security\RehashBlindIndexesCommand::isDoneForCurrentKey());

        // Das Deploy ruft den Befehl bei jedem Update auf. Für denselben
        // Schlüssel darf er nicht erneut jede Zeile entschlüsseln.
        $legacy = (string) BankHelper::hashIBAN(self::IBAN);
        DB::table('bank_accounts')->where('id', $account->id)->update(['iban_hash' => $legacy]);

        $this->artisan('security:rehash-blind-indexes')->expectsOutputToContain('übersprungen')->assertSuccessful();
        $this->assertSame($legacy, (string) DB::table('bank_accounts')->where('id', $account->id)->value('iban_hash'));

        $this->artisan('security:rehash-blind-indexes --force')->assertSuccessful();
        $this->assertSame((string) BlindIndex::ofIban(self::IBAN), (string) DB::table('bank_accounts')->where('id', $account->id)->value('iban_hash'));
    }

    public function test_the_dry_run_does_not_mark_the_key_as_done(): void {
        $this->artisan('security:rehash-blind-indexes --dry-run')->assertSuccessful();

        $this->assertFalse(\App\Console\Commands\Security\RehashBlindIndexesCommand::isDoneForCurrentKey());
    }
}

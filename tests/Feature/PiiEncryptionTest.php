<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PiiEncryptionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{ContactBankAccount, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt, DB};
use Tests\TestCase;

class PiiEncryptionTest extends TestCase {
    use RefreshDatabase;

    public function test_user_tax_fields_are_encrypted_at_rest(): void {
        $user = User::factory()->user()->create();
        $user->forceFill([
            'tax_identification_number' => '86095742719',
            'social_security_number' => '65170539W001',
        ])->save();

        // Über das Model entschlüsselt gelesen.
        $this->assertSame('86095742719', $user->fresh()->tax_identification_number);

        // Roh in der DB liegt KEIN Klartext, aber ein entschlüsselbarer Cipher.
        $raw = DB::table('users')->where('id', $user->id)->value('tax_identification_number');
        $this->assertNotSame('86095742719', $raw);
        $this->assertSame('86095742719', Crypt::decryptString((string) $raw));
    }

    public function test_bank_account_iban_is_encrypted_at_rest(): void {
        $user = User::factory()->user()->create();
        $acct = ContactBankAccount::create([
            'organization_id' => $user->organization_id,
            'accountable_type' => $user->getMorphClass(),
            'accountable_id' => $user->id,
            'account_holder' => 'Max Mustermann',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
        ]);

        $this->assertSame('DE89370400440532013000', $acct->fresh()->iban);

        $raw = DB::table('contact_bank_accounts')->where('id', $acct->id)->value('iban');
        $this->assertNotSame('DE89370400440532013000', $raw);
        $this->assertSame('DE89370400440532013000', Crypt::decryptString((string) $raw));
    }

    public function test_encrypt_existing_command_encrypts_legacy_plaintext(): void {
        $user = User::factory()->user()->create();

        // Klartext direkt in die DB (am Cast vorbei) – simuliert Bestandsdaten.
        DB::table('users')->where('id', $user->id)->update(['social_security_number' => 'PLAINTEXT123']);

        $this->artisan('security:encrypt-existing')->assertSuccessful();

        $raw = DB::table('users')->where('id', $user->id)->value('social_security_number');
        $this->assertNotSame('PLAINTEXT123', $raw);
        $this->assertSame('PLAINTEXT123', $user->fresh()->social_security_number);

        // Idempotent: zweiter Lauf verschlüsselt nicht doppelt.
        $this->artisan('security:encrypt-existing')->assertSuccessful();
        $this->assertSame('PLAINTEXT123', $user->fresh()->social_security_number);
    }
}

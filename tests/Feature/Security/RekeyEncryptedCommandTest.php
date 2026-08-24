<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RekeyEncryptedCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Finance\BankAccount;
use App\Models\{Organization, SystemSetting};
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `security:rekey-encrypted` (Vollscan 2026-08-23, D1): Nach einer
 * APP_KEY-Rotation müssen ALLE verschlüsselten Felder mit dem neuen Schlüssel
 * lesbar sein — auch ohne APP_PREVIOUS_KEYS. Beweisführung: alter Key nur
 * während des Laufs als previous key, danach entfernt.
 */
final class RekeyEncryptedCommandTest extends TestCase {
    use RefreshDatabase;

    public function test_rotated_key_reads_all_fields_after_rekey(): void {
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        $account = BankAccount::factory()->create([
            'organization_id' => $org->id,
            'iban' => 'DE02120300000000202051',
        ]);
        $setting = new SystemSetting(['key' => 'mail.smtp_password']);
        $setting->setResolvedValue('geheim', true);
        $setting->save();

        // Rotation: neuer APP_KEY, alter nur noch als previous key.
        $oldKey = (string) config('app.key');
        $this->useKeys('base64:' . base64_encode(random_bytes(32)), [$oldKey]);

        $this->artisan('security:rekey-encrypted')->assertSuccessful();

        // Der alte Schlüssel verschwindet — alles muss weiter lesbar sein.
        $this->useKeys((string) config('app.key'), []);
        $this->assertSame('DE02120300000000202051', $account->fresh()?->iban);
        $this->assertSame('geheim', SystemSetting::query()->whereKey($setting->id)->firstOrFail()->resolvedValue());
    }

    /** @param list<string> $previous */
    private function useKeys(string $key, array $previous): void {
        config(['app.key' => $key, 'app.previous_keys' => $previous]);
        app()->forgetInstance('encrypter');
        (new EncryptionServiceProvider(app()))->register();
    }
}

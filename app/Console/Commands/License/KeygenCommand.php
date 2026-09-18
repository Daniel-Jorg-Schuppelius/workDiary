<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KeygenCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\License;

use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Console\Command;

class KeygenCommand extends Command {
    protected $signature = 'license:keygen {--out= : Optionaler Pfad, in den die Keys geschrieben werden}';

    protected $description = 'Erzeugt ein Ed25519-Schlüsselpaar für die Lizenzsignierung.';

    public function handle(): int {
        $keypair = sodium_crypto_sign_keypair();
        $secret = sodium_crypto_sign_secretkey($keypair);
        $public = sodium_crypto_sign_publickey($keypair);

        $secretB64 = CryptoHelper::base64UrlEncode($secret);
        $publicB64 = CryptoHelper::base64UrlEncode($public);

        $this->warn('WICHTIG: Den Private Key NIEMALS in die App-Installation einspielen.');
        $this->line('');
        $this->line('LICENSE_PUBLIC_KEY=' . $publicB64);
        $this->line('LICENSE_PRIVATE_KEY=' . $secretB64);

        $out = $this->option('out');
        if (is_string($out) && $out !== '') {
            $payload = "LICENSE_PUBLIC_KEY={$publicB64}\nLICENSE_PRIVATE_KEY={$secretB64}\n";
            // Rechte stehen vor dem Inhalt — der Private Key liegt nie mit umask-Rechten auf der Platte.
            ToolkitFile::write($out, $payload, permissions: 0600, lock: true);
            $this->info('Keys geschrieben nach: ' . $out);
        }

        return self::SUCCESS;
    }
}

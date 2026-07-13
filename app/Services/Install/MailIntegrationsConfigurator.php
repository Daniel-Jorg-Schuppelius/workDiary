<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailIntegrationsConfigurator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Install;

use Minishlink\WebPush\VAPID;

/**
 * Installer-Baustein Mail/Integrationen: MAIL_*-, Lexoffice- und
 * VAPID-Variablen (inkl. Schlüsselerzeugung). Aus dem InstallationManager
 * extrahiert (Refactoring Welle 2, B6b); dieser bleibt die Fassade.
 */
class MailIntegrationsConfigurator {
    public function __construct(private readonly EnvWriter $env) {}

    /**
     * @param  array<string, string|int|null>  $data
     */
    public function configureMail(array $data): void {
        $this->env->ensureFileExists();
        $values = [];
        foreach (
            [
                'mailer' => 'MAIL_MAILER',
                'host' => 'MAIL_HOST',
                'port' => 'MAIL_PORT',
                'username' => 'MAIL_USERNAME',
                'password' => 'MAIL_PASSWORD',
                'scheme' => 'MAIL_SCHEME',
                'from_address' => 'MAIL_FROM_ADDRESS',
                'from_name' => 'MAIL_FROM_NAME',
            ] as $key => $envKey
        ) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $values[$envKey] = (string) $data[$key];
            }
        }

        if ($values !== []) {
            $this->env->setMany($values);
        }
    }

    /**
     * @param  array<string, string|null>  $data
     */
    public function configureIntegrations(array $data): void {
        $this->env->ensureFileExists();
        $values = [];
        foreach (
            [
                'lexoffice_api_key' => 'LEXOFFICE_API_KEY',
                'vapid_public_key' => 'VAPID_PUBLIC_KEY',
                'vapid_private_key' => 'VAPID_PRIVATE_KEY',
                'vapid_subject' => 'VAPID_SUBJECT',
            ] as $key => $envKey
        ) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $values[$envKey] = (string) $data[$key];
            }
        }

        if ($values !== []) {
            $this->env->setMany($values);
        }
    }

    /**
     * Erzeugt ein neues VAPID-Schlüsselpaar für Web-Push. Die Schlüssel werden
     * NICHT persistiert – das übernimmt der Integrations-Schritt, sobald der
     * Anwender das Formular absendet.
     *
     * @return array{publicKey: string, privateKey: string}
     */
    public function generateVapidKeys(): array {
        /** @var array{publicKey: string, privateKey: string} $keys */
        $keys = VAPID::createVapidKeys();

        return $keys;
    }
}

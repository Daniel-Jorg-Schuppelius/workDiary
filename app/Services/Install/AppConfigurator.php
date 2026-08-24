<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppConfigurator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Install;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;

/**
 * Installer-Baustein Anwendungs-Konfiguration: APP_*-Variablen, APP_KEY und
 * SQIDS_SALT (inkl. Laufzeit-Aktivierung). Aus dem InstallationManager
 * extrahiert (Refactoring Welle 2, B6b); dieser bleibt die Fassade.
 */
class AppConfigurator {
    public function __construct(private readonly EnvWriter $env) {}

    /**
     * Setzt grundlegende Anwendungs-Variablen und erzeugt — falls noch nicht
     * vorhanden — einen APP_KEY. Ein bereits gesetzter Key wird NIEMALS
     * überschrieben, da daran verschlüsselte Felder (PluginSetting.settings,
     * SoftwareInstallation.license_key) hängen.
     *
     * @param  array{app_name?: string, app_url?: string, app_env?: string, locale?: string, timezone?: string}  $data
     */
    public function configureApp(array $data): void {
        $this->env->ensureFileExists();

        $values = [];
        if (isset($data['app_name'])) {
            $values['APP_NAME'] = $data['app_name'];
        }
        if (isset($data['app_url'])) {
            $values['APP_URL'] = rtrim($data['app_url'], '/');
        }
        if (isset($data['app_env'])) {
            $values['APP_ENV'] = $data['app_env'];
            $values['APP_DEBUG'] = $data['app_env'] === 'local' ? 'true' : 'false';
            // Produktion: rotierende Tageslogs auf info statt einer einzigen,
            // nie rotierten laravel.log im debug-Level (Vollscan 2026-08-23, J15).
            if ($data['app_env'] === 'production') {
                $values['LOG_STACK'] = 'daily';
                $values['LOG_LEVEL'] = 'info';
            }
        }
        if (isset($data['locale'])) {
            $values['APP_LOCALE'] = $data['locale'];
        }
        if (isset($data['timezone'])) {
            $values['APP_TIMEZONE'] = $data['timezone'];
        }

        if ($values !== []) {
            $this->env->setMany($values);
        }

        $this->ensureAppKey();
        $this->ensureSqidsSalt();
    }

    /**
     * Erzeugt einen APP_KEY, sofern noch keiner gesetzt ist. Lädt den Key in
     * die Laufzeit-Config, damit Folge-Schritte (Session, Verschlüsselung)
     * sofort funktionieren.
     *
     * @return bool true, wenn ein neuer Key erzeugt wurde
     */
    public function ensureAppKey(): bool {
        $this->env->ensureFileExists();

        $current = $this->env->get('APP_KEY');
        if (is_string($current) && $current !== '') {
            $this->applyKeyToRuntime($current);

            return false;
        }

        $key = 'base64:' . base64_encode(Encrypter::generateKey($this->cipher()));
        $this->env->set('APP_KEY', $key);
        $this->applyKeyToRuntime($key);

        return true;
    }

    public function hasAppKey(): bool {
        $current = $this->env->get('APP_KEY');

        return is_string($current) && $current !== '';
    }

    /**
     * Erzeugt einen SQIDS_SALT, sofern noch keiner gesetzt ist. Der Salt geht
     * in die Permutation des Sqids-Alphabets ein; ohne ihn verweigert der
     * SqidEncoder in Produktion den Dienst (RuntimeException). Ein bereits
     * gesetzter Salt wird NIEMALS überschrieben, da daran die öffentlich
     * sichtbaren Route-Keys (Sqids) hängen.
     *
     * @return bool true, wenn ein neuer Salt erzeugt wurde
     */
    public function ensureSqidsSalt(): bool {
        $this->env->ensureFileExists();

        $current = $this->env->get('SQIDS_SALT');
        if (is_string($current) && $current !== '') {
            Config::set('sqids.salt', $current);

            return false;
        }

        return $this->writeSqidsSalt();
    }

    /**
     * Erzeugt einen NEUEN SQIDS_SALT und überschreibt einen vorhandenen Wert.
     * Achtung: Dadurch ändern sich ALLE öffentlich sichtbaren Sqid-Route-Keys;
     * bereits verteilte URLs werden ungültig.
     */
    public function regenerateSqidsSalt(): void {
        $this->env->ensureFileExists();
        $this->writeSqidsSalt();
    }

    private function writeSqidsSalt(): bool {
        $salt = bin2hex(random_bytes(32));
        $this->env->set('SQIDS_SALT', $salt);
        Config::set('sqids.salt', $salt);
        // SqidEncoder-Singleton neu binden, damit der frische Salt sofort greift.
        app()->forgetInstance(\App\Services\SqidEncoder::class);

        return true;
    }

    public function hasSqidsSalt(): bool {
        $current = $this->env->get('SQIDS_SALT');

        return is_string($current) && $current !== '';
    }

    private function applyKeyToRuntime(string $key): void {
        Config::set('app.key', $key);
        // Encrypter-Singleton neu binden, damit Session-/Cookie-Verschlüsselung
        // den frischen Key sofort verwendet.
        app()->forgetInstance('encrypter');
    }

    private function cipher(): string {
        return (string) config('app.cipher', 'AES-256-CBC');
    }
}

<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SerialPassportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Inventory;

use App\Models\Organization;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Str;

/**
 * Zugangstoken des öffentlichen Geräte-Passes (Feature 047/048, E2).
 *
 * Der Token öffnet eine Seite OHNE Anmeldung — er ist damit ein Zugangsmittel
 * und wird wie eines behandelt (Sicherheitsscan 2026-08-23, S-44):
 *
 * - Gespeichert wird nur der SHA-256-Abdruck. Wer die Datenbank liest, kann den
 *   Pass danach nicht mehr öffnen; ein Sicherungsband gibt ihn nicht mehr her.
 * - Der Klartext existiert genau einmal, im Moment der Ausstellung.
 * - Ein Hinweis (erste sechs Zeichen) bleibt sichtbar, damit ein umlaufender
 *   Link einem ausgestellten Token zugeordnet werden kann.
 * - Ausstellen ist zugleich Rotieren: der alte Abdruck wird überschrieben, alte
 *   Links laufen sofort ins Leere.
 */
class SerialPassportService {
    public const HASH_KEY = 'serial_passport_token_hash';

    public const HINT_KEY = 'serial_passport_token_hint';

    public const ISSUED_KEY = 'serial_passport_token_issued_at';

    public const ENABLED_KEY = 'serial_passport_enabled';

    /** Stellt einen neuen Token aus und gibt ihn EINMAL im Klartext zurück. */
    public function issue(Organization $organization): string {
        $token = Str::lower(Str::random(40));

        $this->write($organization, [
            self::HASH_KEY => self::fingerprint($token),
            self::HINT_KEY => mb_substr($token, 0, 6),
            self::ISSUED_KEY => now()->toIso8601String(),
        ]);

        return $token;
    }

    /** Entzieht den Zugang: Abdruck weg, Freischaltung aus. */
    public function revoke(Organization $organization): void {
        $this->write($organization, [
            self::HASH_KEY => null,
            self::HINT_KEY => null,
            self::ISSUED_KEY => null,
            self::ENABLED_KEY => false,
        ]);
    }

    /** Schaltet die öffentliche Seite frei — ohne Token bleibt sie zu. */
    public function setEnabled(Organization $organization, bool $enabled): void {
        $settings = (array) ($organization->settings ?? []);
        if ($enabled && ($settings[self::HASH_KEY] ?? null) === null) {
            return;
        }

        $this->write($organization, [self::ENABLED_KEY => $enabled]);
    }

    /** @return array{enabled: bool, issued: bool, hint: ?string, issued_at: ?string} */
    public function status(Organization $organization): array {
        $settings = (array) ($organization->settings ?? []);
        $hash = $settings[self::HASH_KEY] ?? null;

        return [
            'enabled' => (bool) ($settings[self::ENABLED_KEY] ?? false),
            'issued' => is_string($hash) && $hash !== '',
            'hint' => is_string($settings[self::HINT_KEY] ?? null) ? $settings[self::HINT_KEY] : null,
            'issued_at' => is_string($settings[self::ISSUED_KEY] ?? null) ? $settings[self::ISSUED_KEY] : null,
        ];
    }

    /**
     * Löst einen Klartext-Token zur Organisation auf — die einzige Stelle, die
     * das darf. Nicht freigeschaltete Organisationen liefern nichts.
     */
    public function resolve(string $token): ?Organization {
        $fingerprint = self::fingerprint($token);
        if ($fingerprint === null) {
            return null;
        }

        // TENANT-BYPASS: Auflösung ohne Anmeldung, ausschließlich über den Abdruck.
        $organization = Organization::query()->withoutGlobalScopes()
            ->where('settings->' . self::HASH_KEY, $fingerprint)
            ->first();

        if (! $organization instanceof Organization) {
            return null;
        }

        return (bool) data_get($organization->settings, self::ENABLED_KEY) ? $organization : null;
    }

    /** Abdruck eines Tokens — deterministisch, damit die URL auflösbar bleibt. */
    public static function fingerprint(string $token): ?string {
        return $token === '' ? null : CryptoHelper::hash($token);
    }

    /** @param  array<string, mixed>  $values */
    private function write(Organization $organization, array $values): void {
        $settings = (array) ($organization->settings ?? []);
        foreach ($values as $key => $value) {
            if ($value === null) {
                unset($settings[$key]);

                continue;
            }
            $settings[$key] = $value;
        }

        // Der Klartext aus der Zeit vor S-44 verschwindet bei jeder Änderung mit.
        unset($settings['serial_passport_token']);

        $organization->forceFill(['settings' => $settings])->save();
    }
}

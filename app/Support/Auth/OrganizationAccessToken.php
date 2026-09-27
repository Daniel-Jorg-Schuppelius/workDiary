<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationAccessToken.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\Platform\Organization;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Str;

/**
 * Gemeinsame Basis öffentlicher Zugangstoken einer Organisation
 * (Sicherheitsscan 2026-08-23, S-44; Geräte-Pass, Kalender-Feed, Statusseite):
 *
 * - Gespeichert wird nur der SHA-256-Abdruck in `organizations.settings`.
 * - Der Klartext existiert genau einmal, im Moment der Ausstellung.
 * - Ein Hinweis (erste sechs Zeichen) bleibt sichtbar, damit ein umlaufender
 *   Link einem ausgestellten Token zugeordnet werden kann.
 * - Ausstellen ist zugleich Rotieren; alte Links laufen sofort ins Leere.
 * - Mit `ENABLED_KEY` braucht die Seite zusätzlich eine Freischaltung,
 *   ohne ist der Token selbst die Freischaltung.
 */
abstract class OrganizationAccessToken {
    public const HASH_KEY = '';

    public const HINT_KEY = '';

    public const ISSUED_KEY = '';

    /** Leer = keine gesonderte Freischaltung. */
    public const ENABLED_KEY = '';

    protected const TOKEN_LENGTH = 40;

    /** Nur aktive Organisationen auflösen. */
    protected const ACTIVE_ONLY = false;

    /** Stellt einen neuen Token aus und gibt ihn EINMAL im Klartext zurück. */
    public function issue(Organization $organization): string {
        $token = Str::lower(Str::random(static::TOKEN_LENGTH));
        $this->write($organization, [
            static::HASH_KEY => self::fingerprint($token),
            static::HINT_KEY => mb_substr($token, 0, 6),
            static::ISSUED_KEY => now()->toIso8601String(),
        ]);

        return $token;
    }

    /** Entzieht den Zugang: Abdruck weg, Freischaltung aus. */
    public function revoke(Organization $organization): void {
        $values = [static::HASH_KEY => null, static::HINT_KEY => null, static::ISSUED_KEY => null];
        if (static::ENABLED_KEY !== '') {
            $values[static::ENABLED_KEY] = false;
        }
        $this->write($organization, $values);
    }

    /** Schaltet die öffentliche Seite frei — ohne Token bleibt sie zu. */
    public function setEnabled(Organization $organization, bool $enabled): void {
        if (static::ENABLED_KEY === '' || ($enabled && ! $this->status($organization)['issued'])) {
            return;
        }
        $this->write($organization, [static::ENABLED_KEY => $enabled]);
    }

    /** @return array{enabled: bool, issued: bool, hint: ?string, issued_at: ?string} */
    public function status(Organization $organization): array {
        $settings = (array) ($organization->settings ?? []);
        $hash = $settings[static::HASH_KEY] ?? null;
        $issued = is_string($hash) && $hash !== '';

        return [
            'enabled' => static::ENABLED_KEY === '' ? $issued : (bool) ($settings[static::ENABLED_KEY] ?? false),
            'issued' => $issued,
            'hint' => is_string($settings[static::HINT_KEY] ?? null) ? $settings[static::HINT_KEY] : null,
            'issued_at' => is_string($settings[static::ISSUED_KEY] ?? null) ? $settings[static::ISSUED_KEY] : null,
        ];
    }

    /** Löst einen Klartext-Token zur Organisation auf — die einzige Stelle, die das darf. */
    public function resolve(string $token): ?Organization {
        $fingerprint = self::fingerprint($token);
        if ($fingerprint === null) {
            return null;
        }

        // TENANT-BYPASS: Auflösung ohne Anmeldung, ausschließlich über den Abdruck.
        $organization = Organization::query()->withoutGlobalScopes()
            ->where('settings->' . static::HASH_KEY, $fingerprint)
            ->when(static::ACTIVE_ONLY, fn ($q) => $q->where('is_active', true))
            ->first();
        if (! $organization instanceof Organization) {
            return null;
        }

        return static::ENABLED_KEY === '' || (bool) data_get($organization->settings, static::ENABLED_KEY) ? $organization : null;
    }

    /** Abdruck eines Tokens — deterministisch, damit die Adresse auflösbar bleibt. */
    public static function fingerprint(string $token): ?string {
        return $token === '' ? null : CryptoHelper::hash($token);
    }

    /**
     * Einstellungen, die bei jeder Änderung mit verschwinden (Altlasten).
     *
     * @return list<string>
     */
    protected function obsoleteKeys(): array {
        return [];
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
        foreach ($this->obsoleteKeys() as $key) {
            unset($settings[$key]);
        }
        $organization->forceFill(['settings' => $settings])->save();
    }
}

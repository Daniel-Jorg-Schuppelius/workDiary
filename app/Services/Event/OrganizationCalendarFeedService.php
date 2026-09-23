<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationCalendarFeedService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Event;

use App\Models\Platform\Organization;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Str;

/**
 * Zugangstoken für den gemeinsamen Kalender-Feed einer Organisation
 * (Mandanten-Review 2026-09-13).
 *
 * Bis dahin lag derselbe Feed unter einer festen, jedem bekannten Adresse und
 * lieferte die öffentlichen Termine ALLER Mandanten in einem Dokument. Hinter
 * die Anmeldung stellen ließ er sich nicht: Outlook, Google und Apple rufen
 * eine Abo-Adresse ohne Sitzung ab. Also derselbe Weg wie beim Geräte-Pass und
 * beim persönlichen Feed — ein zufälliger Token in der Adresse, gespeichert
 * nur als Abdruck (Sicherheitsscan 2026-08-23, S-44):
 *
 * - Wer die Datenbank liest, kann den Feed danach nicht öffnen.
 * - Der Klartext existiert genau einmal, im Moment der Ausstellung.
 * - Ein Hinweis (erste sechs Zeichen) bleibt sichtbar, damit ein umlaufender
 *   Link einem ausgestellten Token zugeordnet werden kann.
 * - Ausstellen ist zugleich Rotieren; alte Abos laufen sofort ins Leere.
 * - Ohne Token gibt es keinen Feed. Die Freischaltung IST der Token.
 */
class OrganizationCalendarFeedService {
    public const HASH_KEY = 'calendar_feed_token_hash';

    public const HINT_KEY = 'calendar_feed_token_hint';

    public const ISSUED_KEY = 'calendar_feed_token_issued_at';

    /** Stellt einen neuen Token aus und gibt ihn EINMAL im Klartext zurück. */
    public function issue(Organization $organization): string {
        $token = Str::lower(Str::random(48));
        $this->write($organization, [
            self::HASH_KEY => self::fingerprint($token),
            self::HINT_KEY => mb_substr($token, 0, 6),
            self::ISSUED_KEY => now()->toIso8601String(),
        ]);

        return $token;
    }

    /** Entzieht den Zugang: der Abdruck verschwindet, bestehende Abos brechen ab. */
    public function revoke(Organization $organization): void {
        $this->write($organization, [
            self::HASH_KEY => null,
            self::HINT_KEY => null,
            self::ISSUED_KEY => null,
        ]);
    }

    /** @return array{issued: bool, hint: string|null, issued_at: string|null} */
    public function status(Organization $organization): array {
        $settings = (array) ($organization->settings ?? []);
        $hash = $settings[self::HASH_KEY] ?? null;

        return [
            'issued' => is_string($hash) && $hash !== '',
            'hint' => is_string($settings[self::HINT_KEY] ?? null) ? $settings[self::HINT_KEY] : null,
            'issued_at' => is_string($settings[self::ISSUED_KEY] ?? null) ? $settings[self::ISSUED_KEY] : null,
        ];
    }

    /**
     * Löst einen Klartext-Token zur Organisation auf — die einzige Stelle, die
     * das darf.
     */
    public function resolve(string $token): ?Organization {
        $fingerprint = self::fingerprint($token);
        if ($fingerprint === null) {
            return null;
        }

        // TENANT-BYPASS: Auflösung ohne Anmeldung, ausschließlich über den Abdruck.
        $organization = Organization::query()->withoutGlobalScopes()
            ->where('settings->' . self::HASH_KEY, $fingerprint)
            ->where('is_active', true)
            ->first();

        return $organization instanceof Organization ? $organization : null;
    }

    /** Abdruck eines Tokens — deterministisch, damit die Adresse auflösbar bleibt. */
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
        $organization->forceFill(['settings' => $settings])->save();
    }
}

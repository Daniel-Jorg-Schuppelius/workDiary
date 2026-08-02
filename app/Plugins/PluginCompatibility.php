<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginCompatibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins;

use App\Plugins\Contracts\Plugin;

/**
 * Prüft, ob ein Plugin mit der laufenden WorkDiary-Kernversion kompatibel ist
 * (Feature 022, MVP — Plugin-Kompatibilitätsangaben + Durchsetzung).
 *
 * Quelle der Kompatibilitätsgrenzen sind die Plugin-Contract-Methoden
 * {@see Plugin::minAppVersion()} / {@see Plugin::maxAppVersion()} (additiv,
 * Default `null` über {@see PluginDefaults}). Die laufende Kernversion stammt
 * aus `config('app.version')`.
 *
 * Bewusst stateless und über statische Helfer aufrufbar: Aktivierung (Toggle),
 * Healthcheck und Admin-UI nutzen dieselbe Logik. Versionsvergleich über das
 * eingebaute {@see version_compare()} (keine zusätzlichen Pakete); ein
 * Dev-Suffix wie `-dev` wird für den Grenzvergleich auf den Release-Teil
 * reduziert, damit eine Dev-Installation nicht fälschlich als inkompatibel gilt.
 */
final class PluginCompatibility {
    public function __construct(
        public readonly bool $compatible,
        /** Stabiler Maschinen-Code: 'ok', 'too_old', 'too_new'. */
        public readonly string $code,
        public readonly string $message,
        public readonly ?string $minAppVersion,
        public readonly ?string $maxAppVersion,
        public readonly string $appVersion,
    ) {}

    /** Bewertet ein Plugin gegen die aktuell konfigurierte Kernversion. */
    public static function for(Plugin $plugin): self {
        return self::evaluate(
            $plugin->minAppVersion(),
            $plugin->maxAppVersion(),
            self::currentAppVersion(),
        );
    }

    /**
     * Reine Kernlogik (unit-testbar, ohne config/Plugin-Auflösung).
     */
    public static function evaluate(?string $minAppVersion, ?string $maxAppVersion, string $appVersion): self {
        $normalized = self::normalize($appVersion);

        if ($minAppVersion !== null && $minAppVersion !== '' && version_compare($normalized, self::normalize($minAppVersion), '<')) {
            return new self(
                false,
                'too_old',
                sprintf('Benötigt mindestens WorkDiary %s (läuft auf %s).', $minAppVersion, $appVersion),
                $minAppVersion,
                $maxAppVersion,
                $appVersion,
            );
        }

        if ($maxAppVersion !== null && $maxAppVersion !== '' && version_compare($normalized, self::normalize($maxAppVersion), '>')) {
            return new self(
                false,
                'too_new',
                sprintf('Getestet bis WorkDiary %s (läuft auf %s).', $maxAppVersion, $appVersion),
                $minAppVersion,
                $maxAppVersion,
                $appVersion,
            );
        }

        return new self(
            true,
            'ok',
            'Kompatibel mit der laufenden Kernversion.',
            $minAppVersion,
            $maxAppVersion,
            $appVersion,
        );
    }

    public static function currentAppVersion(): string {
        return (string) config('app.version', '0.1.0-dev');
    }

    /**
     * Reduziert einen Versionsstring auf den Vergleichskern (Review 2026-08,
     * W3e/D8): Build-Metadaten (`+…`) sind laut SemVer vergleichsirrelevant;
     * ein `-dev`-Suffix wird abgeschnitten, damit `0.1.0-dev` nicht unter
     * `0.1.0` rutscht (dokumentierte Absicht). Echte Prerelease-Stufen
     * (`-rc1`, `-beta`) bleiben erhalten — version_compare ordnet sie korrekt
     * unter das Release; das frühere pauschale Abschneiden machte `1.0.0-rc1`
     * fälschlich gleich `1.0.0`.
     */
    private static function normalize(string $version): string {
        $version = trim($version);
        $plus = strpos($version, '+');
        if ($plus !== false) {
            $version = substr($version, 0, $plus);
        }
        if (str_ends_with($version, '-dev')) {
            $version = substr($version, 0, -4);
        }

        return $version;
    }

    /** @return array{compatible: bool, code: string, message: string, min: string|null, max: string|null, app: string} */
    public function toArray(): array {
        return [
            'compatible' => $this->compatible,
            'code' => $this->code,
            'message' => $this->message,
            'min' => $this->minAppVersion,
            'max' => $this->maxAppVersion,
            'app' => $this->appVersion,
        ];
    }
}

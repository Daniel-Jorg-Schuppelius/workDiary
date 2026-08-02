<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins;

use App\Plugins\Contracts\Plugin;
use App\Plugins\Support\PluginSettingsResolver;

/**
 * Basisklasse für App-Plugins (Review 2026-08, W5a): kapselt die vorher
 * 26-fach kopierte Konvention — ein konkretes Plugin definiert `const ID`
 * (+ optional `const SERVICE_PROVIDER`) und implementiert nur noch
 * `name()`, `version()`, `description()`, `capabilities()`, `settingsSchema()`
 * sowie seine Fachlichkeit.
 *
 *  - `id()`         : aus `static::ID`.
 *  - `isEnabled()`  : Org-Setting vor Config-Fallback via
 *                     {@see PluginSettingsResolver::enabled()} — exakt die
 *                     Semantik der früheren Kopien. Plugins mit abweichender
 *                     Logik (z. B. Github/Gitlab-Config-Resolver) überschreiben.
 *  - `serviceProvider()`: aus `static::SERVICE_PROVIDER` (die Konstante ist
 *                     weiterhin die vom Core geladene Quelle — keine Doppelpflege).
 *  - `isPerOrganization()`: `true` — jedes reale Plugin führt Konfiguration
 *                     je Organisation (der Trait-Default `false` war für kein
 *                     einziges zutreffend).
 *
 * Test-Fakes können weiterhin das nackte Interface + {@see PluginDefaults}
 * implementieren.
 */
abstract class AbstractPlugin implements Plugin {
    use PluginDefaults;

    /** Jede konkrete Klasse überschreibt dies mit ihrer stabilen Plugin-ID. */
    public const ID = '';

    public function id(): string {
        return static::ID;
    }

    public function isEnabled(): bool {
        return PluginSettingsResolver::for(static::ID)->enabled();
    }

    public function serviceProvider(): ?string {
        $const = static::class . '::SERVICE_PROVIDER';
        if (! defined($const)) {
            return null;
        }
        $value = constant($const);

        return is_string($value) && class_exists($value) ? $value : null;
    }

    public function adminPanel(): ?array {
        return null;
    }

    public function isPerOrganization(): bool {
        return true;
    }
}

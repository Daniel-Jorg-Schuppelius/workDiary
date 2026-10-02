<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingDefaultsInstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Org\Install;

use App\Models\Platform\{Organization, User};
use App\Services\Classification\Contracts\ProfileInstallStep;
use App\Settings\{SettingScope, SettingSource, SettingsRegistry};
use Illuminate\Validation\ValidationException;

/**
 * Profilabschnitt `settings` (MVP-1053): Vorgaben für Organisationseinstellungen.
 * Gesetzt wird nur, was die Organisation noch nicht selbst festgelegt hat —
 * eine eigene Entscheidung überschreibt kein Profil.
 */
class SettingDefaultsInstallStep implements ProfileInstallStep {
    public function __construct(private readonly SettingsRegistry $registry) {}

    public function key(): string {
        return 'settings';
    }

    /**
     * @param  array<int|string, mixed>  $rows  Einstellungsschlüssel → Wert (Profildaten, ungeprüft)
     * @return array{created: int, skipped: int}
     */
    public function install(Organization $organization, array $rows, ?User $actor): array {
        $created = 0;
        $skipped = 0;
        foreach ($rows as $key => $value) {
            if (! is_string($key) || ! $this->registry->has($key)
                || ! $this->registry->definition($key)->allowsScope(SettingScope::Organization)
                || $this->registry->effective($key, $organization)->source === SettingSource::Organization) {
                $skipped++;

                continue;
            }
            try {
                $this->registry->set($key, $value, SettingScope::Organization, $organization, $actor?->id);
                $created++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}

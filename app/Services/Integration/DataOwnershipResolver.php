<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataOwnershipResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Enums\Integration\DataDomain;
use App\Models\Organization;

/**
 * Zentrale Datenführerschaft je Org und Datenbereich (Restpunkt 69):
 * genau EIN führendes System je Bereich (Map in organizations.settings →
 * Doppel-Führung ist strukturell unmöglich). Semantik des Gates:
 *  - Owner 'native' (Default): WorkDiary führt — Plugin-IMPORTE über die
 *    Inbox bleiben wie bisher erlaubt (kein Verhaltensbruch).
 *  - Owner = Plugin X: nur X darf in den Bereich schreiben; Schreibversuche
 *    anderer Plugins müssen als Inbox-Konflikt landen statt zu schreiben
 *    (Aufrufer prüfen {@see mayWrite()} vor dem Write).
 */
class DataOwnershipResolver {
    public const NATIVE = 'native';

    private const SETTINGS_KEY = 'data_ownership';

    public function ownerFor(Organization $organization, DataDomain $domain): string {
        $map = (array) data_get((array) ($organization->settings ?? []), self::SETTINGS_KEY, []);
        $owner = trim((string) ($map[$domain->value] ?? ''));

        return $owner !== '' ? $owner : self::NATIVE;
    }

    /**
     * Darf $source (Plugin-ID oder 'native') in den Bereich schreiben?
     * Native Führung erlaubt Plugin-Importe (Bestandsverhalten); bei
     * Plugin-Führung darf nur der Owner selbst (und native) schreiben.
     */
    public function mayWrite(Organization $organization, DataDomain $domain, string $source): bool {
        $owner = $this->ownerFor($organization, $domain);

        if ($owner === self::NATIVE) {
            return true;
        }

        return $source === $owner || $source === self::NATIVE;
    }

    /** Setzt den Owner eines Bereichs (genau einer je Domain). */
    public function setOwner(Organization $organization, DataDomain $domain, string $owner): void {
        $settings = (array) ($organization->settings ?? []);
        $map = (array) ($settings[self::SETTINGS_KEY] ?? []);

        $owner = trim($owner);
        if ($owner === '' || $owner === self::NATIVE) {
            unset($map[$domain->value]);
        } else {
            $map[$domain->value] = $owner;
        }

        if ($map === []) {
            unset($settings[self::SETTINGS_KEY]);
        } else {
            $settings[self::SETTINGS_KEY] = $map;
        }
        $organization->forceFill(['settings' => $settings])->save();
    }

    /**
     * Vollständige Matrix für die Admin-UI.
     *
     * @return array<string, string> domain-value => owner
     */
    public function matrix(Organization $organization): array {
        $matrix = [];
        foreach (DataDomain::cases() as $domain) {
            $matrix[$domain->value] = $this->ownerFor($organization, $domain);
        }

        return $matrix;
    }
}

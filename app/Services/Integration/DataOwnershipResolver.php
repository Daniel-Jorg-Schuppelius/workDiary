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
use App\Enums\Inventory\InventoryMode;
use App\Models\Platform\Organization;
use App\Plugins\Contracts\WritesDataDomains;
use App\Plugins\PluginManager;
use App\Services\Stammdaten\Contracts\ContactPushTarget;
use App\Settings\{SettingScope, SettingsRegistry};
use RuntimeException;

/**
 * Zentrale Datenführerschaft je Org und Datenbereich (Restpunkt 69):
 * genau EIN führendes System je Bereich (Map in organizations.settings →
 * Doppel-Führung ist strukturell unmöglich). Semantik des Gates:
 *  - Owner 'native' (Default): WorkDiary führt — Plugin-IMPORTE über die
 *    Inbox bleiben wie bisher erlaubt (kein Verhaltensbruch).
 *  - Owner = Plugin X: nur X darf in den Bereich schreiben; Schreibversuche
 *    anderer Plugins müssen als Inbox-Konflikt landen statt zu schreiben
 *    (Aufrufer prüfen {@see mayWrite()} vor dem Write).
 *
 * Bestände führt die Bestandsführung (`inventory_mode`, `inventory_plugin_id`,
 * Feature 078): die Matrix liest sie nur, ein zweiter Schalter entfällt
 * (MVP-1592).
 *
 * Kunden und Lieferanten folgen der Stammdatenhoheit
 * (`finance.master_data_authority`, Feature 122): WorkDiary führt, oder die
 * Buchhaltung führt — welches Programm, steht in der Matrix. Die Matrix
 * schreibt beide Werte zusammen; ohne gewähltes Programm führt
 * {@see ACCOUNTING}, also jedes Plugin, das Kunden schreiben kann.
 */
class DataOwnershipResolver {
    public const string NATIVE = 'native';

    /** Die Buchhaltung führt die Stammdaten, ein Programm ist nicht gewählt. */
    public const string ACCOUNTING = 'accounting';

    private const string SETTINGS_KEY = 'data_ownership';

    public function ownerFor(Organization $organization, DataDomain $domain): string {
        if ($domain === DataDomain::Inventory) {
            return $this->inventoryOwner($organization);
        }

        $map = (array) data_get((array) ($organization->settings ?? []), self::SETTINGS_KEY, []);
        $owner = trim((string) ($map[$domain->value] ?? ''));
        if ($domain === DataDomain::Customers) {
            if (! $this->accountingLeadsMasterData($organization)) {
                return self::NATIVE;
            }

            return $owner !== '' && $owner !== self::NATIVE ? $owner : self::ACCOUNTING;
        }

        return $owner !== '' ? $owner : self::NATIVE;
    }

    /** Stammdatenhoheit der Organisation (Feature 122); Vorgabe WorkDiary. */
    public function accountingLeadsMasterData(Organization $organization): bool {
        return (string) app(SettingsRegistry::class)->effective(ContactPushTarget::AUTHORITY_KEY, $organization)->value === ContactPushTarget::AUTHORITY_ACCOUNTING;
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
        if ($owner === self::ACCOUNTING) {
            $plugin = app(PluginManager::class)->find($source);

            return $source === self::NATIVE || ($plugin instanceof WritesDataDomains && in_array($domain, $plugin->dataDomains(), true));
        }

        return $source === $owner || $source === self::NATIVE;
    }

    /**
     * Setzt den Owner eines Bereichs (genau einer je Domain). Kunden schreiben
     * zugleich die Stammdatenhoheit: ein Programm heißt „Buchhaltung führt“.
     */
    public function setOwner(Organization $organization, DataDomain $domain, string $owner): void {
        if ($domain === DataDomain::Inventory) {
            throw new RuntimeException((string) __('integration.ownership.inventory_locked'));
        }

        $owner = trim($owner);
        if ($domain === DataDomain::Customers) {
            app(SettingsRegistry::class)->set(
                ContactPushTarget::AUTHORITY_KEY,
                $owner === '' || $owner === self::NATIVE ? ContactPushTarget::AUTHORITY_WORKDIARY : ContactPushTarget::AUTHORITY_ACCOUNTING,
                SettingScope::Organization,
                $organization,
            );
            if ($owner === self::ACCOUNTING) {
                $owner = self::NATIVE; // kein Programm gewählt: kein Matrix-Eintrag
            }
        }

        $settings = (array) ($organization->settings ?? []);
        $map = (array) ($settings[self::SETTINGS_KEY] ?? []);

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

    /** Modus der Bestandsführung (Feature 078); unbekannt oder leer heißt lokal. */
    public function inventoryMode(Organization $organization): InventoryMode {
        return InventoryMode::tryFrom((string) data_get($organization->settings, 'inventory_mode', '')) ?? InventoryMode::Local;
    }

    /** Externe Bestandsführung (auch „nur lesen“) mit gewähltem Plugin führt, sonst WorkDiary. */
    private function inventoryOwner(Organization $organization): string {
        $plugin = trim((string) data_get($organization->settings, 'inventory_plugin_id', ''));

        return $this->inventoryMode($organization) !== InventoryMode::Local && $plugin !== '' ? $plugin : self::NATIVE;
    }
}

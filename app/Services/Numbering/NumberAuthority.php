<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberAuthority.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Numbering;

use App\Enums\Numbering\NumberScope;
use App\Models\Numbering\NumberFormat;
use App\Models\Platform\Organization;
use App\Plugins\Contracts\ContactNumberIssuer;
use App\Plugins\PluginManager;
use App\Plugins\Support\PluginSettingsResolver;
use App\Services\Numbering\Contracts\DocumentIssuer;

/**
 * Entscheidet, wer einen Nummernkreis vergibt: `local` (workDiary) oder die
 * Plugin-ID eines Buchhaltungsprogramms (MVP-1130). Bei Vergabe durch ein
 * Programm entsteht lokal nur eine Entwurfsnummer, die beim Übertragen bzw.
 * Abgleich durch die Nummer des Programms ersetzt wird.
 *
 * Quelle ist allein `number_formats.source`; der Kern kennt keine Plugins.
 */
class NumberAuthority {
    public const SOURCE_LOCAL = 'local';

    public function isExternal(Organization|int|null $organization, NumberScope $scope): bool {
        return $this->sourceFor($organization, $scope) !== self::SOURCE_LOCAL;
    }

    /**
     * Vergebende Stelle des Kreises. Ein eingestelltes, aber für die
     * Organisation abgeschaltetes Programm zählt als `local` — sonst bekäme
     * jeder neue Datensatz eine Entwurfsnummer, die nie ersetzt wird.
     */
    public function sourceFor(Organization|int|null $organization, NumberScope $scope): string {
        $source = $this->configuredSource($organization, $scope);
        if ($source === self::SOURCE_LOCAL) {
            return self::SOURCE_LOCAL;
        }

        return PluginSettingsResolver::for($source, $this->orgId($organization))->enabled() ? $source : self::SOURCE_LOCAL;
    }

    /** Eingestellte Quelle ohne Prüfung, ob das Programm aktiv ist (Anzeige auf der Seite „Nummernkreise“). */
    public function configuredSource(Organization|int|null $organization, NumberScope $scope): string {
        if (! $scope->isAccountingRelevant()) {
            return self::SOURCE_LOCAL;
        }

        $orgId = $this->orgId($organization);
        if ($orgId === null) {
            return self::SOURCE_LOCAL;
        }

        $source = (string) NumberFormat::query()
            ->where('organization_id', $orgId)
            ->where('scope', $scope->value)
            ->value('source');

        return $source === '' ? self::SOURCE_LOCAL : $source;
    }

    /**
     * Wählbare Quellen eines Kreises: workDiary und jedes aktive Programm, das
     * den Kreis vergeben kann. Eine eingestellte, nicht mehr verfügbare Quelle
     * bleibt sichtbar, damit das Speichern der Zeile sie nicht still ersetzt.
     *
     * @return array<string, string> Quelle => Bezeichnung
     */
    public function sourceOptions(Organization|int $organization, NumberScope $scope): array {
        $plugins = app(PluginManager::class);
        $documentIssuers = array_map(static fn (DocumentIssuer $issuer): string => $issuer->pluginId(), app(DocumentIssuers::class)->forScope($scope));
        $options = [self::SOURCE_LOCAL => (string) __('numbering.source.local')];
        foreach ($plugins->enabled() as $id => $plugin) {
            $issuesContacts = $plugin instanceof ContactNumberIssuer && in_array($scope, $plugin->issuableNumberScopes(), true);
            if ($issuesContacts || in_array((string) $id, $documentIssuers, true)) {
                $options[(string) $id] = $plugin->name();
            }
        }

        $configured = $this->configuredSource($organization, $scope);
        if (! isset($options[$configured])) {
            $options[$configured] = (string) __('numbering.source.inactive', ['program' => $plugins->find($configured)?->name() ?? $configured]);
        }

        return $options;
    }

    /**
     * Programm, das die Nummer im Formular sperrt (MVP-1134): bei neuen
     * Kontakten des Kreises (`$partySource` null) und bei solchen, deren
     * Nummer von diesem Programm stammt. Null = frei änderbar.
     */
    public function numberLockedBy(NumberScope $scope, Organization|int|null $organization, ?string $partySource = null): ?string {
        $source = $this->sourceFor($organization, $scope);
        if ($source === self::SOURCE_LOCAL || ($partySource !== null && $partySource !== $source)) {
            return null;
        }

        return $this->sourceLabel($source);
    }

    /** Anzeigename der vergebenden Stelle (Programmname oder workDiary). */
    public function sourceLabel(string $source): string {
        return $source === self::SOURCE_LOCAL
            ? (string) __('numbering.source.local')
            : (app(PluginManager::class)->find($source)?->name() ?? $source);
    }

    private function orgId(Organization|int|null $organization): ?int {
        if ($organization instanceof Organization) {
            return (int) $organization->id;
        }

        return $organization === null ? null : (int) $organization;
    }
}

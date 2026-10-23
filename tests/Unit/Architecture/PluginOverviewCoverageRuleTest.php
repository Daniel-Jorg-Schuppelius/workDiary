<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginOverviewCoverageRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Fremddaten im Überblick (Feature 181, MVP-1368): Jedes Plugin-Modell ist
 * entweder ein Fachobjekt mit Suchanschluss — eine Klasse des Plugins, die
 * `SearchDomain`, `SearchSource` oder `DocumentFeedSource` (der Belegfluss ist
 * durchsuchbar) erfüllt, das Modell nutzt und im ServiceProvider angemeldet
 * ist — oder ein technisches Modell mit Grund in der Liste unten. Lokale
 * Kernobjekte aus Plugins (Aufgaben, Belege) erscheinen über die Kernbereiche.
 */
class PluginOverviewCoverageRuleTest extends TestCase {
    use ScansSourceTree;

    /** Namensendungen technischer Modelle. */
    private const TECHNICAL_SUFFIXES = [
        'Connection' => 'Zugangsdaten und Verbindungszustand',
        'WebhookDelivery' => 'Zustellprotokoll eingehender Webhooks',
        'WebhookSubscription' => 'Webhook-Abonnement beim Anbieter',
        'Link' => 'Zuordnung fremder Projekte oder Listen zu lokalen',
        'Mapping' => 'Zuordnungstabelle',
    ];

    /** Plugin-Modelle ohne eigenen Suchanschluss, Schlüssel `<Plugin>/<Modell>`. */
    private const ALLOWLIST = [
        'CardDav/CardDavCard' => 'Spiegel der Adressbuchkarten; die Kontakte sind Kunden und Lieferanten mit eigener Suche',
        'DatevOnline/DatevOnlineTransfer' => 'Übertragungsjournal; die Belege stehen im Rechnungseingang',
        'Etsy/EtsyLedgerEntry' => 'Gebühren und Auszahlungen des Shopkontos; Summen auf der Etsy-Seite, gesucht wird die Bestellung',
        'Fritzbox/FritzboxDismissedCall' => 'ausgeblendete Anrufe, reiner Bedienzustand',
        'JtlWawi/JtlStockSnapshot' => 'Bestandsabgleich; Artikel und Lager haben eigene Suche',
        'Lexoffice/LexofficeArticle' => 'Artikelspiegel für den Abgleich; Artikel haben eigene Suche',
        'Lexoffice/LexofficeInvoiceHandover' => 'Übergabejournal lokaler Rechnungen',
        'Lexoffice/LexofficePostingCategory' => 'Nachschlageliste der Buchungskategorien',
        'Lexoffice/LexofficeVoucherCategory' => 'Nachschlageliste der Belegkategorien',
        'Lexoffice/LexofficeVoucherLine' => 'Belegpositionen; gesucht wird der Beleg',
    ];

    private const COVERING_CONTRACTS = '/\b(?:implements\s[^{]*\b(?:SearchDomain|SearchSource|DocumentFeedSource)\b|extends\s+AbstractSearchSource\b)/';

    public function test_every_plugin_model_is_searchable_or_named_as_technical(): void {
        $violations = [];
        foreach ($this->pluginModels() as $plugin => $models) {
            $covering = $this->coveringClasses($plugin);
            foreach ($models as $model) {
                if ($this->technicalReason($plugin, $model) !== null) {
                    continue;
                }
                $covered = array_filter($covering, static fn (string $source): bool => preg_match('/\b' . $model . '\b/', $source) === 1);
                if ($covered === []) {
                    $violations[] = "app/Plugins/$plugin/Models/$model.php — kein Suchanschluss (SearchDomain/SearchSource/DocumentFeedSource) im Plugin";
                }
            }
        }

        $this->assertSame([], $violations, "Fremddaten gehören in die Überblicksflächen (Plugin-Autorenleitfaden, „Überblicks-Durchgängigkeit“) — Suchanschluss ergänzen oder mit Grund in die Liste:\n" . implode("\n", $violations));
    }

    public function test_allowlist_names_existing_models_without_coverage(): void {
        $stale = [];
        $models = $this->pluginModels();
        foreach (array_keys(self::ALLOWLIST) as $entry) {
            [$plugin, $model] = explode('/', $entry);
            if (! in_array($model, $models[$plugin] ?? [], true)) {
                $stale[] = "$entry — Modell gibt es nicht mehr";
            } elseif (array_filter($this->coveringClasses($plugin), static fn (string $source): bool => preg_match('/\b' . $model . '\b/', $source) === 1) !== []) {
                $stale[] = "$entry — hat inzwischen einen Suchanschluss";
            }
        }

        $this->assertSame([], $stale, implode("\n", $stale));
    }

    private function technicalReason(string $plugin, string $model): ?string {
        foreach (self::TECHNICAL_SUFFIXES as $suffix => $reason) {
            if (str_ends_with($model, $suffix)) {
                return $reason;
            }
        }

        return self::ALLOWLIST["$plugin/$model"] ?? null;
    }

    /**
     * Quelltexte der Plugin-Klassen, die einen Suchanschluss erfüllen und im
     * ServiceProvider des Plugins genannt sind.
     *
     * @return list<string>
     */
    private function coveringClasses(string $plugin): array {
        $provider = (string) file_get_contents($this->repoRoot() . "/app/Plugins/$plugin/{$plugin}ServiceProvider.php");
        $sources = [];
        foreach ($this->phpFiles("app/Plugins/$plugin") as $file) {
            if (str_contains($file, '/Models/')) {
                continue;
            }
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match(self::COVERING_CONTRACTS, $source) === 1 && str_contains($provider, basename($file, '.php'))) {
                $sources[] = $source;
            }
        }

        return $sources;
    }

    /** @return array<string, list<string>> Plugin => Modellnamen */
    private function pluginModels(): array {
        $models = [];
        foreach (glob($this->repoRoot() . '/app/Plugins/*/Models/*.php') ?: [] as $file) {
            $models[basename(dirname($file, 2))][] = basename($file, '.php');
        }

        return $models;
    }
}

<?php

/*
 * Filename     : PluginHelpMentionRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „jede Erweiterung wird in der Hilfe erwähnt" (Vollscan
 * 2026-09-15, Befund `C1-11` / `MVP-797`).
 *
 * Befund: 16 der damals 38 Plugins kamen in keinem Hilfe-Topic auch nur vor —
 * sevDesk, easybill, BuchhaltungsButler, Peppol, sipgate, seven.io, DHL,
 * FedEx, S3, Nextcloud, Calendly, GitHub, GitLab, FRITZ!Box, Msgraph, CardDAV.
 * Betreiber konnten nicht nachlesen, dass es sie gibt. `help:coverage` findet
 * das nicht: es prüft, ob eine Route ein Topic HAT, nicht ob der Text die
 * Sache auch NENNT — und die Plugin-Admin-Seiten zeigen alle auf dasselbe
 * generische Topic.
 *
 * Geprüft wird bewusst nur die Leitsprache: die Sprachparität der Topics
 * sichert `HelpContentTest` ab. „Erwähnt" heisst: der Name (oder eine
 * gebräuchliche Schreibweise davon) steht in irgendeinem Topic — nicht, dass
 * es ein eigenes Topic gibt. Ein eigenes Topic ist die Ausnahme für
 * Erweiterungen mit eigenem Einrichtungsweg (siehe `admin.calendly`).
 */
class PluginHelpMentionRuleTest extends TestCase {
    use ScansSourceTree;

    /** Verzeichnisse unter app/Plugins, die kein Plugin sind. */
    private const NOT_A_PLUGIN = ['Contracts', 'Support'];

    /**
     * Verzeichnisname → Schreibweisen, unter denen die Erweiterung in der
     * Hilfe auftauchen darf. Die Hilfe schreibt für Menschen: dort steht
     * „FRITZ!Box" und „Microsoft Graph", nicht `Fritzbox` und `Msgraph`.
     * Mehrsprachige Begriffe (Fernwartung) stehen mit ihren Übersetzungen.
     *
     * @var array<string, list<string>>
     */
    private const SPELLINGS = [
        'BuchhaltungsButler' => ['buchhaltungsbutler'],
        'CalDav' => ['caldav'],
        'CardDav' => ['carddav'],
        'Dhl' => ['dhl'],
        'DomainReselling' => ['domain'],
        'Fedex' => ['fedex'],
        'Fritzbox' => ['fritz'],
        'GoogleCalendar' => ['google calendar', 'google kalender', 'google agenda'],
        'GoogleDrive' => ['google drive'],
        'JtlWawi' => ['jtl'],
        'Msgraph' => ['microsoft graph', 'msgraph'],
        'PeppolAccessPoint' => ['peppol'],
        'RemoteSupport' => ['fernwartung', 'remote support'],
        'SevenIo' => ['seven.io'],
        'Ups' => ['ups'],
    ];

    public function test_every_plugin_is_mentioned_in_the_help(): void {
        $haystack = $this->helpText();

        $missing = [];
        foreach ($this->pluginNames() as $plugin) {
            $spellings = self::SPELLINGS[$plugin] ?? [strtolower($plugin)];
            foreach ($spellings as $spelling) {
                if (str_contains($haystack, $spelling)) {
                    continue 2;
                }
            }
            $missing[] = $plugin;
        }

        $this->assertSame([], $missing, "Diese Erweiterungen kommen in keinem Hilfe-Topic vor:\n"
            . implode("\n", $missing)
            . "\n\nWer sie einrichten soll, findet nichts darüber. Entweder in "
            . "resources/help/{locale}/admin.integrations.md aufnehmen (Regelfall) oder "
            . "ein eigenes Topic anlegen (bei eigenem Einrichtungsweg). Schreibt die Hilfe "
            . 'den Namen anders als das Verzeichnis, gehört die Schreibweise in SPELLINGS.');
    }

    public function test_the_spelling_map_has_no_stale_entries(): void {
        $plugins = $this->pluginNames();
        $stale = array_values(array_diff(array_keys(self::SPELLINGS), $plugins));

        $this->assertSame([], $stale, "SPELLINGS nennt Verzeichnisse, die es nicht (mehr) gibt:\n"
            . implode("\n", $stale));
    }

    public function test_the_plugin_inventory_is_actually_scanned(): void {
        // Schutz gegen stilles Leerlaufen (falscher Pfad).
        $this->assertGreaterThan(20, count($this->pluginNames()));
        $this->assertNotSame('', $this->helpText());
    }

    /** @return list<string> */
    private function pluginNames(): array {
        $names = [];
        foreach ((array) glob($this->repoRoot() . '/app/Plugins/*', GLOB_ONLYDIR) as $dir) {
            $name = basename((string) $dir);
            if (! in_array($name, self::NOT_A_PLUGIN, true)) {
                $names[] = $name;
            }
        }
        sort($names);

        return $names;
    }

    /** Alle Hilfe-Texte der Leitsprache, kleingeschrieben. */
    private function helpText(): string {
        $text = '';
        foreach ((array) glob($this->repoRoot() . '/resources/help/de/*.md') as $file) {
            $text .= (string) file_get_contents((string) $file);
        }

        return mb_strtolower($text);
    }
}

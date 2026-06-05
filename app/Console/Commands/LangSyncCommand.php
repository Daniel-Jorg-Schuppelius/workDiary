<?php
/*
 * Created on   : Thu Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LangSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Support\{Locales, Translations};
use Illuminate\Console\Command;

/**
 * Legt fehlende Übersetzungs-Keys/Dateien für eine Sprache an (Gerüst-Generator)
 * und/oder entfernt überzählige. Platzhalter = Quellwert (deutscher JSON-Quelltext
 * bzw. de-Namespace-Wert), anschließend zu übersetzen.
 *
 *   php artisan lang:sync --locale=es --fill        # neue Sprache gerüsten/füllen
 *   php artisan lang:sync --locale=fr --fill --prune
 */
class LangSyncCommand extends Command {
    protected $signature = 'lang:sync {--locale= : Zielsprache (Default: alle außer de)} {--fill : Fehlende Keys mit Quellwert anlegen} {--prune : Überzählige Keys entfernen}';

    protected $description = 'Synchronisiert Übersetzungsdateien (JSON + Namespaces) gegen die Referenz.';

    public function handle(): int {
        $fill = (bool) $this->option('fill');
        $prune = (bool) $this->option('prune');
        if (! $fill && ! $prune) {
            $this->warn('Weder --fill noch --prune gesetzt — nichts zu tun.');

            return self::SUCCESS;
        }

        $only = $this->option('locale');
        $targets = $only !== null
            ? [$only]
            : array_values(array_filter(Locales::enabledCodes(), static fn(string $c): bool => $c !== 'de'));

        foreach ($targets as $code) {
            if ($code === 'de') {
                $this->warn('de ist die Quellsprache und wird übersprungen.');

                continue;
            }
            $this->syncJson($code, $fill, $prune);
            $this->syncNamespaces($code, $fill);
        }

        $this->info('lang:sync fertig.');

        return self::SUCCESS;
    }

    private function syncJson(string $code, bool $fill, bool $prune): void {
        $ref = Translations::jsonReferenceKeys();
        $existing = Translations::loadJson($code);
        $out = [];
        $added = 0;
        foreach ($ref as $key) {
            if (array_key_exists($key, $existing)) {
                $out[$key] = $existing[$key];
            } elseif ($fill) {
                $out[$key] = $key; // Platzhalter = deutscher Quelltext
                $added++;
            }
        }
        // Nicht-Referenz-Keys nur behalten, wenn NICHT geprunt wird.
        $pruned = 0;
        if (! $prune) {
            foreach ($existing as $key => $value) {
                if (! array_key_exists($key, $out)) {
                    $out[$key] = $value;
                }
            }
        } else {
            $pruned = count(array_diff_key($existing, array_fill_keys($ref, true)));
        }

        Translations::writeJson($code, $out);
        $this->line(sprintf('%s.json: +%d gefüllt, -%d entfernt (gesamt %d)', $code, $added, $pruned, count($out)));
    }

    private function syncNamespaces(string $code, bool $fill): void {
        if (! $fill) {
            return;
        }
        // Für fehlende Namespace-Dateien einen en-Fallback-Stub anlegen
        // (Projekt-Konvention, vgl. lang/fr, lang/it). Bestehende Dateien werden
        // NIE angefasst (handformatierte Übersetzungen/Header bleiben erhalten).
        $created = 0;
        foreach (Translations::namespaceFiles() as $file) {
            if (is_file(Translations::langPath($code) . '/' . $file)) {
                continue;
            }
            Translations::writeRequireStub($code, $file);
            $created++;
        }
        if ($created > 0) {
            $this->line(sprintf('%s: %d Namespace-Fallback-Stub(s) angelegt.', $code, $created));
        }
    }
}

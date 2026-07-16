<?php
/*
 * Created on   : Thu Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LangCheckCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Support\{Locales, Translations};
use Illuminate\Console\Command;

/**
 * Prüft die Vollständigkeit aller Übersetzungen (JSON + PHP-Namespaces) gegen
 * die Referenz (en.json bzw. lang/de/). Meldet fehlende/überzählige Keys je
 * Sprache und liefert Exitcode ≠ 0 bei Lücken (CI-Gate).
 */
class LangCheckCommand extends Command {
    protected $signature = 'lang:check';

    protected $description = 'Prüft Übersetzungs-Parität (JSON + Namespaces) über alle Sprachen.';

    public function handle(): int {
        $gaps = 0;
        $refJson = Translations::jsonReferenceKeys();
        $refSet = array_fill_keys($refJson, true);

        foreach (Translations::jsonLocales() as $code) {
            $keys = array_keys(Translations::loadJson($code));
            $have = array_fill_keys($keys, true);
            $missing = array_values(array_filter($refJson, static fn(string $k): bool => ! isset($have[$k])));
            $extra = array_values(array_filter($keys, static fn(string $k): bool => ! isset($refSet[$k])));

            if ($missing !== []) {
                $gaps++;
                $this->warn(sprintf('%s.json: %d fehlend', $code, count($missing)));
                $this->sample('  fehlt', $missing);
            }
            if ($extra !== []) {
                // Zusätzliche, sprachspezifische Keys sind erlaubt — nur Hinweis.
                $this->line(sprintf('%s.json: %d zusätzliche Keys (ok)', $code, count($extra)));
            }
        }

        // PHP-Namespaces gegen lang/en/ (Fallback-Basis: fr/it/es verweisen per
        // require auf en; de ist die Quellsprache und darf zusätzliche Keys haben).
        foreach (Translations::namespaceFiles() as $file) {
            $ref = Translations::phpKeys('en', $file);
            foreach (Locales::enabledCodes() as $code) {
                if ($code === 'en') {
                    continue;
                }
                if (! is_file(Translations::langPath($code) . '/' . $file)) {
                    $gaps++;
                    $this->warn(sprintf('%s/%s: Datei fehlt', $code, $file));

                    continue;
                }
                $have = array_fill_keys(Translations::phpKeys($code, $file), true);
                $missing = array_values(array_filter($ref, static fn(string $k): bool => ! isset($have[$k])));
                if ($missing !== []) {
                    $gaps++;
                    $this->warn(sprintf('%s/%s: %d fehlend', $code, $file, count($missing)));
                    $this->sample('  fehlt', $missing);
                }
            }
        }

        // Quell-Scan: in Views/app verwendete JSON-Keys, die in en.json fehlen und
        // deshalb überall auf den deutschen Quelltext zurückfallen (Parität oben sieht das nicht).
        $sourceMissing = array_values(array_filter(
            Translations::sourceJsonKeys(),
            static fn(string $k): bool => ! isset($refSet[$k]),
        ));
        if ($sourceMissing !== []) {
            $gaps++;
            $this->warn(sprintf('Quellcode: %d verwendete Keys fehlen in en.json (bleiben überall deutsch)', count($sourceMissing)));
            $this->sample('  fehlt', $sourceMissing);
        }

        if ($gaps === 0) {
            $this->info('Übersetzungen vollständig über alle Sprachen (' . implode(', ', Locales::enabledCodes()) . ').');

            return self::SUCCESS;
        }

        $this->error("$gaps Lücke(n) gefunden. Mit `php artisan lang:sync --locale=<code> --fill` beheben.");

        return self::FAILURE;
    }

    /** @param list<string> $keys */
    private function sample(string $label, array $keys): void {
        if ($keys === []) {
            return;
        }
        $shown = array_slice($keys, 0, 5);
        $more = count($keys) - count($shown);
        $this->line($label . ': ' . implode(' · ', $shown) . ($more > 0 ? " … (+$more)" : ''));
    }
}

<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactProjectionGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Stammdaten\ContactDetailsWriter;
use Illuminate\Database\Eloquent\Model;

/**
 * Gate MVP-1700: Rechnungsanschrift, Land und Bankverbindung von Kunde und
 * Lieferant schreibt nur der Satellit; die Spalten `address_*`, `country` und
 * `bank_*` füllt der ContactDetailsProjectionObserver still (ohne Ereignis).
 * Speichert Code aus `app/` die Projektion direkt, bricht der Test ab. Tests,
 * Factories und Seeder dürfen Altbestand ohne Satelliten anlegen.
 */
final class ContactProjectionGuard {
    /** @var array<string, string> Muster auf den Pfad relativ zur Repo-Wurzel → Grund */
    public const array ALLOWED = [
        '#^app/Services/Demo/DemoSeederService\.php$#' => 'Demo-Daten einer frischen Organisation, ohne Bestand und ohne Abgleich.',
        '#^app/Services/[^/]+/Demo/#' => 'Demo-Bausteine der Module, wie der Demo-Seeder.',
    ];

    /**
     * Prüft einen Speichervorgang; der Aufruf kommt aus dem `saving`-Listener.
     * Den Aufrufstapel liest er erst, wenn die Projektion geändert ist.
     *
     * @param  list<array<string, mixed>>|null  $trace  vorgegebener Stapel (Regeltest)
     */
    public static function check(Model $model, string $basePath, ?array $trace = null): void {
        $columns = self::dirtyProjection($model);
        if ($columns === []) {
            return;
        }
        $origin = self::offendingOrigin($trace ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), $basePath);
        if ($origin !== null) {
            throw new \LogicException(sprintf(
                '%s schreibt die Projektion %s von %s direkt; Anschrift und Bank gehen über den ContactDetailsWriter (writeInline/pullInline, CLAUDE.md „Kontaktdaten“).',
                $origin,
                implode(', ', $columns),
                class_basename($model),
            ));
        }
    }

    /** @return list<string> geänderte Projektionsspalten; eine neue Partei mit leerem Wert zählt nicht */
    public static function dirtyProjection(Model $model): array {
        return array_values(array_filter(
            ContactDetailsWriter::INLINE_FIELDS,
            static fn (string $column): bool => $model->isDirty($column) && ($model->exists || $model->getAttribute($column) !== null),
        ));
    }

    /**
     * Erster Aufrufer außerhalb von vendor/ und dieser Schutzschicht: liegt er
     * unter app/ und ist nicht erlaubt, ist das der Fundort („Datei:Zeile“).
     *
     * @param  list<array<string, mixed>>  $trace
     */
    public static function offendingOrigin(array $trace, string $basePath): ?string {
        $root = rtrim(str_replace('\\', '/', $basePath), '/') . '/';
        foreach ($trace as $frame) {
            $file = isset($frame['file']) && is_string($frame['file']) ? str_replace('\\', '/', $frame['file']) : null;
            if ($file === null || ! str_starts_with($file, $root)) {
                continue;
            }
            $relative = substr($file, strlen($root));
            if (str_starts_with($relative, 'vendor/') || $relative === 'tests/TestCase.php' || $relative === 'tests/Support/ContactProjectionGuard.php') {
                continue;
            }
            if (! str_starts_with($relative, 'app/')) {
                return null;
            }
            foreach (array_keys(self::ALLOWED) as $pattern) {
                if (preg_match($pattern, $relative) === 1) {
                    return null;
                }
            }

            return $relative . ':' . (is_int($frame['line'] ?? null) ? $frame['line'] : 0);
        }

        return null;
    }
}

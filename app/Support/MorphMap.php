<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MorphMap.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Polymorphe Typwerte (MVP-860). Zwei Schlüsselarten je Modell:
 *
 * - **Alias** (`aliases` in config/morph-map.php, generiert): der Tabellenname.
 *   Wert aller umschreibbaren `*_type`-Spalten; was Eloquent über
 *   `getMorphClass()` schreibt.
 * - **Stabiler Schlüssel** (`legacy`, nur wachsend): der Klassenname, unter
 *   dem ein Modell vor der Map gespeichert wurde — für Bestandsmodelle
 *   `App\Models\…`, für jüngere Modelle der Alias. Er ändert sich nie, auch
 *   wenn die Klasse umzieht; darauf beruhen Hash-Ketten (`audit_logs`,
 *   `audit_redactions`) und die Sqid-Alphabete.
 *
 * Die Legacy-Einträge stehen zusätzlich in der erzwungenen Map, damit alte
 * Zeilen weiter auflösen; die Aliase stehen davor, weil Laravel beim
 * Schreiben den ersten Schlüssel einer Klasse nimmt.
 */
final class MorphMap {
    /** @var array<class-string, string> Klasse → Alias */
    private static array $aliasByClass = [];

    /** @var array<class-string, string> Klasse → stabiler Schlüssel */
    private static array $stableByClass = [];

    /**
     * Config-Repository, aus dem die Indizes gebaut wurden. Tests wechseln den
     * Container je Test, reine Unit-Tests haben keinen — der Index gilt nur
     * für das Repository, aus dem er stammt, und ein leerer wird nie behalten.
     */
    private static ?object $indexSource = null;

    /** @return array<string, class-string<Model>> Alias → Klasse */
    public static function aliases(): array {
        return self::section('aliases');
    }

    /** @return array<string, class-string<Model>> alter Klassenname → Klasse */
    public static function legacy(): array {
        return self::section('legacy');
    }

    /** @return array<string, class-string<Model>> Aliase zuerst, dann Legacy-Namen */
    public static function map(): array {
        return self::aliases() + self::legacy();
    }

    /** @param class-string $class */
    public static function alias(string $class): string {
        $class = ltrim($class, '\\');
        $alias = self::aliasIndex()[$class] ?? null;
        if ($alias === null) {
            throw new InvalidArgumentException("Kein Morph-Alias für {$class} — `php artisan morph-map:generate` ausführen.");
        }

        return $alias;
    }

    /**
     * Unveränderlicher Typschlüssel für Hash-Ketten und Sqids. Nicht-Modelle
     * (z. B. Report-Controller im Audit-Log) und unbekannte Werte kommen
     * unverändert zurück.
     */
    public static function stableKey(string $class): string {
        $class = ltrim($class, '\\');

        return self::stableIndex()[$class] ?? self::aliasIndex()[$class] ?? $class;
    }

    /**
     * Klasse hinter einem gespeicherten Typwert (Alias, alter Klassenname oder
     * Klassenname eines Nicht-Modells); null für Unbekanntes.
     *
     * @return class-string|null
     */
    public static function classFor(?string $type): ?string {
        if ($type === null || $type === '') {
            return null;
        }
        $class = self::map()[$type] ?? null;
        if ($class !== null) {
            return $class;
        }

        return class_exists($type) ? $type : null;
    }

    /**
     * Bezeichnet der gespeicherte Typwert das Modell $class? Versteht Alias
     * und alten Klassennamen, deshalb auch für Ketten-Tabellen richtig.
     *
     * @param class-string $class
     */
    public static function is(?string $type, string $class): bool {
        return self::classFor($type) === ltrim($class, '\\');
    }

    /** Kurzname der Klasse hinter einem Typwert — Basis für `entity-types`-Labels. */
    public static function basename(?string $type): string {
        return class_basename(self::classFor($type) ?? (string) $type);
    }

    /** Cache verwerfen (Tests, die die Map verändern). */
    public static function flush(): void {
        self::$aliasByClass = [];
        self::$stableByClass = [];
        self::$indexSource = null;
    }

    /**
     * Ohne Laravel-Container (reine Unit-Tests, Werkzeuge) gibt es keine Map;
     * dann verhalten sich alle Abfragen wie vor der Map (Klassenname).
     *
     * @return array<string, class-string<Model>>
     */
    private static function section(string $name): array {
        $config = self::configRepository();
        if ($config === null) {
            return [];
        }
        /** @var array<string, class-string<Model>> $section */
        $section = (array) $config->get('morph-map.' . $name, []);

        return $section;
    }

    private static function configRepository(): ?Repository {
        $app = Container::getInstance();
        if (! $app->bound('config')) {
            return null;
        }
        return $app->make('config');
    }

    /** @return array<class-string, string> */
    private static function aliasIndex(): array {
        self::buildIndexes();

        return self::$aliasByClass;
    }

    /** @return array<class-string, string> */
    private static function stableIndex(): array {
        self::buildIndexes();

        return self::$stableByClass;
    }

    private static function buildIndexes(): void {
        $config = self::configRepository();
        if ($config === null) {
            self::flush();

            return;
        }
        if (self::$indexSource === $config && self::$aliasByClass !== []) {
            return;
        }
        self::$aliasByClass = array_flip(self::aliases());
        // Erster Legacy-Name je Klasse gewinnt — die Datei ist nur wachsend
        // und der älteste Eintrag ist der in der Datenbank gespeicherte.
        $stable = [];
        foreach (self::legacy() as $legacyName => $class) {
            $stable[$class] ??= $legacyName;
        }
        self::$stableByClass = $stable;
        self::$indexSource = $config;
    }
}

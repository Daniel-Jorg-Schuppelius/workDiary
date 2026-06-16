<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinancialFormatsSupport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance;

use RuntimeException;

/**
 * Verfügbarkeits-Guard für das optionale, private Paket
 * `daniel-jorg-schuppelius/php-financial-formats`
 * (Namespace `CommonToolkit\FinancialFormats\…`).
 *
 * Das Paket ist NICHT Teil der committeten composer.json/lock (siehe
 * AGENTS.md §9.1). Jeder Code, der DATEV-/Banking-Formate (CAMT, MT940, Pain,
 * Swift, DATEV-Buchungsstapel) erzeugt oder liest, MUSS vorher
 * {@see self::isAvailable()} prüfen bzw. {@see self::ensureAvailable()} nutzen,
 * damit die App ohne das Paket lauffähig bleibt.
 *
 * Beispiel:
 *   if (! FinancialFormatsSupport::isAvailable()) {
 *       return back()->with('error', __('DATEV-Export ist in dieser Installation nicht verfügbar.'));
 *   }
 *   $generator = new \CommonToolkit\FinancialFormats\Generators\DATEV\DatevDocumentGenerator(...);
 */
final class FinancialFormatsSupport {
    /**
     * Sentinel-Klasse aus dem Paket. Ist sie ladbar, ist das Paket installiert.
     */
    private const SENTINEL = '\\CommonToolkit\\FinancialFormats\\Generators\\DATEV\\DatevDocumentGenerator';

    private static ?bool $available = null;

    /**
     * Ist das optionale Paket installiert und nutzbar?
     */
    public static function isAvailable(): bool {
        return self::$available ??= class_exists(self::SENTINEL);
    }

    /**
     * Wirft eine aussagekräftige Exception, wenn das Paket fehlt.
     * Für Stellen, an denen das Fehlen ein harter, nicht behandelbarer Fehler ist.
     *
     * @throws RuntimeException
     */
    public static function ensureAvailable(): void {
        if (! self::isAvailable()) {
            throw new RuntimeException(
                'Optionales Paket "daniel-jorg-schuppelius/php-financial-formats" ist nicht installiert. '
                    . 'Installation siehe AGENTS.md §9.1 (composer.local.json).'
            );
        }
    }

    /**
     * Übersetzter „Modul nicht aktiviert"-Hinweis inkl. zentraler Kontaktadresse.
     *
     * Die betroffenen Übersetzungen (z. B. `finance.datev.error.unavailable`,
     * `bank.import.error.unavailable`) nutzen den `:contact`-Platzhalter; die
     * Adresse kommt aus {@see config()} `support.module_contact` — so muss sie
     * bei einer Änderung nur an EINER Stelle angepasst werden.
     */
    public static function unavailableMessage(string $key): string {
        return (string) __($key, ['contact' => (string) config('support.module_contact')]);
    }

    /**
     * Nur für Tests: gecachten Status zurücksetzen.
     */
    public static function flush(): void {
        self::$available = null;
    }
}

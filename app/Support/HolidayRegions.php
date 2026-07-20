<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HolidayRegions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

/**
 * Kuratierte Auswahl wählbarer Feiertags-Rechtsräume (Yasumi-Provider) für
 * die Organisations-Einstellungen (Feature 034).
 *
 * Der Wert ist exakt der Yasumi-Provider-Pfad, den {@see \App\Services\HolidayService}
 * an Yasumi::create() übergibt. Die Auswahl ist auf den DACH-Raum fokussiert
 * (alle 16 deutschen Bundesländer + bundesweit, Österreich); weitere Länder
 * können hier additiv ergänzt werden, ohne die Feiertagsberechnung anzufassen.
 *
 * WICHTIG: Nur Provider aufnehmen, die Yasumi tatsächlich kennt — eine
 * unbekannte Region führt im HolidayService zu einer leeren Feiertagsliste.
 */
final class HolidayRegions {
    /**
     * Gruppierte Auswahl: [Ländergruppe => [Provider-Pfad => Anzeigename]].
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array {
        return [
            'Deutschland' => [
                'Germany' => __('holidays.region.de_federal'),
                'Germany\\BadenWurttemberg' => 'Baden-Württemberg',
                'Germany\\Bavaria' => 'Bayern',
                'Germany\\Berlin' => 'Berlin',
                'Germany\\Brandenburg' => 'Brandenburg',
                'Germany\\Bremen' => 'Bremen',
                'Germany\\Hamburg' => 'Hamburg',
                'Germany\\Hesse' => 'Hessen',
                'Germany\\MecklenburgWesternPomerania' => 'Mecklenburg-Vorpommern',
                'Germany\\LowerSaxony' => 'Niedersachsen',
                'Germany\\NorthRhineWestphalia' => 'Nordrhein-Westfalen',
                'Germany\\RhinelandPalatinate' => 'Rheinland-Pfalz',
                'Germany\\Saarland' => 'Saarland',
                'Germany\\Saxony' => 'Sachsen',
                'Germany\\SaxonyAnhalt' => 'Sachsen-Anhalt',
                'Germany\\SchleswigHolstein' => 'Schleswig-Holstein',
                'Germany\\Thuringia' => 'Thüringen',
            ],
            'Österreich' => [
                'Austria' => __('holidays.region.at_federal'),
            ],
            // Vollaudit 2026-07 (M10): Schweiz inkl. aller 26 Kantone —
            // DACH-Akzeptanzkriterium; config/retention.php + legal_region
            // kannten CH bereits, nur die Feiertagsauswahl fehlte.
            'Schweiz' => [
                'Switzerland' => __('holidays.region.ch_federal'),
                'Switzerland\\Aargau' => 'Aargau',
                'Switzerland\\AppenzellAusserrhoden' => 'Appenzell Ausserrhoden',
                'Switzerland\\AppenzellInnerrhoden' => 'Appenzell Innerrhoden',
                'Switzerland\\BaselLandschaft' => 'Basel-Landschaft',
                'Switzerland\\BaselStadt' => 'Basel-Stadt',
                'Switzerland\\Bern' => 'Bern',
                'Switzerland\\Fribourg' => 'Freiburg',
                'Switzerland\\Geneva' => 'Genf',
                'Switzerland\\Glarus' => 'Glarus',
                'Switzerland\\Grisons' => 'Graubünden',
                'Switzerland\\Jura' => 'Jura',
                'Switzerland\\Lucerne' => 'Luzern',
                'Switzerland\\Neuchatel' => 'Neuenburg',
                'Switzerland\\Nidwalden' => 'Nidwalden',
                'Switzerland\\Obwalden' => 'Obwalden',
                'Switzerland\\Schaffhausen' => 'Schaffhausen',
                'Switzerland\\Schwyz' => 'Schwyz',
                'Switzerland\\Solothurn' => 'Solothurn',
                'Switzerland\\StGallen' => 'St. Gallen',
                'Switzerland\\Thurgau' => 'Thurgau',
                'Switzerland\\Ticino' => 'Tessin',
                'Switzerland\\Uri' => 'Uri',
                'Switzerland\\Valais' => 'Wallis',
                'Switzerland\\Vaud' => 'Waadt',
                'Switzerland\\Zug' => 'Zug',
                'Switzerland\\Zurich' => 'Zürich',
            ],
        ];
    }

    /**
     * Flache Liste aller gültigen Provider-Pfade (für Validierung).
     *
     * @return list<string>
     */
    public static function providers(): array {
        $out = [];
        foreach (self::grouped() as $providers) {
            foreach (array_keys($providers) as $provider) {
                $out[] = $provider;
            }
        }

        return $out;
    }

    public static function isValid(string $provider): bool {
        return in_array($provider, self::providers(), true);
    }

    /** Anzeigename eines Provider-Pfads (Fallback: der Pfad selbst). */
    public static function label(string $provider): string {
        foreach (self::grouped() as $providers) {
            if (isset($providers[$provider])) {
                return $providers[$provider];
            }
        }

        return $provider;
    }
}

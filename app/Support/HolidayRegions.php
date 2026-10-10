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
 * Der Wert ist exakt der Yasumi-Provider-Pfad, den {@see \App\Services\Calendar\HolidayService}
 * an Yasumi::create() übergibt. Die Auswahl ist auf den DACH-Raum fokussiert
 * (alle 16 deutschen Bundesländer + bundesweit, Österreich); weitere Länder
 * können hier additiv ergänzt werden, ohne die Feiertagsberechnung anzufassen.
 *
 * WICHTIG: Nur Provider aufnehmen, die Yasumi tatsächlich kennt — eine
 * unbekannte Region führt im HolidayService zu einer leeren Feiertagsliste.
 */
final class HolidayRegions {
    /**
     * Provider-Pfad => Übersetzungsschlüssel (`holidays.region.*`), je Land
     * (`holidays.country.*`). Vollaudit 2026-07 (M10): Schweiz inkl. aller
     * 26 Kantone — DACH-Akzeptanzkriterium.
     */
    private const REGIONS = [
        'de' => [
            'Germany' => 'de_federal',
            'Germany\\BadenWurttemberg' => 'de_bw',
            'Germany\\Bavaria' => 'de_by',
            'Germany\\Berlin' => 'de_be',
            'Germany\\Brandenburg' => 'de_bb',
            'Germany\\Bremen' => 'de_hb',
            'Germany\\Hamburg' => 'de_hh',
            'Germany\\Hesse' => 'de_he',
            'Germany\\MecklenburgWesternPomerania' => 'de_mv',
            'Germany\\LowerSaxony' => 'de_ni',
            'Germany\\NorthRhineWestphalia' => 'de_nw',
            'Germany\\RhinelandPalatinate' => 'de_rp',
            'Germany\\Saarland' => 'de_sl',
            'Germany\\Saxony' => 'de_sn',
            'Germany\\SaxonyAnhalt' => 'de_st',
            'Germany\\SchleswigHolstein' => 'de_sh',
            'Germany\\Thuringia' => 'de_th',
        ],
        'at' => [
            'Austria' => 'at_federal',
        ],
        'ch' => [
            'Switzerland' => 'ch_federal',
            'Switzerland\\Aargau' => 'ch_ag',
            'Switzerland\\AppenzellAusserrhoden' => 'ch_ar',
            'Switzerland\\AppenzellInnerrhoden' => 'ch_ai',
            'Switzerland\\BaselLandschaft' => 'ch_bl',
            'Switzerland\\BaselStadt' => 'ch_bs',
            'Switzerland\\Bern' => 'ch_be',
            'Switzerland\\Fribourg' => 'ch_fr',
            'Switzerland\\Geneva' => 'ch_ge',
            'Switzerland\\Glarus' => 'ch_gl',
            'Switzerland\\Grisons' => 'ch_gr',
            'Switzerland\\Jura' => 'ch_ju',
            'Switzerland\\Lucerne' => 'ch_lu',
            'Switzerland\\Neuchatel' => 'ch_ne',
            'Switzerland\\Nidwalden' => 'ch_nw',
            'Switzerland\\Obwalden' => 'ch_ow',
            'Switzerland\\Schaffhausen' => 'ch_sh',
            'Switzerland\\Schwyz' => 'ch_sz',
            'Switzerland\\Solothurn' => 'ch_so',
            'Switzerland\\StGallen' => 'ch_sg',
            'Switzerland\\Thurgau' => 'ch_tg',
            'Switzerland\\Ticino' => 'ch_ti',
            'Switzerland\\Uri' => 'ch_ur',
            'Switzerland\\Valais' => 'ch_vs',
            'Switzerland\\Vaud' => 'ch_vd',
            'Switzerland\\Zug' => 'ch_zg',
            'Switzerland\\Zurich' => 'ch_zh',
        ],
    ];

    /**
     * Gruppierte Auswahl in der aktiven Sprache: [Land => [Provider-Pfad => Anzeigename]].
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array {
        $grouped = [];
        foreach (self::REGIONS as $country => $regions) {
            $grouped[(string) __('holidays.country.' . $country)] = array_map(
                static fn (string $key): string => (string) __('holidays.region.' . $key),
                $regions,
            );
        }

        return $grouped;
    }

    /**
     * Flache Liste aller gültigen Provider-Pfade (für Validierung).
     *
     * @return list<string>
     */
    public static function providers(): array {
        return array_merge(...array_map(array_keys(...), array_values(self::REGIONS)));
    }

    public static function isValid(string $provider): bool {
        return in_array($provider, self::providers(), true);
    }

    /** Anzeigename eines Provider-Pfads (Fallback: der Pfad selbst). */
    public static function label(string $provider): string {
        foreach (self::REGIONS as $regions) {
            if (isset($regions[$provider])) {
                return (string) __('holidays.region.' . $regions[$provider]);
            }
        }

        return $provider;
    }
}

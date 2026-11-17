<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer-tax-2025-2027.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Eingefrorene Steuertermine des Regelsatzes Steuerberatung (Feature 197,
 * MVP-1631), von Hand gegen Wortlaut und Kalender geprüft am 2026-11-12.
 *
 * Quellen (Stand 2026):
 * - § 18 Abs. 1 UStG, § 46–48 UStDV: Voranmeldung am 10. Tag nach Ablauf des
 *   Zeitraums, Dauerfristverlängerung +1 Monat, Sondervorauszahlung mit dem
 *   Antrag bis 10.02. — https://www.gesetze-im-internet.de/ustg_1980/__18.html,
 *   https://www.gesetze-im-internet.de/ustdv_1980/__46.html
 * - § 41a Abs. 1 EStG: Lohnsteuer-Anmeldung am 10. Tag nach Ablauf des
 *   Anmeldungszeitraums, ohne Dauerfristverlängerung —
 *   https://www.gesetze-im-internet.de/estg/__41a.html
 * - § 18a Abs. 1 UStG: Zusammenfassende Meldung am 25. Tag nach Ablauf des
 *   Meldezeitraums, ohne Verlängerung — https://www.gesetze-im-internet.de/ustg_1980/__18a.html
 * - § 149 Abs. 2 und 3 AO (31.07. bzw. letzter Februartag des zweiten
 *   Folgejahres; Land- und Forstwirtschaft: 7 Monate nach Ende des
 *   Wirtschaftsjahres bzw. 31.07. des zweiten Folgejahres) —
 *   https://www.gesetze-im-internet.de/ao_1977/__149.html
 * - Art. 97 § 36 Abs. 3 EGAO, Übergangsfristen der Besteuerungszeiträume
 *   2020–2024 — https://www.gesetze-im-internet.de/aoeg_1977/art_97__36.html
 * - § 108 Abs. 3 AO: Sonnabend, Sonntag, gesetzlicher Feiertag am Ort der
 *   Behörde → nächster Werktag — https://www.gesetze-im-internet.de/ao_1977/__108.html
 *
 * Schlüssel: Region|Merkmale|Fristart|Periode → erwartetes Datum. Merkmale:
 * `ext` Dauerfristverlängerung, `adv` beraten, `agri7` Land- und
 * Forstwirtschaft mit Wirtschaftsjahr ab Juli.
 */

return [
    // Abnahme 1: monatlich mit Dauerfristverlängerung.
    'Germany\\Berlin|ext|vat_advance|2027-01' => '2027-03-10',
    'Germany\\Berlin|ext|vat_advance|2026-12' => '2027-02-10',
    // Ohne Verlängerung: 10.02.2026 ist ein Dienstag.
    'Germany\\Berlin|-|vat_advance|2026-01' => '2026-02-10',
    // 10.05.2026 ist ein Sonntag → Montag.
    'Germany\\Berlin|-|vat_advance|2026-04' => '2026-05-11',
    'Germany\\Berlin|-|vat_advance|2026-Q1' => '2026-04-10',
    // Quartal IV mit Verlängerung: 10.01.2027 + 1 Monat = Mittwoch 10.02.2027.
    'Germany\\Berlin|ext|vat_advance|2026-Q4' => '2027-02-10',
    'Germany\\Berlin|ext|vat_special_prepayment|2026' => '2026-02-10',
    'Germany\\Berlin|ext|vat_special_prepayment|2027' => '2027-02-10',
    // Lohnsteuer: nie verlängert; 10.01.2027 Sonntag → Montag.
    'Germany\\Berlin|ext|wage_tax|2026-12' => '2027-01-11',
    'Germany\\Berlin|-|wage_tax|2026-Q2' => '2026-07-10',
    // Jährliche Anmeldung 2025: 10.01.2026 Sonnabend → Montag.
    'Germany\\Berlin|-|wage_tax|2025' => '2026-01-12',
    // ZM: 25.05.2026 Pfingstmontag → 26.05.; die Verlängerung greift nicht.
    'Germany\\Berlin|ext|recapitulative|2026-04' => '2026-05-26',
    // 25.10.2026 ist ein Sonntag.
    'Germany\\Berlin|-|recapitulative|2026-Q3' => '2026-10-26',
    // Abnahme 2: beraten, 2025 → 28.02.2027 (Sonntag) → 01.03.2027.
    'Germany\\Berlin|adv|annual_return|2025' => '2027-03-01',
    'Germany\\Berlin|-|annual_return|2025' => '2026-07-31',
    'Germany\\Berlin|adv|annual_return|2026' => '2028-02-29',
    // 31.07.2027 Sonnabend → Montag.
    'Germany\\Berlin|-|annual_return|2026' => '2027-08-02',
    // Übergang Art. 97 § 36 EGAO: 2024 beraten 30.04.2026, 2023 beraten 31.05.2025 (Sonnabend → 02.06.).
    'Germany\\Berlin|adv|annual_return|2024' => '2026-04-30',
    'Germany\\Berlin|adv|annual_return|2023' => '2025-06-02',
    // 2023 unberaten: acht Monate → 31.08.2024 (Sonnabend) → 02.09.2024; 2024 wieder sieben Monate.
    'Germany\\Berlin|-|annual_return|2023' => '2024-09-02',
    'Germany\\Berlin|-|annual_return|2024' => '2025-07-31',
    // Land- und Forstwirtschaft, Wirtschaftsjahr ab Juli.
    'Germany\\Berlin|adv,agri7|annual_return|2024' => '2026-09-30',
    'Germany\\Berlin|adv,agri7|annual_return|2025' => '2027-08-02',
    // Unberaten: Wirtschaftsjahr 07/2025–06/2026, sieben Monate später 31.01.2027 (Sonntag) → 01.02.2027.
    'Germany\\Berlin|agri7|annual_return|2025' => '2027-02-01',
    // Abnahme 3: 31.10.2025 (Übergang 2023, Nr. 2) ist Reformationstag in Niedersachsen, nicht in Bayern.
    'Germany\\LowerSaxony|adv,agri7|annual_return|2023' => '2025-11-03',
    'Germany\\Bavaria|adv,agri7|annual_return|2023' => '2025-10-31',
];

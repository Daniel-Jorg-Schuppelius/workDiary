<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : social-security-2026-2027.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Eingefrorene Beitragstermine der Sozialversicherung (Feature 197, MVP-1676):
 * Monat → [Beitragsnachweis, Fälligkeit], Einzugsstelle ohne regionalen
 * Feiertag in der Monatsmitte (Berlin). Fälligkeit am drittletzten
 * Bankarbeitstag (§ 23 Abs. 1 SGB IV), Nachweis zwei Arbeitstage davor
 * (§ 28f Abs. 3 SGB IV); 24.12. und 31.12. sind keine Bankarbeitstage.
 *
 * Quellen, abgerufen am 2026-11-12:
 * - 2026: https://sozialversicherung-kompetent.de/krankenversicherung/zahlen-werte/1614-beitragsnachweis-abgabetermine-2026.html
 * - Oktober 2026 bis Dezember 2027: https://lohn-info.de/meldewesen_faelligkeit_beitragsnachweis.html
 * - Regel: https://www.aok.de/fk/sozialversicherung/beitraege-zur-sozialversicherung/faelligkeit-der-sozialversicherungsbeitraege/
 * - § 23 SGB IV: https://www.gesetze-im-internet.de/sgb_4/__23.html, § 28f SGB IV: https://www.gesetze-im-internet.de/sgb_4/__28f.html
 *
 * Oktober und Dezember 2026: Veröffentlichte Tabellen weichen dort
 * voneinander ab. Gegen den Bankarbeitstagskalender geklärt: Oktober — 31.10.
 * ist ein Sonnabend, letzte Bankarbeitstage 30./29./28.10. → Fälligkeit
 * Mittwoch 28.10., Nachweis zwei Bankarbeitstage davor Montag 26.10.
 * Dezember — 31.12. und 24.12. zählen nicht, 25./26.12. Feiertage bzw.
 * Wochenende: letzte Bankarbeitstage 30./29./28.12. → Fälligkeit Montag
 * 28.12., Nachweis zurück über 24.–27.12. auf Dienstag 22.12. Eine Tabelle mit
 * 23.12. zählt den 24.12. als Arbeitstag mit — die Fälligkeit (28.12.)
 * bleibt gleich, der frühere Nachweistermin ist der sichere. Beide hier
 * genannten Quellen stimmen damit überein.
 */

return [
    'Germany\\Berlin' => [
        '2026-01' => ['2026-01-26', '2026-01-28'],
        '2026-02' => ['2026-02-23', '2026-02-25'],
        '2026-03' => ['2026-03-25', '2026-03-27'],
        '2026-04' => ['2026-04-24', '2026-04-28'],
        '2026-05' => ['2026-05-22', '2026-05-27'],
        '2026-06' => ['2026-06-24', '2026-06-26'],
        '2026-07' => ['2026-07-27', '2026-07-29'],
        '2026-08' => ['2026-08-25', '2026-08-27'],
        '2026-09' => ['2026-09-24', '2026-09-28'],
        '2026-10' => ['2026-10-26', '2026-10-28'],
        '2026-11' => ['2026-11-24', '2026-11-26'],
        '2026-12' => ['2026-12-22', '2026-12-28'],
        '2027-01' => ['2027-01-25', '2027-01-27'],
        '2027-02' => ['2027-02-22', '2027-02-24'],
        '2027-03' => ['2027-03-23', '2027-03-25'],
        '2027-04' => ['2027-04-26', '2027-04-28'],
        '2027-05' => ['2027-05-25', '2027-05-27'],
        '2027-06' => ['2027-06-24', '2027-06-28'],
        '2027-07' => ['2027-07-26', '2027-07-28'],
        '2027-08' => ['2027-08-25', '2027-08-27'],
        '2027-09' => ['2027-09-24', '2027-09-28'],
        '2027-10' => ['2027-10-25', '2027-10-27'],
        '2027-11' => ['2027-11-24', '2027-11-26'],
        '2027-12' => ['2027-12-23', '2027-12-28'],
    ],
    // Fronleichnam (27.05.2027) ist in Bayern Feiertag: lohn-info.de nennt dort 24.05./26.05.
    'Germany\\Bavaria' => [
        '2027-05' => ['2027-05-24', '2027-05-26'],
    ],
];

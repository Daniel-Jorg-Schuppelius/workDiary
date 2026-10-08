---
title: "ArbZG-Compliance"
topic: reports.arbzg-compliance
version: 1
keywords:
    - Arbeitszeitgesetz
    - Arbeitszeitverstoß
    - Höchstarbeitszeit
    - Ruhezeit
    - Pausenregelung
    - Pflichtpause
    - 10-Stunden-Grenze
    - Jugendarbeitsschutz
    - JArbSchG
    - Nachtarbeit
    - MiLoG
    - Aufzeichnungspflicht
    - Arbeitszeitprüfung
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.overview
    - reports.drilldown
    - reports.compliance
    - reports.fleet
---

Die ArbZG-Compliance-Auswertung prüft die **tatsächlich erfasste Arbeitszeit**
(Stempelungen/Anwesenheiten, netto nach Pausen) je Mitarbeiter und Tag gegen die
Schwellen des Arbeitszeitgesetzes. Sie ist die Ist-Sicht — die Plan-Compliance
des Dienstplans bleibt davon unberührt.

Geprüft werden:

- **Tageshöchstarbeitszeit** – Verstoß, wenn die Netto-Arbeitszeit eines Tages
  die Tagesgrenze (Standard 10 h, ArbZG §3) überschreitet.
- **Ruhezeit** – Verstoß, wenn zwischen Ende des einen und Beginn des nächsten
  Arbeitstags weniger als die Mindestruhezeit (Standard 11 h, ArbZG §5) liegt.
- **Pflichtpause** – Verstoß, wenn die erfassten Pausen die gesetzliche
  Mindestpause unterschreiten (ArbZG §4: 30 min ab 6 h, 45 min ab 9 h).
- **Wochenhöchstarbeitszeit** – Hinweis, wenn die Wochensumme die
  Durchschnittsgrenze (Standard 48 h, ArbZG §3) überschreitet.

Die Schwellen stammen aus den Compliance-Einstellungen der Organisation und sind
identisch zu Tagesabschluss und Dienstplan-Prüfung.

Jeder Eintrag verlinkt über **Zum Tagesabschluss** auf den betroffenen Tag.
Liegt für einen Tag eine genehmigte Zeitkorrektur vor, ist der Eintrag mit
**korrigiert** markiert. Die Liste lässt sich als CSV oder PDF exportieren.

**Nachtzeit und Jugendliche:** Die Nachtzeit (Standard 23–6 Uhr, in Bäckereien
22–5 Uhr) stellen Sie in den Compliance-Einstellungen ein; bei der
Durchschnittsprüfung nach § 3 zählen Feiertage nicht als Werktage. Ist am
Mitarbeiter ein Geburtsdatum hinterlegt, prüft die Auswertung die Tage vor dem
18. Geburtstag zusätzlich nach dem Jugendarbeitsschutzgesetz: höchstens 8 h
täglich und 40 h wöchentlich, Ruhepausen (30 min ab 4,5 h, 60 min ab 6 h),
12 h Freizeit, keine Arbeit zwischen 20 und 6 Uhr, höchstens 5 Arbeitstage je
Woche. Arbeit am Wochenende und Nachtarbeit ab 16 Jahren erscheinen als
Hinweis, weil das Gesetz dafür Branchenausnahmen kennt.

Die MiLoG-Aufzeichnungsfrist (sieben Tage) misst ab dem ursprünglichen
Erfassungszeitpunkt: bei Stempeln der Stempelmoment, auch wenn ein Gerät
offline erst später überträgt; bei Importen die Spalte „erfasst am“. Importe
ohne diese Angabe bleiben bei der Frist ungeprüft.

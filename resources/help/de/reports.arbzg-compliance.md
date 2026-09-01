---
title: "ArbZG-Compliance"
topic: reports.arbzg-compliance
version: 1
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.overview
    - reports.drilldown
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

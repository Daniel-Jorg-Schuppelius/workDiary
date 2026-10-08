---
title: "Zeiteintrag bearbeiten"
topic: time-entries.edit
version: 2
keywords:
    - Zeit korrigieren
    - Zeitbuchung ändern
    - Stunden anpassen
    - falsche Zeit
    - Beginn und Ende ändern
    - Pause ändern
    - Projekt umbuchen
    - Korrekturantrag
    - gesperrter Eintrag
    - Änderungsprotokoll
    - Verwaltungszeit
    - interne Zeit erfassen
audience: []
related:
    - time-entries.start
    - reports.customer-analysis
---

Klicken Sie auf eine Zeile in der Zeiterfassungs-Liste, um den Eintrag zu
bearbeiten. Änderungen werden im Audit-Log mit Person, Zeitpunkt und
vorherigem Wert protokolliert.

Wichtig:

- **Bereits freigegebene** Einträge sind gesperrt. Für nachträgliche
  Korrekturen nutzen Sie die **Korrekturanträge**.
- Ändern Sie niemals nur das Ende einer Schicht – passen Sie **immer** Beginn,
  Ende, Pause als Set an, sonst werden Auswertungen inkonsistent.
- Wechsel des Projekts ist erlaubt, sofern die alte Projektzuordnung nicht
  bereits abgerechnet wurde.

## Verwaltungszeit

Verwaltungszeit ist Arbeitszeit ohne Projekt: Besprechungen, Schulungen,
interne Arbeiten, Reisezeit, Pausen und Sonstiges. Sie erfassen sie immer
für sich selbst – der Eintrag wird Ihrem eigenen Konto zugeordnet. Eine
Erfassung für andere Personen ist hier nicht vorgesehen.

**Einstieg:**

- In der Seitenleiste über **Neu …** → **Tagesgeschäft** → **Verwaltungszeit**.
  Das Datum ist mit heute vorbelegt.
- In der Tagesansicht (**Tagesgeschäft** → **Erfassung** → **Heute**) über die
  Schaltfläche **Verwaltungszeit** oben rechts. Das Datum ist mit dem
  angezeigten Tag vorbelegt – auch mit einem früheren Tag, wenn Sie über
  **Vortag** dorthin geblättert haben.

**Felder im Dialog „Verwaltungszeit erfassen“:**

- **Datum** und **Dauer (Minuten)** sind Pflicht. Die Dauer liegt zwischen
  1 und 1440 Minuten; vorbelegt sind 30 Minuten.
- **Tätigkeitstyp** (Pflicht): **Verwaltung** (Vorgabe), **Besprechung**,
  **Schulung**, **Intern**, **Reise**, **Pause** oder **Sonstiges**.
- **Kategorie (optional)**: eine der aktiven Tätigkeitskategorien Ihrer
  Organisation; die Auswahl zeigt zu jeder Kategorie ihren Tätigkeitstyp.
- **Zeitraum (optional)**: **Beginn (Uhrzeit)** und **Ende (Uhrzeit)**. Ein
  Ende ohne Beginn wird abgelehnt. Ist das Ende früher als der Beginn, zählt
  es zum Folgetag – so erfassen Sie Zeiten über Mitternacht. Sind Beginn und
  Ende angegeben, berechnet die Anwendung die Dauer daraus und ersetzt die
  eingetragene Minutenzahl.
- **Beschreibung** (bis 500 Zeichen) und **Tags**.
- Gibt es für das vorbelegte Datum bereits eine Stempelung von Ihnen, wird
  der Eintrag mit ihr verknüpft. Der Dialog zeigt dann den Hinweis „Wird mit
  Stempelung verknüpft (seit …)“.

Nach **Erfassen** kehren Sie zur Tagesansicht des gewählten Datums zurück.
Der Eintrag steht dort unter **Zeiteinträge** mit seinem Tätigkeitstyp und,
falls gewählt, mit der Kategorie.

**Unterschiede zur Zeiterfassung mit Projekt:**

- Es gibt kein Projektfeld; eingeordnet wird die Zeit über Tätigkeitstyp und
  Kategorie.
- Beginn und Ende sind freiwillig – eine Dauer genügt.
- Der Tätigkeitstyp ist auf die oben genannten Arten ohne Projektbezug
  beschränkt.

**Bearbeiten und löschen:** In der Tagesansicht **Heute** öffnet das
Stift-Symbol (**Bearbeiten**) in der Zeile einer Verwaltungszeit den Dialog
**Verwaltungszeit bearbeiten**. Im Dialog **Verwaltungszeit bearbeiten** ändern
Sie dieselben Felder, schreiben **Kommentare** und entfernen den Eintrag mit
**Löschen** (nach einer Sicherheitsabfrage). Nach dem Speichern oder Löschen
öffnet sich die Tagesansicht des Eintragsdatums. Bearbeiten und löschen
dürfen Sie nur eigene Einträge und nur, solange sie nicht gesperrt sind.
Gesperrt ist ein Eintrag, wenn

- das Korrekturfenster abgelaufen ist (standardmäßig 7 Tage nach dem Tag des
  Eintrags),
- der Monat für Sie bereits freigegeben ist,
- der zugehörige Stundenzettel signiert oder gesperrt ist oder
- der Eintrag bereits exportiert wurde.

Der Dialog nennt dann den Grund; Kommentare bleiben weiterhin möglich.

**Berechtigung:** Erfassen darf jede angemeldete Person für sich selbst. Die
Rolle **Administrator** darf auch fremde und gesperrte Einträge bearbeiten
und löschen; der Dialog weist dann darauf hin, dass Sie als Admin bearbeiten.

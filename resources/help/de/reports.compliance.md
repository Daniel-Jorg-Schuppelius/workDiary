---
title: "Nachweise: Audit-Aktivität, Compliance und Mindestlohn"
topic: reports.compliance
version: 3
keywords:
    - Audit-Auswertung
    - wer hat was geändert
    - Exporte nachvollziehen
    - Compliance-Übersicht
    - Arbeitszeitverstöße
    - Verstöße quittieren
    - Verstoß akzeptieren
    - ungeklärte Fälle
    - Mindestlohn-Nachweis
    - Zollprüfung
    - Arbeitszeitnachweis
    - Aufzeichnungspflicht
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
related:
    - reports.arbzg-compliance
    - audit.log
    - corrections.requests
    - attendance.manage
    - reports.fleet
    - admin.organization-settings
---

Diese Seiten dienen als Nachweis gegenüber Prüfern und Behörden: wer im
System was getan hat, wie es um Verstöße gegen das Arbeitszeitgesetz steht
und wie sie bearbeitet wurden, und der Arbeitszeitnachweis nach dem
Mindestlohngesetz für den Zoll. Welche Regeln die ArbZG-Compliance prüft und
wie die Einzelliste aussieht, beschreibt das Thema zur ArbZG-Compliance.

## Zeitraum und Export

- Den Zeitraum wählen Sie über die Zeitraumwahl in der Kopfzeile. Die
  **Verstoß-Historie** zeigt dagegen alle gespeicherten Verstöße.
- Wo es einen Export gibt, ist er beim jeweiligen Abschnitt genannt. Jeder
  Export wird im Audit-Protokoll vermerkt.

## Audit-Aktivität

**Auswertungen** → **Finanzen & Audit** → **Audit-Aktivität** fasst die
Einträge des Audit-Protokolls im Zeitraum zusammen. Die Seite öffnet sich nur
für Administratoren; allen anderen wird der Zugriff verweigert.

- Kacheln: **Events Σ** (alle Einträge im Zeitraum), **Aktive Benutzer**
  (Personen mit mindestens einem Eintrag) und **Entity-Typen** (verschiedene
  Objekttypen). Auch diese beiden zählen über alle Einträge im Zeitraum, nicht
  nur über die Top-20-Listen.
- Diagramme: **Ereignisse im Verlauf**, **Top-Akteure (Top 15)** und die
  Ereignisse im Verlauf nach Ereignistyp.
- Tabellen: **Nach Event**, **Nach Entity-Typ (Top 20)**, **Nach Benutzer
  (Top 20)** und **Letzte 100 Events** mit **Zeitpunkt**, **Benutzer**,
  **Event**, **Typ**, **ID** und **IP**. Ereignisse und Typen erscheinen mit
  ihrem lesbaren Namen, soweit einer hinterlegt ist.
- Filter: **Mitarbeiter**.

Auch das Exportieren von Berichten erzeugt einen Eintrag mit Bericht, Format
und Filtern; so lässt sich nachvollziehen, wer welche Auswertung
heruntergeladen hat. Einzelne Einträge mit allen Details zeigt das
**Audit-Log**. Export als PDF, CSV und Excel.

## Compliance-Dashboard

Das **Compliance-Dashboard** öffnen Sie über den Reiter **Dashboard** auf der
Seite **ArbZG-Compliance** (**Auswertungen** → **Finanzen & Audit** →
**ArbZG-Compliance**). Die Reiter **Dashboard**, **Einzelreport** und
**Verstoß-Historie** verbinden die drei Sichten. Nötig ist das Recht
**ArbZG-Compliance einsehen**.

Das Dashboard ermittelt die Befunde des Zeitraums wie der Einzelreport aus
den erfassten Arbeitszeiten; sind die Lenkzeitregeln eingeschaltet, kommen
die Lenk- und Ruhezeitbefunde hinzu.

- Kacheln: **Befunde gesamt** (ein Klick öffnet den Einzelreport),
  **Betroffene Mitarbeitende**, **Offen (ohne Korrektur)** und **Mit
  genehmigter Korrektur** (Befunde an Tagen mit genehmigter Zeitkorrektur).
- Je Verstoßart eine Kachel mit der Anzahl; ein Klick öffnet den
  Einzelreport, gefiltert auf diese Art.
- Diagramme: offene Befunde im Verlauf und Befunde im Verlauf nach
  Verstoßart.
- **Verstöße je Regel und Monat**: je Monat die Befunde jeder Verstoßart mit
  **Summe**.
- **Befunde je Team**: bewusst nach Teams statt nach Personen. Wer mehreren
  Teams angehört, zählt in jedem; Personen ohne Team stehen unter **Ohne
  Team**.

Filter: **Team**. Das Dashboard bietet keinen Export.

## Verstoß-Historie

Der Reiter **Verstoß-Historie** öffnet die Seite **Compliance-Verstöße** mit
den gespeicherten Verstößen und ihrem Bearbeitungsstand. Ein regelmäßiger
Abgleich, standardmäßig einmal täglich in der Nacht, speichert neue Befunde.
Wird ein Befund dabei nicht mehr erkannt, setzt der Abgleich ihn auf
**Behoben**; tritt er erneut auf, steht er wieder auf **Offen**.

- Kacheln je Status: **Offen**, **Quittiert**, **Behoben** und
  **Akzeptiert**, gezählt über die ganze Organisation. Ein Klick filtert die
  Liste.
- Diagramm **Neue vs. quittierte Befunde je Monat** für die letzten 24 Monate
  mit Daten.
- Liste: **Mitarbeiter**, **Datum**, **Art**, **Wert**, **Schwelle**,
  **Schweregrad** und **Status**; bei bearbeiteten Verstößen stehen darunter
  Name, Datum und Begründung.
- Filter: **Mitarbeiter**, **Team**, **Status** und **Kategorie** (**ArbZG**,
  **Ungeklärte Fälle**, **Lenkzeiten**).

So bearbeiten Sie einen Verstoß im Status **Offen** oder **Quittiert**:

1. Tragen Sie bei Bedarf eine Begründung in das Feld **Begründung (Pflicht bei
   „akzeptiert")** ein.
2. Wählen Sie **Quittieren**, um den Verstoß zur Kenntnis zu nehmen, oder
   **Akzeptieren**, um ihn bewusst hinzunehmen. Für **Akzeptieren** ist die
   Begründung Pflicht.

Jeder Statuswechsel wird im Audit-Protokoll festgehalten. Bei **Ungeklärte
Fälle** öffnet ein zusätzliches Symbol einen **Korrekturantrag** mit dem Tag
des Befunds. Die Liste ist nicht auf den Zeitraum beschränkt und zeigt 50
Einträge je Seite. Die Seite bietet keinen Export.

## MiLoG-Nachweis (Zoll)

Den **MiLoG-Nachweis (Zoll)** laden Sie auf der Seite **ArbZG-Compliance** im
Menü **Export** herunter. Er dient als Aufzeichnung nach § 17 Abs. 1 MiLoG
und verlangt das Recht **ArbZG-Compliance einsehen**.

- Die CSV-Datei enthält je Mitarbeiter und Kalendertag **Mitarbeiter**,
  **Personalnummer**, **Datum**, **Beginn**, **Ende**, **Pausen (Min.)** und
  **Dauer**.
- Grundlage sind die abgeschlossenen Stempelzeiten; abgesagte und noch offene
  Stempelungen zählen nicht. **Beginn** ist der erste Start, **Ende** das
  letzte Ende des Tages, die Pausen werden addiert, und **Dauer** ist die
  Arbeitszeit nach Abzug der Pausen.
- Sortiert ist nach Name und Datum. Der Download übernimmt Zeitraum,
  Mitarbeiter- und Teamfilter der Seite und wird im Audit-Protokoll vermerkt.

Den **Lenkzeit-Nachweis** im selben Menü beschreibt das Thema zu Fuhrpark,
Fahrtenbuch und Lenkzeiten.

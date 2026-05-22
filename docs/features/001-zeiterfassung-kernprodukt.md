# Aufzeichnung und Zeiterfassung als Kernprodukt

## Status

Proposed — Auftrags-Lebenszyklus (Annahme/Bearbeitung/Abschluss) als MVP-011
konzipiert: [docs/auftrags-lebenszyklus.md](../auftrags-lebenszyklus.md).
Auftrags-Timeline (MVP-010): [docs/auftrags-timeline.md](../auftrags-timeline.md).
Tagesabschluss-Ansicht (MVP-015): [docs/tagesabschluss.md](../tagesabschluss.md).

## Ziel

WorkDiary soll als Aufzeichnungstool verstanden werden: Es weist nach, wer wann
welche Aufträge angenommen, geplant, begonnen, bearbeitet, unterbrochen,
abgeschlossen oder übergeben hat. Dazu gehört, wie viel Zeit verbraucht wurde,
welche Dienstmittel, Fahrzeuge, Materialien, Anhänge und Notizen genutzt wurden
und welche Abweichungen, Protokolle, Abnahmen oder Freigaben es gab.

Zeiterfassung bleibt ein zentrales Thema, ist aber nicht isoliert zu betrachten.
Sie ist die zeitliche Achse des Nachweises und verbindet Auftrag, Mitarbeitende,
Dienstplan, Projekt, Material, Fahrt, Spesen, Protokolle, Abnahme, Rechnung,
Audit und Auswertung.

## Warum

Viele Anbieter können Arbeitszeit erfassen. Die Differenzierung entsteht, wenn
WorkDiary daraus einen belastbaren Arbeits- und Auftragsnachweis macht. Für
Betriebe zählt nicht nur, dass jemand acht Stunden gearbeitet hat, sondern an
welchen Aufträgen, mit welchen Mitteln, mit welchem Ergebnis und mit welcher
Verantwortung diese Zeit verbraucht wurde.

Das ist besonders relevant für Betriebe, die Service-, Einsatz-, Bereitschafts-
oder Projektarbeit später gegenüber Kunden, Führungskräften, Buchhaltung,
Steuerberatung oder internen Prüfungen erklären müssen.

Die Aufzeichnung muss außerdem auswertbar sein. Eine Firma soll daraus ableiten
können, ob ein Kunde überdurchschnittlich viel Aufwand verursacht, ob bestimmte
Produkte wiederkehrende Probleme erzeugen, ob Fortbildungen nötig sind, wie
effizient Teams arbeiten und wo Prozesse oder Angebote angepasst werden müssen.

## Zielgruppen

- Kleine und mittlere Servicebetriebe mit Außendienst.
- Handwerk, Facility Management, IT-Service, Bereitschafts- und Notdienstteams.
- Projektorientierte Dienstleister mit abrechenbaren und nicht abrechenbaren Zeiten.
- Organisationen mit Gleitzeit, Schichtplanung und Prüfpflichten.

## Vorhandene Basis

- `Attendance` als Quelle der Wahrheit für Anwesenheit.
- `TimeEntry` für Projekt-, Verwaltungs-, Reise-, Pausen- und interne Zeiten.
- `DiaryEntry`, `Project`, `Task` und `Timesheet` als fachlicher Auftrags- und Arbeitsnachweis.
- `MaterialUsage`, `Vehicle`, `TravelLog`, `Expense` und Anhänge für genutzte Dienstmittel und Belege.
- Kommentare, Anhänge, PDF-Ausgabe und Signaturen als Grundlage für Protokolle
  und Abnahmen.
- Stempeluhr, Stoppuhr, Projektzeiten, Verwaltungszeiten.
- Automatische Pausenregeln nach Konfiguration.
- Arbeitszeitmodell, Gleitzeit und Arbeitsbilanz.
- Fahrtenbuch mit automatischer Reisezeit.
- Reports für Arbeitsbilanz, Anwesenheit, Projekte und Abwesenheiten.
- Reporting-Basis für Kunden-, Projekt-, Material-, Fuhrpark-, Abrechnungs-,
  Qualifikations- und Operations-Auswertungen.

Siehe auch [Zeiterfassung in workDiary](../zeiterfassung.md).

## Nachweisfragen

WorkDiary soll diese Fragen zuverlässig beantworten können:

- Wer hat einen Auftrag angenommen oder zugewiesen bekommen?
- Wann wurde die Arbeit begonnen, pausiert, fortgesetzt, abgeschlossen oder übergeben?
- Welche Personen waren beteiligt?
- Wie viel Anwesenheitszeit, Projektzeit, Reisezeit und Bereitschaftszeit wurde verbraucht?
- Welche Fahrzeuge, Geräte, Materialien oder sonstigen Dienstmittel wurden genutzt?
- Welche Fotos, Dokumente, Notizen, Kommentare oder Kundenbestätigungen gehören dazu?
- Welche Protokolle, Checklisten, Messwerte, Mängel, offenen Punkte und
  Unterschriften sichern den Auftrag ab?
- Welche Abweichungen, Korrekturen, Freigaben oder Ablehnungen gab es?
- Welche Daten sind abrechenbar, lohnrelevant oder nur intern zu dokumentieren?
- Welche Muster entstehen über viele Aufträge hinweg: schwierige Kunden,
  wiederkehrende Produktprobleme, Schulungsbedarf, ineffiziente Abläufe?

## MVP

- Auftragslebenslauf: Annahme, Bearbeitung, Statuswechsel, Übergaben,
  Kommentare, Anhänge und Abschluss werden nachvollziehbar protokolliert.
- Tagesabschluss: Mitarbeitende sehen Anwesenheit, Pausen, erfasste Zeiten,
  offene Restzeit und Warnungen auf einen Blick.
- Auftragsbezogene Zeitverwendung: Zeit wird Auftrag, Projekt, Aufgabe,
  Dienst, Fahrt oder interner Tätigkeit zugeordnet.
- Dienstmittel-Nachweis: genutzte Fahrzeuge, Materialien, Anhänge und Belege
  werden mit Auftrag oder Stundenzettel verbunden.
- Protokoll-Nachweis: Baustellen-, Produkt-, Wartungs-, Mängel- oder
  Abnahmeprotokolle können mit Fotos, Checklisten und Unterschriften am Auftrag
  hängen.
- Monatsfreigabe: Mitarbeitende reichen ihren Monat ein; Admins prüfen,
  kommentieren, genehmigen oder zur Korrektur zurückgeben.
- Korrekturanträge: nachträgliche Änderungen werden beantragt, begründet und
  revisionsfähig protokolliert.
- Plan/Ist-Abgleich: Schicht, Anwesenheit und gebuchte Tätigkeiten werden pro
  Tag gegenübergestellt.
- Erfassungslücken: automatische Hinweise bei nicht verteilter Anwesenheitszeit,
  fehlender Pause, offener Stempeluhr oder überschrittenem Arbeitszeitrahmen.
- Exportgrundlage: geprüfte Zeiten können später zuverlässig an Lohnabrechnung,
  Rechnungstellung und Controlling übergeben werden.
- Auswertungsgrundlage: Aufträge, Zeiten, Ursachen, Produkte, Dienstmittel und
  Ergebnisse werden so strukturiert erfasst, dass spätere Kennzahlen und
  Diagramme belastbar sind.

## Akzeptanzkriterien

- Ein Mitarbeitender kann am Tagesende eindeutig erkennen, ob der Tag vollständig
  und plausibel erfasst ist.
- Ein Auftrag kann später als Verlauf gelesen werden: Annahme, Bearbeitung,
  Zeitverbrauch, Dienstmittel, Beteiligte, Anhänge und Abschluss.
- Abnahme- und Dokumentationsdaten sind Teil des Auftragsverlaufs, nicht ein
  loses PDF ohne Datenbezug.
- Eine Führungskraft kann Monatszeiten prüfen, ohne Rohdaten aus mehreren
  Modulen manuell zusammenzuführen.
- Jede nachträgliche Änderung an Arbeitszeitdaten ist nachvollziehbar: wer,
  wann, was, warum.
- Projektzeit darf nicht mit Anwesenheitszeit verwechselt werden; beide Ebenen
  bleiben fachlich getrennt.
- Dienstmittel- und Materialnutzung darf nicht als lose Notiz verschwinden,
  sondern muss strukturiert am Auftrag oder Stundenzettel hängen.
- Wiederkehrende Auswertungsdimensionen wie Kunde, Produkt, Auftragstyp,
  Tätigkeit, Ursache, Dienstmittel, Qualifikation und Ergebnis sind als
  strukturierte Daten erfassbar, nicht nur als Freitext.
- Offene oder widersprüchliche Daten werden sichtbar, nicht still korrigiert.

## Später

- Terminal-Modus für Tablet/Kiosk.
- NFC/QR-Code-Check-in für Standorte oder Fahrzeuge.
- Optionale Standortprüfung für Außendienst, nur mit klarer Konfiguration und
  Datenschutz-Hinweisen.
- Automatische Erinnerungen bei vergessener Stempelung.
- Teamübersicht für aktuelle Anwesenheiten und offene Tagesabschlüsse.
- Direkte Verknüpfung zu Management-Auswertungen für Kunden, Produkte,
  Effizienz, Schulungsbedarf und wiederkehrende Probleme.
- Protokollvorlagen für Baustellen, Produkte, Anlagen, Wartungen, Übergaben und
  Abnahmen.

## Abhängigkeiten

- `Attendance`
- `TimeEntry`
- `DiaryEntry`
- `Project`
- `Task`
- `Timesheet`
- `MaterialUsage`
- `Vehicle`
- `TravelLog`
- `Expense`
- `Attachment`
- `Comment`
- `WorkSchedule`
- `FlexCalculator`
- `WorkBalanceCalculator`
- `AuditLog`
- `Dashboard`
- `Reports`

## GitHub Issues

- TBD

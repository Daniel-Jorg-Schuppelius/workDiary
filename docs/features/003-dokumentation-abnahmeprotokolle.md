# Dokumentation und Abnahmeprotokolle

## Status

Done — MVP umgesetzt; konzipiert in MVP-020 bis MVP-024:
[Protokoll-Datenmodell](../protokoll-datenmodell.md),
[Protokollpunkt-Typen](../protokollpunkt-typen.md),
[Abnahme & Signatur](../abnahme-signatur.md),
[Vorher-/Nachher-Fotos](../protokoll-fotos.md),
[Offene Punkte](../offene-punkte.md).

## Ziel

WorkDiary soll die Dokumentation einer Baustelle, eines Produkts, einer Anlage,
eines Einsatzes oder eines beliebigen Auftrags ermöglichen. Dazu gehören
strukturierte Protokolle, Fotos, Anhänge, Messwerte, Checklisten,
Abnahmebestätigungen, Unterschriften und alle Informationen, die für
Absicherung, Verwaltung, Nacharbeit, Abrechnung und spätere Auswertung nötig
sind.

## Warum

Für viele Firmen ist nicht nur wichtig, dass Arbeit geleistet wurde. Sie müssen
später beweisen können, was vor Ort vorgefunden wurde, was erledigt wurde,
welche Mängel bestanden, welche Teile oder Dienstmittel verwendet wurden, wer
die Arbeit abgenommen hat und welche offenen Punkte bleiben. Ohne saubere
Dokumentation entstehen Streitfälle, Nacharbeit, Abrechnungsverluste und
Wissenslücken.

## Anwendungsfälle

- Baustellendokumentation mit Vorher-/Nachher-Fotos.
- Service- oder Wartungsprotokoll für Produkte, Anlagen oder Geräte.
- Abnahmeprotokoll mit Kundenunterschrift.
- Mängelaufnahme, Restpunkteliste und Nacharbeitsnachweis.
- Übergabeprotokoll zwischen Mitarbeitenden, Teams oder Schichten.
- Prüf- und Wartungschecklisten.
- Verbindliche Arbeitsprozeduren, z. B. Update nur nach dokumentiertem
  Konfigurationsbackup, Funktionstest und Freigabe.
- Dokumentation verwendeter Materialien, Fahrzeuge, Werkzeuge und sonstiger
  Dienstmittel.
- Interne Absicherung bei Reklamationen, Haftungsfragen oder späterer
  Rechnungsprüfung.

## Vorhandene Basis

- `DiaryEntry`, `Project`, `Task` und `Timesheet` als fachliche Träger.
- Polymorphe Anhänge über `Attachment`.
- Kommentare und Statuswechsel.
- Materialverbrauch über `MaterialUsage`.
- Fahrzeuge und Fahrten über `Vehicle` und `TravelLog`.
- Kunden, Projekte, Rechnungen und PDF-Ausgabe.
- Öffentliche Signatur-Links für Stundenzettel.

## MVP

- Protokolltypen pro Organisation: Baustelle, Service, Wartung, Abnahme,
  Übergabe, Mangel, Prüfung.
- Konfigurierbare Checklisten mit Pflichtfeldern.
- Einbindung verbindlicher Prozeduren und Arbeitsanweisungen.
- Foto- und Dateianhänge pro Protokollpunkt.
- Kunden- oder Verantwortlichen-Unterschrift direkt am Protokoll.
- Vorher-/Nachher-Dokumentation.
- Offene Punkte mit Verantwortlichkeit, Frist und Status.
- PDF-Ausgabe für Kunde, Archiv oder Rechnung.
- Verknüpfung mit Auftrag, Projekt, Stundenzettel, Material, Fahrzeug,
  Mitarbeitenden und Zeitbuchungen.

## Akzeptanzkriterien

- Ein abgeschlossener Auftrag kann als vollständiges Protokoll exportiert
  werden: Arbeit, Zeiten, Beteiligte, Fotos, Material, Dienstmittel,
  Unterschriften und offene Punkte.
- Pflichtfelder verhindern, dass kritische Nachweisdaten fehlen.
- Verbindliche Prozedurschritte können nicht unbemerkt übersprungen werden.
- Fotos und Anhänge sind eindeutig einem Protokollpunkt zugeordnet.
- Unterschriften werden mit Zeitstempel, Unterzeichner und Kontext gespeichert.
- Nachträgliche Änderungen an abgenommenen Protokollen sind nachvollziehbar und
  überschreiben den ursprünglichen Stand nicht still.
- Protokolle können später für Auswertungen genutzt werden: Mangelarten,
  Produktprobleme, Nacharbeit, Kundenauffälligkeiten und Schulungsbedarf.

## Datenstruktur

Für belastbare Dokumentation sollten Protokolle nicht nur aus Freitext bestehen.
Wichtige strukturierte Felder:

- Protokolltyp.
- Objektbezug: Baustelle, Produkt, Anlage, Gerät, Auftrag oder Projekt.
- Zustand bei Beginn und Zustand nach Abschluss.
- Checklistenpunkte mit Ergebnis: ok, nicht ok, nicht anwendbar, offen.
- Prozedurschritte mit Pflichtstatus, Reihenfolge, Nachweisart und Bearbeiter.
- Mangel- oder Fehlerkategorie.
- Ursache, soweit bekannt.
- verwendete Materialien, Werkzeuge, Fahrzeuge oder sonstige Dienstmittel.
- beteiligte Personen und Rollen.
- Kundenkontakt oder abnehmende Person.
- Unterschrift, Zeitstempel und Abschlussstatus.

## Später

- Wiederverwendbare Protokollvorlagen je Kunde, Produkt oder Auftragstyp.
- Prozedurvorlagen mit Pflichtnachweisen, Reihenfolge und Vier-Augen-Schritten.
- Pflichtfoto-Regeln für bestimmte Checklistenpunkte.
- Versionssichere Protokollfreigabe.
- QR-Code am Produkt oder Objekt zum Öffnen der Historie.
- Seriennummern-/Inventarbezug für Geräte und Produkte.
- Automatische Folgeaufträge aus offenen Punkten oder Mängeln.
- Kundenportal für Abnahme, Rückfragen und Dokumentenabruf.

## Abhängigkeiten

- Aufzeichnung und Zeiterfassung als Kernprodukt
- Auswertungen und Entscheidungsgrundlagen
- `DiaryEntry`
- `Project`
- `Task`
- `Timesheet`
- `Attachment`
- `Comment`
- `MaterialUsage`
- `Vehicle`
- `TravelLog`
- `Customer`
- PDF-Ausgabe
- Audit
- Prozeduren, Arbeitsanweisungen und Checklisten

## GitHub Issues

- TBD

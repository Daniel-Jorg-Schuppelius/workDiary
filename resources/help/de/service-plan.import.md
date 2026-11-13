---
title: "Listen übernehmen und Kundenliste abgleichen"
topic: service-plan.import
version: 1
keywords:
    - Liste übernehmen
    - Excel
    - CSV
    - Jahresliste
    - Periodenliste
    - Kundenabgleich
    - DATEV
    - Mandantenliste
audience: []
related:
    - service-plan.overview
    - customers.deadlines
---

## Liste übernehmen

Vorhandene Excel- oder CSV-Listen übernehmen Sie über **Liste übernehmen** im
Leistungsplan. Erst prüft eine **Vorschau** jede Zeile, dann übernehmen Sie.

- **Jahresliste:** Kundennummer, Leistung, Stand, Bemerkung; optional
  Unterlagen da, Pflicht und Zuständig.
- **Periodenliste:** Kundennummer, Leistung und je Periode eine Spalte (Jan …
  Dez, Q1 … Q4 oder H1, H2) mit dem Stand.
- Gibt es keine Spalte „Leistung“, wählen Sie die Leistung für die ganze
  Liste.
- Spalten mit der Bezeichnung eines Merkmals der Kundenfristen (etwa
  USt-Turnus) übernehmen die Merkmale als neue Fassung.

Zugeordnet wird **nie geraten**: Kunden nur über die genaue Kundennummer,
Leistungen nur, wenn genau eine Vorlage oder Eintragsart passt, Zuständige nur
über Name oder E-Mail eines Mitglieds. Eine Zeile mit unbekannter Kundennummer
legt nichts an und steht in der Vorschau.

Übernommen werden fehlender Leistungsumfang (vorhandener bleibt), Merkmale,
Bemerkung und „Unterlagen da“ der laufenden Periode (nur, wo noch nichts
steht) und „entfällt“. Auf Wunsch entsteht aus dem Stand der laufenden
Periode ein Auftrag, wo es noch keinen gibt.

## Kundenliste abgleichen

**Kundenliste abgleichen** vergleicht Ihren Kundenstamm mit einer Liste aus
einem anderen Programm, etwa dem DATEV-Mandantenexport (Mandantennummer,
Name). Die Vorschau zeigt Vorschläge:

- **Neu:** Nummer unbekannt — Kunde anlegen.
- **Umbenannt:** Nummer bekannt, Name anders — Kunde umbenennen.
- **Fehlt in der Liste:** nur zur Information.

Nichts wird überschrieben oder angelegt, bevor Sie einen Vorschlag ankreuzen.

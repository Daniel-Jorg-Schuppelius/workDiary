---
title: "Berichte und Preise"
topic: finance.resale.reports
version: 1
keywords:
    - Margenbericht
    - Marge
    - Verlängerungen
    - Abos ohne Rechnung
    - Preisprüfung
    - UVP
    - Rechnungsvorschlag exportieren
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.periods
---

Die **Berichte** des Registers rechnen über die fälligen Perioden:
Margenbericht, Rechnungsvorschlag, Verlängerungen und Abos ohne Rechnung —
jeweils als CSV, XLSX oder PDF; die Seite „Preise“ prüft Verkaufspreise gegen
Einkauf, Katalog und UVP.

**Margenbericht:** Je Produkt und je Rechnungsempfänger stehen die
fälligen Perioden des Zeitraums mit Soll-Verkauf (Menge × Verkaufspreis),
Berechnet (Nettobeträge der Rechnungsbezüge, Vorschläge eingeschlossen),
Soll-Einkauf (Anbieterpreis × Menge) und Ist-Einkauf aus den
Einkaufsbelegen. Marge = Berechnet − Einkauf; der Ist-Einkauf zählt, sobald
jede Periode der Zeile einen hat, sonst der Soll-Einkauf. Beträge werden nie
über Währungen hinweg summiert — bei mehreren Währungen gibt es je Währung
eine Zeile und einen Hinweis. Export als CSV, XLSX oder PDF; der
Rechnungsvorschlag (offene Perioden mit offenen Lizenzmonaten und Betrag)
als CSV oder XLSX.

**Verlängerungen und Abos ohne Rechnung:** Der Bericht „Verlängerungen"
zeigt, welche Abos sich im Zeitraum verlängern oder enden (Kacheln 30, 60,
90 Tage): Verlängerung ist der Beginn der nächsten geplanten Periode, bei
gekündigten Abos zählt das Ende. „Ohne Rechnung" listet Abos, deren älteste
offene fällige Periode länger als N Tage zurückliegt (Vorgabe 60), mit
offenen Perioden und offenem Betrag. Beide als CSV oder XLSX.

**Preisprüfung:** Je Produkt Einkauf laut Vertrag, Katalogpreis und UVP aus
der zuletzt importierten Preisliste gegen die Verkaufspreise der Abos
(Minimum, Median, Maximum). Hinweise: „Verkauf unter Einkauf", „Verkauf
unter UVP", „Vertrag teurer als Katalog", „Kein Verkaufspreis".
Folgt der Einkaufspreis eines Abos dem letzten Anbieterpreis nicht mehr,
bietet die Seite „Anbieterpreis übernehmen“ an: Der Dialog zeigt alten und
neuen Preis je Abo; nach der Übernahme zieht der Import künftige
Preisänderungen wieder selbst nach, offene Perioden bekommen das neue Soll.

---
title: "Zahlungsverhalten"
topic: reports.payment-behavior
version: 3
keywords:
    - Zahlungsmoral
    - DSO
    - Forderungslaufzeit
    - Zahlungsverzug
    - Zahlungsdauer
    - säumige Kunden
    - Spätzahler
    - überfällige Rechnungen
    - offene Forderungen
    - Kapitalbindung
    - Zahlungsziel
    - Debitorenanalyse
    - Lexoffice-Rechnungen
audience: []
related:
    - reports.economics
    - reports.customer-value
---

Verhaltens- und Trendsicht auf **lokale Rechnungen und Rechnungen aus dem
Buchhaltungsprogramm** (etwa Lexoffice) — der Abrechnungsbericht zeigt die
Bestandsaufnahme (Status, Altersstruktur), dieser Bericht das Verhalten
dahinter. Stichtag ist immer das **Zeitraumende** (reproduzierbare
Berichte).

## DSO mit Zahlenbeispiel

**DSO** (Days Sales Outstanding) = offene Forderungen am Monatsende
÷ Umsatz der letzten 90 Tage × 90. Beispiel: 12 000 € offen bei 48 000 €
Umsatz in 90 Tagen → 12 000 ÷ 48 000 × 90 = **22,5 Tage** durchschnittliche
Kapitalbindung. Steigt die Kurve, bindet das Geschäft zunehmend Liquidität —
unabhängig davon, ob der Umsatz wächst.

## Zahldauer vs. Verzug

- **Zahldauer** = Tage von Rechnungsstellung bis Zahlung (unabhängig von
  der Fälligkeit) — im Monatstrend und als Verteilung (Boxplot) je Kunde.
- **Verzug** = Tage **nach Fälligkeit**; Frühzahler zählen als 0. Die
  Top-Liste zeigt Kunden mit dem höchsten Ø Verzug.

Boxplot lesen: Strich = Median, Box = mittlere Hälfte, Whisker =
Spannweite. Ein Kunde mit Median 40 Tagen bei Fälligkeit 14 Tagen zahlt
systematisch spät — das ist ein Preis-/Konditionsthema, kein Einzelfall.

## Was tun damit?

- **DSO steigt** → Mahnwesen prüfen, Fälligkeiten verkürzen, Skonto
  erwägen.
- **Einzelne Kunden mit hohem Ø Verzug** → Zahlungsziele neu verhandeln,
  Vorkasse/Abschläge für Neuaufträge, Kreditlimit intern setzen.
- **Überfällige offene Rechnungen** (Tabelle unten) → direkt aus der
  Liste in die Rechnung bzw. die offenen Rechnungen des Kunden springen.

Klick auf einen Kunden in Boxplot oder Verzugs-Top filtert diesen
Bericht auf ihn. Führt Lexoffice die Rechnungen, fließen sie über den
Beleg-Spiegel des Plugins ein — der Beleg-Sync lädt dabei auch die
Zahlungsdaten (Payments-Endpunkt) nach. Ohne jede Datenquelle weist der
Bericht das offen aus.

Belege, deren Kontakt im Buchhaltungsprogramm keinem Kunden zugeordnet ist,
zählen in den Kennzahlen **DSO (Tage)**, **Ø Zahldauer (Tage)**, **Pünktlich
bezahlt** und **Überfällige Rechnungen** mit. In **Überfällige offene
Rechnungen (Top 15)** stehen sie als „Kontaktname (ohne Kundenzuordnung)“
ohne Link. In den Aufschlüsselungen je Kunde fehlen sie: in den
Kunden-Boxen der **Zahldauer-Verteilung (Ausstellung bis Zahlung)** — die
Box **Alle Kunden** enthält sie — und in **Ø Verzugstage je Kunde (Top
10)**.

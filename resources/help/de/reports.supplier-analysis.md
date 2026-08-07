---
title: "Lieferantenanalyse"
topic: reports.supplier-analysis
version: 1
audience: []
related:
    - reports.customer-analysis
    - reports.customer-value
---

Die Lieferantenanalyse ist das Einkaufs-Pendant zur Kundenanalyse und
beantwortet: **Wofür geben wir Geld aus, von welchen Lieferanten hängen
wir ab, wo liegen offene Verbindlichkeiten?**

## Wie lese ich diesen Bericht?

- **Ausgaben je Lieferant (Pareto)**: absteigende Säulen + kumulierte
  Prozentlinie — wie schnell die Linie 80 % erreicht, zeigt die
  Abhängigkeit von wenigen Lieferanten (Klumpenrisiko im Einkauf).
- **Ausgaben je Monat**: org-weiter Ausgabenverlauf der letzten zwölf
  Monate, unabhängig vom gewählten Zeitraum.
- **Offener Betrag je Lieferant**: noch nicht vollständig bezahlte
  Einkaufsbelege — die aktuellen Verbindlichkeiten.

## Datenbasis

Die Ausgaben stammen aus dem **Belegspiegel der Buchhaltung**
(Einkaufsrechnungen, Einkaufsgutschriften und generische Belege je
Lieferant). Gutschriften mindern die Ausgaben. Entwürfe und stornierte
Belege zählen nicht. Der Bericht funktioniert damit **ohne das
Lager-Modul**.

Ist das **Lager-Modul** aktiv, kommen zusätzlich **Bestellungen** (im
Zeitraum ausgelöst) und **offene Bestellungen** (aktuell laufend) je
Lieferant hinzu.

## Kennzahlen

- **HHI (Konzentration)** — Herfindahl-Hirschman-Index über die Ausgaben:
  unter 1500 unkritisch, 1500–2500 mäßig, über 2500 hoch.
- **Top-5-Anteil** — Anteil der fünf ausgabenstärksten Lieferanten; ab
  etwa 60 % besteht Klumpenrisiko.
- **Trend %** — Ausgaben im Zeitraum gegenüber dem unmittelbar davor
  liegenden, gleich langen Vergleichszeitraum.

Jede Zeile öffnet per Klick die **Lieferanten-Detailseite**. Der Bericht
zeigt Finanzdaten und ist daher nur für Berechtigte mit Auswertungsrecht
sichtbar.

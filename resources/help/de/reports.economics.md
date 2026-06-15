---
title: "Wirtschaftlichkeit"
topic: reports.economics
version: 1
audience: []
related:
    - reports.customer-analysis
    - reports.drilldown
---

Die Wirtschaftlichkeitssicht (Nachkalkulation) zeigt je Kunde und je Projekt im
gewählten Zeitraum den Deckungsbeitrag:

- **Erlös** = abrechenbare Zeiten × Satz + abgerechnetes Material +
  abrechenbare Spesen. Die maßgebliche Rechnung führt das externe
  Fakturierungssystem; hier dienen die erfassten Beträge als Projektion.
- **Kosten** = interner Zeit-Kostensatz × Zeit + Material- und
  Beleg-Direktaufwand.
- **Deckungsbeitrag** = Erlös − Kosten, zusätzlich als **Marge** in Prozent.

Weitere Auswertungen:

- **Ranking** (Top/Flop 5) je Projekt und Kunde nach Deckungsbeitrag – so
  werden defizitäre Kunden, Projekte und Aufträge sichtbar.
- **Nicht abrechenbare Zeit** (`billable=false`) als Proxy für Nacharbeit und
  Kulanz, getrennt ausgewiesen mit Anteil.
- **Plan-vs-Ist** je Projekt: Ist-Minuten gegen das Projekt-Zeitbudget und
  Ist-Kosten gegen das Projekt-Budget (€).

Hinweise zur Datenqualität:

- Ist für einen Teil der Zeiten **kein interner Kostensatz** gepflegt, fließen
  diese mit 0 € Kosten ein – der Deckungsbeitrag ist insoweit zu optimistisch
  (mit `*` markiert).
- Projekte **ohne Zeitbudget/Budget** zeigen in der Plan-vs-Ist-Spalte „–".

Export als CSV oder PDF für Geschäftsführung und Controlling. Org-weite
Finanzdaten – nur für Berechtigte mit Report-Leserecht.

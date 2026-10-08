---
title: "Wirtschaftlichkeit"
topic: reports.economics
version: 3
keywords:
    - Nachkalkulation
    - Deckungsbeitrag
    - Marge
    - Rentabilität
    - Profitabilität
    - Projektrentabilität
    - Soll-Ist-Vergleich
    - Budgetvergleich
    - interner Kostensatz
    - Verlustprojekte
    - Controlling
    - Top und Flop
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

Die Seite **Wirtschaftlichkeit** (Nachkalkulation) unter **Auswertungen** →
**Finanzen & Audit** → **Wirtschaftlichkeit** zeigt je Kunde
(**Wirtschaftlichkeit je Kunde**) und je Projekt (**Wirtschaftlichkeit &
Plan-vs-Ist je Projekt**) im gewählten **Zeitraum** den Deckungsbeitrag:

- **Erlös** = abrechenbare Zeiten × Satz + abgerechnetes Material +
  abrechenbare Spesen. Die maßgebliche Rechnung führt das externe
  Fakturierungssystem; hier dienen die erfassten Beträge als Projektion.
- **Kosten** = interner Zeit-Kostensatz × Zeit + Material- und
  Beleg-Direktaufwand.
- **Deckungsbeitrag** = Erlös − Kosten, zusätzlich als **Marge** in Prozent.

Weitere Auswertungen:

- **Ranking**: „Top 5 Kunden (Deckungsbeitrag)“, „Flop 5 Kunden
  (Deckungsbeitrag)“ sowie dasselbe für Projekte – so werden defizitäre
  Kunden und Projekte sichtbar.
- **Nicht abrechenbare Zeit**: Je Kunde zeigen **Abrechenbar (Min.)**,
  **Nicht abrechenbar (Min.)** und **Anteil %**, wie viel Zeit ohne
  Abrechnung erfasst wurde – ein Hinweis auf Nacharbeit und Kulanz. Je
  Projekt weisen **Nacharbeit (Min.)**, **Kulanz (Min.)** und
  **Nacharbeit %** die Zeiten aus, die mit einem Nacharbeits- bzw.
  Kulanzgrund erfasst wurden.
- **Plan-vs-Ist** je Projekt: **Ist (Min.)** gegen **Plan (Min.)** aus dem
  Projekt-Zeitbudget (**Δ Min.**) und die Ist-Kosten gegen das
  **Plan-Budget** in Euro (**Δ Budget**).

Hinweise zur Datenqualität:

- Ist für einen Teil der Zeiten **kein interner Kostensatz** gepflegt, fließen
  diese mit 0 € Kosten ein – der Deckungsbeitrag ist insoweit zu optimistisch.
  Die Kosten tragen dann ein Sternchen mit dem Hinweis „Kostensätze nicht
  vollständig gepflegt“.
- Projekte **ohne Zeitbudget/Budget** zeigen in den Plan-Spalten „–“.

Export als **PDF**, **CSV** oder **Excel** für Geschäftsführung und
Controlling. Die Seite zeigt organisationsweite Finanzdaten und steht nur
Personen mit dem Recht **Auswertungen einsehen** offen.

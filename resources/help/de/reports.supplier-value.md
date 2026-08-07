---
title: "Lieferantenwert"
topic: reports.supplier-value
version: 1
audience: []
related:
    - reports.supplier-analysis
    - reports.customer-value
---

Der Lieferantenwert-Bericht ist das Einkaufs-Pendant zum Kundenwert und
beantwortet: **Von welchen Lieferanten hängen wir ab, wo liegt
Klumpenrisiko, welche sind strategisch, ruhend oder sporadisch?**

## Wie lese ich diesen Bericht?

- **Ausgaben je Lieferant (Pareto)**: absteigende Säulen + kumulierte
  Prozentlinie — wie schnell die Linie 80 % erreicht, zeigt die
  Abhängigkeit von wenigen Lieferanten.
- **Ausgaben nach Inaktivität**: je weiter rechts, desto länger kein
  Beleg; Punkte rechts **oberhalb** der P80-Linie sind ausgabenstarke
  Lieferanten, die lange nichts geliefert haben.
- **Lieferanten je Segment**: Klick auf einen Balken filtert die
  Lieferantenliste unten auf genau diese Lieferanten.
- **Risikoliste**: Lieferanten, deren Ausgabenanteil die eingestellte
  Schwelle überschreitet (Single-Source-Klumpenrisiko), mit
  12-Monats-Ausgabenverlauf (Sparkline).

## R, F und M — so entstehen die Scores

Jeder im Zeitraum aktive Lieferant erhält drei **Quintil-Scores von 1 bis
5**:

- **R (Recency)** — Tage seit dem letzten Beleg. Je kürzer, desto höher.
- **F (Frequency)** — Anzahl der Belegtage im Zeitraum.
- **M (Monetary)** — Ausgaben im Zeitraum (Einkaufsbelege aus dem
  Beleg-Spiegel, Gutschriften mindern).

Quintil heißt: Die Lieferanten werden je Kennzahl in fünf gleich große
Gruppen geteilt. Scores sind also **relativ zum eigenen
Lieferantenbestand**, nicht absolut.

## Segmente

- **Strategisch** — R ≥ 4, F ≥ 4, M ≥ 4 (hohe Ausgaben, regelmäßig,
  aktuell).
- **Ruhender Schlüssellieferant** — R ≤ 2 bei M ≥ 4 (ausgabenstark, aber
  lange keine Belege).
- **Stammlieferant** — F ≥ 3 (regelmäßige Beschaffung).
- **Sporadisch** — alle übrigen aktiven Lieferanten.
- **Neu** — erster Beleg liegt im Zeitraum.
- **Ruhend** — keine Belege im Zeitraum.

Der Bericht zeigt Finanzdaten und ist nur für Berechtigte mit
Auswertungsrecht sichtbar.

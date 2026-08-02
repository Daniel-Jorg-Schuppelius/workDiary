---
title: "Kundenwert"
topic: reports.customer-value
version: 2
audience: []
related:
    - reports.customer-analysis
    - reports.customer-retention
---

Der Kundenwert-Bericht beantwortet: **Von welchen Kunden lebt das
Unternehmen, wo liegt Klumpenrisiko, welche A-Kunden sind gefährdet?**

## Wie lese ich diesen Bericht?

- **Erlös je Kunde (Pareto)**: absteigende Säulen + kumulierte Prozentlinie —
  wie schnell die Linie 80 % erreicht, zeigt die Abhängigkeit von wenigen
  Kunden.
- **Erlös nach Inaktivität**: je weiter rechts, desto länger keine Leistung;
  Punkte rechts **oberhalb** der P80-Linie sind gefährdete A-Kunden.
- **Kunden je Segment**: Klick auf einen Balken filtert die Kundenliste
  unten auf genau diese Kunden.
- **Risikoliste**: umsatzstarke Kunden ohne Leistung seit der eingestellten
  Schwelle, mit 12-Monats-Erlösverlauf (Sparkline).

## R, F und M — so entstehen die Scores

Jeder im Zeitraum aktive Kunde erhält drei **Quintil-Scores von 1 bis 5**:

- **R (Recency)** — Tage seit der letzten Leistung. Je kürzer, desto
  höher der Score.
- **F (Frequency)** — Anzahl der Aktivitätstage im Zeitraum.
- **M (Monetary)** — Erlös im Zeitraum (abrechenbare Zeit-Snapshots,
  dieselbe Quelle wie die Wirtschaftlichkeit).

Quintil heißt: Die Kunden werden je Kennzahl in fünf gleich große Gruppen
geteilt. Beispiel mit fünf Kunden nach Erlös 10 000/8 000/5 000/1 000/300 €
→ M-Scores 5/4/3/2/1. Scores sind also **relativ zum eigenen
Kundenbestand**, nicht absolut.

## Die Segmente (erste zutreffende Regel gewinnt)

| Segment | Regel |
| --- | --- |
| Inaktiv | keine Leistung im Zeitraum |
| Neu | Erstleistung liegt im Zeitraum |
| Champions | R ≥ 4 und F ≥ 4 und M ≥ 4 |
| Gefährdet | R ≤ 2 bei M ≥ 4 (umsatzstark, aber lange nichts) |
| Inaktiv | R ≤ 2 (früh im Zeitraum aktiv, dann still) |
| Stammkunden | F ≥ 3 |
| Ausbaufähig | alle übrigen aktiven Kunden |

## HHI — Konzentration mit Zahlenbeispiel

HHI = Summe der **quadrierten** Umsatzanteile in Prozent. Zwei Kunden mit
je 50 % → 50² + 50² = **5000** (extrem konzentriert); zehn Kunden mit je
10 % → 10 × 10² = **1000** (unkritisch). Richtwerte: unter 1500 unkritisch,
1500–2500 mäßig, über 2500 hohes Klumpenrisiko.

## Was tun mit den Segmenten?

- **Champions**: halten — bevorzugter Service, keine Experimente.
- **Gefährdet**: aktiv Kontakt aufnehmen, Grund der Stille klären.
- **Ausbaufähig**: gezielte Angebote — hier liegt Wachstumspotenzial.
- **Neu**: Onboarding sauber abschließen, zweite Beauftragung sichern.
- **Inaktiv**: bewusst entscheiden — reaktivieren oder sauber abschließen.
- **HHI/Top-5 hoch**: Neukundenakquise priorisieren, Abhängigkeit senken.

Jeder Diagrammpunkt und jede Tabellenzeile führt per Klick zur
Datenbasis (Kunden-&-Projekte-Bericht bzw. gefilterte Kundenliste).

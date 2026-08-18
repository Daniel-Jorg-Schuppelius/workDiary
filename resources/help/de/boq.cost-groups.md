---
title: "Kostengruppen nach DIN 276"
topic: boq.cost-groups
version: 1
audience: []
related:
    - boq.overview
---

Positionen tragen **Katalogzuordnungen**: Die Kostengruppe sagt, *wofür* das
Geld ausgegeben wird, der Leistungsbereich, *wer* es ausführt. Beides kommt in
der Regel schon mit der Datei der Vergabestelle — im Bundesbau ist StLB-Bau als
Grundlage der Leistungsbeschreibung vorgeschrieben, und StLB-Bau gibt zu jeder
Textvariante die Kostengruppe mit.

**Der Katalogstamm liegt bei.** Ausgeliefert werden die DIN-276-Kostengruppen in
den Ausgaben **2018-12** (dreistufig) und **2008-12** sowie die
StLB-Leistungsbereiche — jeweils nur Nummern und Kurzbezeichnungen, kein
Normtext.

**Beide DIN-Ausgaben stehen nebeneinander, sie lösen einander nicht ab.** „310"
heißt in der Ausgabe 2008 „Baugrube", 2018 „Baugrube, Erdbau"; die Ausgabe 2018
hat außerdem die 200er, 500er und 600/700 neu gegliedert. Ein laufendes Vorhaben
rechnet weiter nach seiner Ausgabe ab.

## Zuordnen

**Bau → Leistungsverzeichnis → Zuordnen.** Der Filter **„Nur ohne
Kostengruppe"** ist der eigentliche Arbeitsmodus — was zugeordnet ist, muss
niemand ansehen. Jede Zeile zeigt die **Herkunft**:

- *aus der Datei* — kam mit dem Import und darf beim Reimport überschrieben
  werden,
- *von Hand* — bleibt beim Reimport unangetastet,
- *Vorschlag* — von einer Regel gesetzt.

Eine Nummer, die im Katalog nicht steht, wird abgewiesen. Die Auswertung
summiert nach Nummern; eine falsche fiele sonst niemandem auf.

Die **Massenzuordnung** über die Auswahl überschreibt auch von Hand Gesetztes —
wer sie auslöst, meint genau das.

**Aufgeteilte Positionen** stehen mit ihren Teilmengen als eigene Zeilen
darunter, jede mit eigenem Feld. In der Auswertung schlägt die Zuordnung der
Teilmenge die der Position.

## Vorschlagsregeln

**Bau → Zuordnungsregeln** hält fest, welche Leistung üblicherweise auf welche
Kostengruppe schlägt. Zwei Anknüpfungspunkte:

- **Leistungsbereich** — steht in der Datei und wird auf Präfix verglichen
  („013" trifft auch „013.2"). Die verlässlichere Grundlage.
- **Stichwort** im Kurz- oder Langtext — schwächer, aber die einzige Handhabe,
  wenn die Vergabestelle keine Leistungsbereiche mitschickt.

Der Regellauf **füllt nur Lücken**: Vorhandene Zuordnungen bleiben, gleich
welcher Herkunft. Greifen mehrere Regeln, gewinnt die mit dem kleinsten Rang.

## Auswerten

**Bau → Leistungsverzeichnis → Kostengruppen** zeigt die Summen je Gruppe,
umschaltbar zwischen erster, zweiter und dritter Gliederungsebene, mit Diagramm
und CSV-/Excel-Ausgabe.

Drei Dinge sind wichtig zu wissen:

1. **Teilmengen schlagen die Position.** Ist eine Position aufgeteilt (300 m³
   auf KG 310, 150 m³ auf KG 320), zählt die Aufteilung.
2. **Der Abschnitt vererbt** an Positionen ohne eigene Zuordnung.
3. **„Ohne Zuordnung" steht immer in der Tabelle**, auch bei 0,00 €. Dort
   landet auch der Rest unvollständiger Teilmengen. Eine Auswertung, die den
   Rest verschweigt, ist nicht prüfbar.

Darunter steht die **Kostenverfolgung**: LV-Ansatz, Nachträge, aufgemessene
Leistung und Rest. Nachträge zählen getrennt vom LV-Ansatz — das eine war
ausgeschrieben, das andere kam hinzu. Ein Aufmaß über der LV-Menge ergibt einen
**negativen Rest**; er wird gezeigt, nicht geglättet. Das **Budget** kommt aus
der Kostenermittlung am Projekt (s. u.); der **abgerechnete Stand** fehlt
bewusst — er liegt im führenden Faktura-System.

## Kostenermittlung und Budget

Die HOAI kennt vier Stufen — **Kostenschätzung, Kostenberechnung,
Kostenanschlag, Kostenfeststellung**. Sie lösen einander nicht ab: Ihr
Vergleich *ist* die Kostenkontrolle.

Eine fremde Kostenermittlung kommt als **X51** herein und gehört zum
**Projekt**, nicht zum einzelnen Leistungsverzeichnis — ermittelt wird das
Bauvorhaben als Ganzes. In der Kostenverfolgung erscheint sie als Budgetspalte;
ohne Projekt bleibt diese leer, denn ein fehlendes Budget ist kein Budget von
null.

Ausgegeben werden nur **Kostenanschlag** (LV-Ansatz samt Nachträgen) und
**Kostenfeststellung** (die aufgemessene Leistung). Schätzung und Berechnung
stammen aus der Planung; die dafür nötigen Kennwerte liegen hier nicht vor.

## Ausgabe wechseln

Der Wechsel der Norm läuft über **Zuordnen → Ausgabe wechseln** und zeigt zuerst
eine **Vorschau**. Umgestellt wird nur, was eine eindeutige Entsprechung hat;
alles andere bleibt stehen. Die Lücken sind das Eigentliche — sie zeigen, wo
jemand entscheiden muss. Eine geratene Nummer wäre schlimmer als die alte.

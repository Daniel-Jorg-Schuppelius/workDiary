---
title: "Kostengruppen nach DIN 276"
topic: boq.cost-groups
version: 1
audience: []
modules:
    - module.bau
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

Die drei Ebenen lassen sich auch **ineinander** zeigen: 300 über 310 über 311,
jede Ebene auf- und zuklappbar. Die Summe einer Oberebene entsteht dabei **aus
ihren Kindern**; sie kann gar nicht von der Summe darunter abweichen. Der Export
dieser Sicht trägt die Ebene als eigene Spalte — eingerückte Nummern wären in
einer Tabellenkalkulation nicht filterbar.

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

Unter **Projekt → Kostenermittlung** stehen alle vier Stufen nebeneinander, je
Kostengruppe eine Zeile, dazu die Abweichung zwischen der ersten und der letzten
vorhandenen Stufe — auch als PDF. **Eine fehlende Stufe bleibt leer**, und mit
nur einer Stufe gibt es keine Abweichung.

## Kalkulationsdaten (X52)

Eine **X52-Datei** trägt die Kalkulation hinter den Preisen: **Kostenarten** im
Kopf (Lohn, Material, Gerät, Fremdleistung) und **Kostenansätze** an jeder
Position. Unter **Leistungsverzeichnis → Kalkulationsdaten** stehen daraus

- die **EKT** — Einzelkosten der Teilleistung, was die Ansätze unmittelbar
  kosten,
- die **GKT** — der Zuschlag darauf.

**Der Zuschlagssatz hängt an der Kostenart, nicht am Ansatz.** Ein Betrieb
schlägt auf Lohn anders zu als auf Material, aber nicht je Position. Fehlt der
Satz, gibt es keinen Zuschlag — eine unterstellte Null behauptete, es werde
nichts zugeschlagen.

**Die Differenz zum angebotenen Preis ist der eigentliche Befund.** Weicht die
Kalkulation vom Positionsbetrag ab, wurde sie entweder unvollständig übertragen
oder bewusst korrigiert. Positionen ohne Preis gehen nicht in die
Gesamtdifferenz ein, sondern werden gezählt — sonst sähe eine fehlende
Preisangabe wie ein Kalkulationsfehler aus.

Eine **Zuschlagsposition darf keine eigenen Kostenansätze tragen**: Sie rechnet
prozentual auf andere Positionen, eigene Ansätze zählten dasselbe Geld ein
zweites Mal. Der Import beanstandet das, bereinigt es aber nicht — die Datei
kommt aus einem fremden System, und was dort steht, ist dessen Aussage.

## Baukostenkataloge

Ein Baukostenkatalog (**GAEB X50**) ist ein Nachschlagewerk: Er sagt, was ein
Bauteil *üblicherweise* kostet — „Außenwand, zweischalig — 320 €/m²". Damit
speist er die frühen Kostenstufen, für die aus dem eigenen Bestand keine Zahlen
vorliegen.

**Der Kennwert ist eine Spanne**, keine Zahl: von, Mittel und bis stehen
nebeneinander. Der Mittelwert ist der Rechenwert, die Spanne daneben sagt, wie
sicher er ist.

Jedes Kostenelement lässt sich mit einem **eigenen Artikel** verknüpfen; auf der
Artikelseite erscheinen die Kennwerte dann als Vergleich. **Übernommen wird
nichts:** Der Katalog sagt, was üblich ist, der Artikelstamm, was bei uns gilt.

## Ausgabe wechseln

Der Wechsel der Norm läuft über **Zuordnen → Ausgabe wechseln** und zeigt zuerst
eine **Vorschau**. Umgestellt wird nur, was eine eindeutige Entsprechung hat;
alles andere bleibt stehen. Die Lücken sind das Eigentliche — sie zeigen, wo
jemand entscheiden muss. Eine geratene Nummer wäre schlimmer als die alte.

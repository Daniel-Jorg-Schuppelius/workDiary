---
title: "Serienvorlagen (Plan)"
topic: finance.resale.plan
version: 1
keywords:
    - Serienvorlagen
    - wiederkehrende Rechnung
    - Vorlagen-Vorschlag
    - Plan-Abgleich
    - Ausführungsstand
    - Abo ohne Vorlage
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.accounts
    - finance.resale.periods
---

**Serienvorlagen** sind die wiederkehrenden Rechnungen des
Buchhaltungsprogramms; das Register spiegelt sie, ordnet ihre Positionen den
Abos zu und prüft Bestand gegen Plan. Die Seite „Vorlagen-Vorschlag“ nennt,
welche Vorlagen noch anzulegen sind.

**Serienvorlagen (Plan):** Führt ein Buchhaltungsprogramm die Rechnungen,
entstehen wiederkehrende Rechnungen dort als Serienvorlagen. Das Register
spiegelt sie (Lexoffice: täglich und nach dem Belegsync, nur lesend) und zeigt
im Lizenzkonto je Vorlage Status, Rhythmus, nächste Ausführung und die
Lizenzpositionen mit den Abos, die sie berechnen. Die Zuordnung Abo ↔
Vorlagenposition schlägt der Lauf vor — zuerst über die Rechnung, die die
Vorlage zuletzt erzeugt hat und die einer Periode zugeordnet ist, sonst über
gleichen Empfänger, Artikel und Menge (bei mehreren Endkunden eines Partners
nur mit Nennung) — Sie bestätigen, ordnen von Hand zu
oder lösen. Perioden, die eine laufende Vorlage berechnet, lässt
der Rechnungsvorschlag aus; was vor ihrer ersten Ausführung endet, bleibt drin.
So lassen sich alte Jahre auch dann nachberechnen, wenn die Vorlage schon
angelegt ist. **Prüfregeln Bestand ↔ Plan** melden vor der Rechnung:
Abo ohne Vorlage, Vorlage ohne Abo („Abo aus Vorlage anlegen“ übernimmt
Artikel, Menge, Rhythmus und Preis), abweichende Menge, Vorlage läuft nach dem
Abo-Ende weiter, Vorlage pausiert oder beendet bei laufendem Abo, nächste
Ausführung mehr als sieben Tage neben dem Periodenbeginn, letzte Ausführung
fehlgeschlagen — in der Kachel, im Wochen-Digest und im Arbeitsvorrat. Jede
gespiegelte Rechnung trägt den Verweis auf ihre Vorlage.

**Vorlagen-Vorschlag und Ausführungsstand:** Die Seite „Vorlagen-Vorschlag“
(aus der Periodenliste) listet je Empfänger mit externer Rechnungshoheit die
Abos ohne zugeordnete Vorlage mit Artikel, Positionstext, Menge, Einheit,
Einzelpreis, Intervall und erster Ausführung; Empfänger, die der Serienlauf
des Registers deckt, sind markiert. Die Vorlage legen Sie im
Buchhaltungsprogramm an, der nächste Spiegel schlägt die Zuordnung vor. Je
Vorlage zeigt das Lizenzkonto, was aus der letzten Ausführung wurde —
Rechnung bezahlt, offen (Versand im Programm prüfen) oder fehlt — und bei
fehlgeschlagener Ausführung die Meldung des Programms als Tooltip; ob eine
Rechnung per E-Mail ging, kennt die Lexoffice-API nicht. Eine Vorlagenposition
nimmt höchstens so viele Lizenzen auf, wie sie berechnet; Domain-Abos werden
nur bei exakter Nennung der Domain zugeordnet.

**Vorlage als Anker der Zuordnung:** Ist eine Vorlagenposition einem Abo
zugeordnet (bestätigt, von Hand oder aus einem Rechnungsbezug), gehen die
Rechnungen dieser Position an das Lizenzjahr dieses Abos oder eines seiner
Vorgänger, in das das Rechnungsdatum fällt — auch wenn ein anderes Abo
desselben Produkts näher am Datum beginnt. Solche Bezüge gelten als sicher
und tragen die Vorlage als Bemerkung. Einem Abo außerhalb dieser Kette wird
die Position nicht vorgeschlagen; findet sie keinen Platz, erscheint sie
unter „Positionen ohne Abo“. Stimmt die Zuordnung nicht, ändern Sie die
Zuordnung der Vorlagenposition, nicht jedes Jahr den Bezug. Der Anker gilt
nicht, wenn der Artikel der Rechnung von dem der Vorlagenposition abweicht
oder die Rechnung aus der Zeit vor dem Abo stammt. Je Zuordnung zeigt der
Vorlagenblock den **Abrechnungslauf**: das Lizenzjahr des letzten Laufs und ob
es am Beleg dieses Laufs hängt. „Beleg nicht zugeordnet“ führt direkt zur
Zuordnung.

**Vorlage endet, Abo läuft weiter:** Trägt eine Vorlage ein Enddatum in den
nächsten 90 Tagen und läuft das zugeordnete Abo darüber hinaus, meldet das
Register das vorab. Legen Sie die Anschlussvorlage an, sonst bleibt das
nächste Lizenzjahr ohne Rechnung. **Rumpflaufzeit:** Eine Monatsmenge, die
kein Vielfaches des Rhythmus ist („7 Monat“ bei jährlicher Vorlage), zählt als
eine Lizenz über diese Monate.

**Vor dem nächsten Lauf:** Befunde an Vorlagen zeigen, wann die Vorlage das
nächste Mal ausführt und welchen Betrag die Position dann berechnet. Führt
eine Vorlage mit Befund in den nächsten 30 Tagen aus, steht sie im Überblick
unter „Vorlagen vor dem nächsten Lauf prüfen“. **Beendetes Produkt:** Berechnet
eine Vorlage einen Artikel, dessen Abo beim Empfänger beendet ist, nennt der
Befund das Ende und bei einem Produktwechsel das Abo, das seitdem läuft.
**Preis weicht ab:** Der Preis der Position je Lizenz wird mit dem
Verkaufspreis des Abos verglichen (Toleranz 5 %, mindestens 1 €).

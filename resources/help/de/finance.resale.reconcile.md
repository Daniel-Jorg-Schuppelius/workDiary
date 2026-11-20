---
title: "Abgleich je Rechnungsempfänger"
topic: finance.resale.reconcile
version: 1
keywords:
    - Abgleich
    - Rechnungsempfänger
    - Arbeitsliste
    - offener Betrag
    - nie abgerechnet
    - Rechnung an anderen Kunden
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.board
    - finance.resale.periods
---

Der **Abgleich** stellt je Rechnungsempfänger die fälligen Perioden den
Lizenzpositionen seiner Rechnungen gegenüber. Die Liste nennt je Empfänger
den offenen Betrag, die Vorlagenbefunde und ob er fertig ist; der
Standardfilter blendet fertige Empfänger aus, und „Nächsten Empfänger
abarbeiten“ öffnet die erste offene Arbeitsmappe.

**Abgleich je Rechnungsempfänger:** Wenn Perioden offen bleiben und unklar
ist, ob eine Rechnung fehlt oder nur die Zuordnung, hilft der Abgleich
(Schaltfläche in der Abo-Liste, auf der Periodenseite und am Kunden). Er
stellt je Empfänger — Kunde samt seiner Endkunden — die fälligen Perioden
aller Abos den Lizenzpositionen seiner Rechnungen gegenüber, in
Lizenzmonaten je Produkt: *Soll* aus den Perioden, *Abgerechnet* aus den
Positionen. Der Befund sagt, was zu tun ist: „nur nicht zugeordnet" (freie
Positionen reichen — zuordnen), „nie abgerechnet" (mehr Perioden als
Positionen — Rechnung nachholen über den Entwurf oder Periode verzichten)
oder „ohne Periode" (mehr Positionen als Perioden — Abo fehlt im Register
oder Doppelabrechnung). Je offener Periode stehen die Positionen desselben
Produkts mit Abstand zum Periodenbeginn: freie mit Zuordnung, bereits
vergebene mit ihrem Halter zur Kontrolle. Bezugsdatum ist der
**Leistungszeitraum** der Rechnung, sonst das Rechnungsdatum; Lizenzen und
Monate stehen getrennt („5 × 12 Mon." = fünf Lizenzen für ein Jahr). Dazu
drei Fallen, die je Abo unsichtbar wären: **Rechnung an einen anderen
Kunden** (das Anbieter-Konto ist nicht der Kunde, oder der Endkunde wird
direkt statt über den Partner berechnet; erkannt am gemeinsamen
Namensbestandteil) — die Lösung ist „Halter → Kunde": das Abo wechselt zu
diesem Kunden und der Vorschlagslauf greift sofort. **Stornierte**
Rechnungen nahe am Periodenbeginn zeigen, warum eine Periode leer ist. Abos
aus dem **Posteingang**, deren Firma im Rechnungstext an diesen Empfänger
steht, warten auf ihren Halter. Trifft eine freie Position keine Periode
ihres Produkts mehr, fehlt der Vertrag im Register (der Anbieter-Export kennt
ihn nicht): die Produktzeile sagt „Position ohne Abo ab …", und „Abo aus
Position anlegen" öffnet den Abo-Dialog mit Artikel, Menge, Beginn und Preis
aus der Rechnung. Die Zuordnung darf eine Periode eines anderen Abos
desselben Empfängers treffen — nie eine Periode eines fremden Kunden.

**Abrechnungsbeginn des Portalkontos:** Rechnet ein Subuser seine Domains
über das Anbieterportal ab, berechnet das Portal erst, seit es dieses
Unterkonto gibt. Offene Lizenzjahre, die vorher begannen, melden Lizenzkonto,
Arbeitsmappe, Überblick und das Abo-Detail der Domain mit dem Kontobeginn.
„Abrechnungsbeginn übernehmen“ entscheidet sie in einem Schritt: **Nicht mehr
berechnen (Verzicht)** schließt sie ab, mit dem Kontobeginn und Ihrer
Begründung als Grund. **Noch selbst berechnen** lässt sie offen und nimmt sie
in den Rechnungsvorschlag des Registers auf, obwohl die Domain sonst über das
Portal abgerechnet wird. Lizenzjahre, die nach dem Kontobeginn begannen, und
teilweise berechnete bleiben unberührt; jede Periode lässt sich danach einzeln
wieder öffnen oder verzichten.

**Prüffälle an Bezügen:** Über der Tafel stehen Bezüge, die nicht stimmen
können: eine Position deckt mehr Lizenzmonate, als sie hat (überbucht), oder
ihr Leistungszeitraum trifft die Periode nicht (zeitlich unpassend). Ein
unpassender Bezug lässt sich auf die vorgeschlagene Periode verschieben oder
lösen, ein überbuchter nur lösen.

**Produkt neu ordnen:** Sind die Bezüge eines Produkts durcheinander, etwa um
ein Jahr verschoben, löst die Schaltfläche im Kopf des Produkts alle
Rechnungsbezüge dieses Produkts bei diesem Empfänger, und der Vorschlagslauf
verteilt die Rechnungen neu. Bestätigte und von Hand gesetzte Bezüge gehen
dabei verloren. Verzicht, Einspruch und Gutschriften bleiben.

**Rechnung an einen anderen Kunden:** Hat ein anderer Kunde eine freie
Position desselben Produkts mit genau dem Leistungszeitraum der offenen
Periode, steht sie bei der Periode, auch bei einer einzelnen Lizenz. Von dort
lässt sich der Halter wechseln oder die Periode abtreten.

**Überzählige Position:** Sind alle Perioden eines Produkts gedeckt und eine
Rechnung bleibt frei, steht sie in der Tafel als „doppelt berechnet“ oder „nach Abo-Ende berechnet“. Entweder wurde
doppelt berechnet (Gutschrift), oder das Abo fehlt im Register (Abo anlegen),
oder die Position gehört nicht hierher (ignorieren). Legen Sie das Abo aus der
Position an und endete kurz davor ein Abo desselben Produkts, bietet der Dialog
es als Vorgänger an.

**Zu viel, abweichend, fehlt — in Euro:** Die Abgleichsliste und der Überblick
zeigen je Rechnungsempfänger, was zu viel berechnet wurde (Position in einem
schon gedeckten Lizenzjahr: „doppelt berechnet“; Position nach dem letzten
Lizenzjahr des Produkts: „nach Abo-Ende berechnet“) und was abweichend
berechnet wurde. Was fehlt, steht als offener Betrag. Positionen „außerhalb
der Laufzeit“ oder „ohne Abo“ zählen nicht mit: dort fehlt meist ein Abo im
Register.

**Abweichender Bezug:** Ordnen Sie eine Position zu, deren Artikel nicht der
des Abos ist oder deren Betrag um mehr als 5 % (mindestens 1 €) vom Soll
abweicht, verlangt der Dialog eine Begründung. Der Bezug bleibt möglich, die
Begründung steht an der Periode. Bezüge aus dem Bestand ohne Begründung
erscheinen unter den Prüffällen als „Anderer Artikel“ oder „Betrag weicht ab“
und lassen sich dort begründen oder lösen.

**Lizenzbild und Korrekturblatt:** Die Arbeitsmappe zeigt oben Produkt ×
Lizenzjahr über alle Anbieter des Kunden. Das Korrekturblatt darunter nennt
die Rechnungen zum Gutschreiben, die Lizenzjahre zum Berechnen, die
Abweichungen und den Saldo; es lässt sich als XLSX laden. Eine Gutschrift mit
Bezug auf die Rechnung nimmt deren Positionen aus der Liste.

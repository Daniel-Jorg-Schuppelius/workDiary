---
title: "Abos & Lizenzen"
topic: finance.resale
version: 1
audience: []
modules:
    - module.reselling
related:
    - roles.buchhaltung
    - glossary.core
---

Das **Reselling-Register** führt jede weiterverkaufte wiederkehrende Leistung
als Abo: Microsoft-365-Lizenzen, Domains, Hosting, Postfächer, Backups oder
Sonstiges — anbieterneutral in einer Liste.

**Halter:** Jedes Abo hat genau einen Halter. Ein **Kunde** bekommt die
Rechnung selbst. Ein **Fremdkunde** ist der Endkunde eines Partners; die
Rechnung geht an den Partner, der sie weiterreicht. **Eigener Bestand**
(interne Lizenzen, eigene Domains) wird nie berechnet. Abos ohne Halter
warten auf die Zuordnung und zählen in der Kennzahl „Ohne Halter".

**Laufzeit und Perioden:** Aus Beginn, Abrechnungsintervall (jährlich oder
monatlich) und Ende plant das Register die erwarteten Abrechnungsperioden —
bis 90 Tage in die Zukunft, damit die nächste Verlängerung sichtbar ist. Ein
Abo ohne Ende verlängert sich automatisch. Ein Rest am Laufzeitende, der
kürzer als ein Monat (jährlich) bzw. fünf Tage (monatlich) ist, ist ein
Ausrichtungsstummel und keine Periode. Perioden mit einer Entscheidung
(berechnet, teilweise, verzichtet, strittig) bleiben bei jeder Neuplanung
erhalten; offene Perioden folgen Änderungen an Menge, Preis und Ende.

**Preise:** Einkauf und Verkauf je Stück und Intervall, netto. Der Artikel
liefert Produkt und Verkaufspreis für Rechnungen. Soll-Verkauf je Periode =
Menge × Verkaufspreis.

**Status:** Aktiv, Gekündigt (Ende bekannt, bis dahin werden Perioden
geplant), Abgelöst (Nachfolger bei einem anderen Anbieter) und Beendet.
Beendete und abgelöste Abos bekommen keine neuen Perioden.

**Löschen:** Ein Abo mit entschiedenen Perioden lässt sich nicht löschen —
setze es auf „beendet". Rechte: Sehen mit *Reselling-Register sehen*,
Pflegen mit *Reselling-Register pflegen*.

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

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

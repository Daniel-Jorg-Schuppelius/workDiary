---
title: "Arbeitsmappe und Abgleichstafel"
topic: finance.resale.board
version: 1
keywords:
    - Arbeitsmappe
    - Abgleichstafel
    - Zeitstrahl
    - Passung
    - Übernahme
    - Forderungsausfall
    - Insolvenz
    - Bestellungswechsel
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.reconcile
    - finance.resale.subscription
---

Die **Arbeitsmappe** eines Rechnungsempfängers ist die Fläche, auf der
zugeordnet wird: oben seine Abos, darunter je Produkt die Abgleichstafel mit
Perioden und Rechnungspositionen auf einem Zeitstrahl. Die Kacheln über der
Tafel filtern sie auf die betroffenen Produkte.

**Arbeitsmappe und Abgleichstafel:** Der Abgleich eines Empfängers ist die
Arbeitsmappe: oben die Abos mit Bearbeiten, Übernahme, Lizenzen abtreten und
Rücknahme, darunter je Produkt die Abgleichstafel. Links stehen alle
Perioden in Datumsfolge mit Soll und Deckung, rechts die
Rechnungspositionen: zugeordnete mit ihrem Leistungszeitraum, bei offenen
Perioden die passenden freien, Rechnungen an andere Kunden und Stornos.
Positionen ohne passende Periode stehen als eigene Zeile „ohne Periode“ an
ihrem Datum. Jede freie Rechnung steht nur bei der Periode, zu der sie
zeitlich am besten passt, und „… zuordnen“ ordnet sie mit einem Klick zu;
die Menge ist, was die Periode noch braucht. Ein Versatz von einigen Wochen
zwischen Bestelldatum beim Anbieter und Rechnungsdatum ist normal und wird
nicht angezeigt; eine Sammelrechnung, die die Periode ganz umfasst, passt.
Rechnungen ohne passende Periode stehen als Zeile „ohne Periode“ mit „Abo
anlegen“ oder „Einer anderen Periode zuordnen“. Fragliche Zuordnungen fragen
vor dem Speichern nach; im Bezug-Dialog und in der Schnellzuordnung der
Abo-Seite bestätigen Sie sie mit dem Häkchen. Bezüge, die zeitlich nicht
passen, sind gelb markiert — prüfen Sie sie und lösen Sie sie bei Bedarf.
„Nächsten Empfänger abarbeiten“ im Überblick führt durch alle Empfänger mit
offenem Betrag; fertig ist ein Empfänger ohne fällige offene Periode und
ohne Vorlagenbefund. Verlängert der Anbieter ein Abo als neue Bestellung,
verzichtet der Vorschlagslauf die kurze Restperiode der alten Bestellung und
gibt ihre Bezüge an die erste Periode der neuen. Die Restperiode steht dann als
„Bestellungswechsel“ da, nicht als Verzicht: Die Rechnungen des Nachfolgers
decken die Zeit.

**Passung:** Jede Rechnungsposition trägt eine Passung zur Periode — aus dem
Abstand des Periodenbeginns zum nächsten Zyklusbeginn ihres
Leistungszeitraums, ohne Zeitraum zum Rechnungsdatum. Bis 14 Tage gilt als
genau, bis 90 Tage als versetzt; Positionen, die nur überlappen, weit entfernt
oder außerhalb liegen oder zu einer anderen offenen Periode desselben Produkts
deutlich besser passen, fragen vor dem Zuordnen nach: in der Mappe als
Rückfrage am Knopf, im Bezug-Dialog und in der Schnellzuordnung der Abo-Seite
als Häkchen. Umfasst der Leistungszeitraum die ganze Periode, passt die
Position ohne Rückfrage.

**Insolvenz und Übernahme:** Wurde eine Rechnung storniert, weil der Kunde
insolvent ist, verbuchen Sie die Periode in der Tafel mit „Als Ausfall
verbuchen“ — der Verzicht-Dialog hat den Grund mit der Belegnummer
vorbelegt. Führt eine andere Firma die Lizenzen weiter, nutzen Sie
„Übernahme“ am Abo: neuer Halter und Stichtag. Liegt der Stichtag am Beginn
der ersten offenen Periode, rechnet der neue Halter auch die offenen
Perioden ab; liegt er später, verzichtet das Register die offenen Perioden
davor auf Wunsch als Forderungsausfall. Ein Stichtag am Abo-Beginn gibt das
ganze Abo an den neuen Halter, sonst entsteht ab dem Stichtag eine
Abtretung. Geht eine Rechnung schon an die nachfolgende Firma, bietet die
Tafel die Übernahme direkt an dieser Rechnung an.
Sind an den bisherigen Halter schon Perioden berechnet, lehnt das Register
einen Stichtag am Abo-Beginn ab — er muss dann ab der ersten offenen Periode
liegen.

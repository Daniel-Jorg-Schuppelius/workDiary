---
title: "Belegfluss"
topic: billing.feed
version: 5
keywords:
    - Belegliste
    - Rechnungen
    - Angebote
    - Ausgangsrechnungen
    - Eingangsrechnungen
    - Gutschriften
    - Auslagen
    - offene Posten
    - OP-Liste
    - mahnen
    - überfällige Rechnungen
    - E-Rechnung umwandeln
    - Projektfilter
audience: []
modules:
    - module.vertrieb
    - module.finance
related:
    - billing.chain
    - invoices.manage
    - quotes.overview
    - finance.incoming-invoices
    - travel-expenses.manage
---

Der Belegfluss zeigt **alle Belege in einer Liste**: Angebote, Ausgangs- und
Eingangsrechnungen, Gutschriften, gespiegelte Belege aus einer angebundenen
Buchhaltung und Auslagen. Die früheren Seiten „Angebote“ und „Rechnungen“
führen hierher – sie sind jetzt Reiter derselben Liste.

**Zeitraum:** Die Liste folgt dem Datumsfilter in der Kopfzeile. Fehlt ein
Beleg, prüfen Sie zuerst den gewählten Zeitraum. Ein Treffer der globalen
Suche öffnet die Liste auf dem Belegdatum; dann gilt dieser Tag statt des
Kopfzeitraums.

**Reiter:** „Alle“, „Angebote“, „Ausgangsrechnungen“, „Eingangsrechnungen“,
„Gutschriften“ und „Auslagen“ sind gespeicherte Filter, keine eigenen Seiten.
Die Zahl am Reiter nennt die Belege im Zeitraum. „Weitere“
(Auftragsbestätigungen, Lieferscheine, Sonstiges) erscheint nur, wenn dort
etwas liegt. Suche und Filter bleiben beim Wechsel des Reiters erhalten.

**Kennzahlen:** Die Kacheln rechnen über die gesamte gefilterte Menge, nicht
nur über die sichtbare Seite – je Währung getrennt und ohne Umrechnung.

Ein Klick auf **Offen** filtert auf offene Belege (ein zweiter Klick hebt den Filter auf); die Summenkacheln bleiben ohne Ziel.

- **Erlöse**, **Aufwand (extern)** und **Saldo** stellen Ausgangs- und
  Eingangsbelege gegenüber.
- **Meine Auslagen** nennt Ihre eigenen Auslagen und den Anteil, der noch in
  Prüfung ist.
- **Offen** und **davon überfällig**: Überfällig ist eine Teilmenge von Offen,
  die beiden Beträge werden nicht addiert. Ein Klick auf die Kachel filtert auf
  überfällige Belege.
- **Ohne Geldwirkung:** Angebote, Auftragsbestätigungen und Lieferscheine
  zählen nur als Anzahl.

**Filter:** Die Suche findet Nummer, Kunde und Lieferant. Dazu kommen Herkunft
(in WorkDiary erstellt oder aus einem angebundenen System), Zuordnung (Kunde
oder Lieferant), Status (Entwurf, Offen, Abgeschlossen, Storniert), „Nur
überfällige“ und „Archivierte einbeziehen“. Die Richtung wählen Sie nur in
„Alle“ und „Gutschriften“, weil die übrigen Reiter sie schon festlegen. Im
Reiter „Auslagen“ zeigt „Nur ohne Buchungsbeleg“ die Auslagen, denen noch kein
Beleg zugeordnet ist; die Verwaltung schaltet dort zwischen „Meine“ und „Alle“
um.

**Zeilen:** Die Nummer führt dorthin, wo der Vorgang bearbeitet wird – zur
Rechnung, zum Angebot, zur Eingangsrechnung oder zum Auslagenbeleg. Gespiegelte
Belege ohne eigene Seite haben keinen Link. Die Spalte „Fällig“ nennt bei
offenen Belegen die Tage im Verzug und die erreichte Mahnstufe. Überfällige
eigene Rechnungen mahnen Sie über „Mahnen“ direkt aus der Zeile.

**Gespiegelte Belege:** Führt das angebundene System einen Beleg nicht mehr,
steht er auf **In Quelle gelöscht**. Er zählt dann in keiner Kennzahl und
erscheint nur mit „Archivierte einbeziehen“. Bei Belegen in Fremdwährung steht
unter dem Betrag der Gegenwert in der zweiten Währung.

**Neue Belege:** Oben rechts legen Sie ein Angebot oder eine Rechnung an oder
wandeln eine Rechnungsdatei in eine E-Rechnung um. „Abzurechnen und
nachzufassen“ öffnet die Belegkette mit allem, was noch aussteht.

**Sichtbarkeit:** Der Belegfluss zeigt nur, was Ihre Rechte in den einzelnen
Bereichen ohnehin erlauben. Fehlt Ihnen etwa das Recht auf Angebote, fehlen der
Reiter und die zugehörigen Zeilen.

## Doppelte Belege und Original

**Extern führt:** Wurde eine Rechnung an ein Buchhaltungsprogramm übertragen
oder hat das Programm ihre Nummer vergeben (Lexoffice, orgaMAX, sevDesk,
easybill), steht sie nur einmal in der Liste – als gespiegelter Beleg. Das gilt
für jedes angebundene Programm, sobald ein Ausgangsbeleg dieselbe Nummer trägt
und demselben Kunden gehört: Eine Rechnungsnummer vergibt ein Unternehmen nur
einmal. Ein Beleg mit gleicher Nummer, aber anderem Kunden verdrängt Ihre
Rechnung nicht; Eingangsbelege und in der Quelle gelöschte Belege ebenso
wenig.

**Auslagen:** Ist einer Auslage ein Eingangsbeleg zugeordnet, zählt nur der
Beleg im Aufwand – bei Lexoffice wie bei sevDesk, Akaunting, Invoice Ninja und
den übrigen Programmen mit Belegspiegel.

**Summen:** Gespiegelte Eingangsbelege zählen im Aufwand, Gutschriften mindern;
Entwürfe und stornierte Belege zählen nicht.

**Original:** Gespiegelte Belege öffnen über die Nummer das Original im
Programm, wo es dafür eine Adresse gibt (Invoice Ninja, Akaunting). Bei
sevDesk-Rechnungen und -Gutschriften, easybill-Dokumenten und
orgaMAX-Rechnungen lädt **Original-PDF** am Zeilenende das PDF direkt aus dem
Programm; dafür brauchen Sie das Recht, Belege anzusehen, beim orgaMAX-PDF
zusätzlich die Administration. sevDesk-Belege ohne Rechnung (Kassenbon,
Lieferantenbeleg) und orgaMAX-Ausgaben haben kein PDF.

**orgaMAX und sevDesk:** orgaMAX spiegelt Rechnungen, Abschlagsrechnungen und
Ausgaben; die Ausgaben stehen als Eingangsbelege im Aufwand. Gutschriften aus
sevDesk stehen als ausgehende Gutschriften in der Liste und mindern den
Umsatz. Serienvorlagen eines Programms sind kein Beleg und erscheinen nicht im
Belegfluss; sie stehen im Abgleich von Abos und Lizenzen.

## Nach Projekt filtern

Der Filter **Projekt** zeigt nur Belege eines Projekts. Belege aus
Buchhaltungsprogrammen kennen kein Projekt und fallen bei diesem Filter
heraus.

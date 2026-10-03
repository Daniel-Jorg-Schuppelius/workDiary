---
title: "Belegfluss"
topic: billing.feed
version: 1
audience: []
modules:
    - module.vertrieb
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
Beleg, prüfen Sie zuerst den gewählten Zeitraum.

**Reiter:** „Alle“, „Angebote“, „Ausgangsrechnungen“, „Eingangsrechnungen“,
„Gutschriften“ und „Auslagen“ sind gespeicherte Filter, keine eigenen Seiten.
Die Zahl am Reiter nennt die Belege im Zeitraum. „Weitere“
(Auftragsbestätigungen, Lieferscheine, Sonstiges) erscheint nur, wenn dort
etwas liegt. Suche und Filter bleiben beim Wechsel des Reiters erhalten.

**Kennzahlen:** Die Kacheln rechnen über die gesamte gefilterte Menge, nicht
nur über die sichtbare Seite – je Währung getrennt und ohne Umrechnung.

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

**Neue Belege:** Oben rechts legen Sie ein Angebot oder eine Rechnung an oder
wandeln eine Rechnungsdatei in eine E-Rechnung um. „Abzurechnen und
nachzufassen“ öffnet die Belegkette mit allem, was noch aussteht.

**Sichtbarkeit:** Der Belegfluss zeigt nur, was Ihre Rechte in den einzelnen
Bereichen ohnehin erlauben. Fehlt Ihnen etwa das Recht auf Angebote, fehlen der
Reiter und die zugehörigen Zeilen.

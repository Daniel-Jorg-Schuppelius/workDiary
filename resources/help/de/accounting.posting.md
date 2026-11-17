---
title: "Buchen und Inbox"
topic: accounting.posting
version: 11
keywords:
    - Buchung erfassen
    - Belege buchen
    - Kontierung
    - Buchungsvorschlag
    - Buchungsregeln
    - Buchung stornieren
    - Storno
    - Generalumkehr
    - Festschreibung
    - Vier-Augen-Prinzip
    - Freigabe
    - Fremdwährung
    - Wechselkurs
    - Buchungsjournal
    - Offene Posten
    - wiederkehrende Buchung
    - Kontierungsvorschlag
    - Bankumsatz ohne Beleg
    - Gläubiger-ID
    - Mandatsreferenz
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.finance
related:
    - accounting.overview
    - accounting.closing
---

Die **Buchungs-Inbox** ist der Einstieg: Sie zeigt Belege, Auslagen, Kassen-
und Zahlungsvorgänge des Zeitraums mit ihrem Buchungsstatus — ungebucht,
blockiert, bereit, gebucht. Blockiertes steht oben; das ist die Arbeit, die
jemand anfassen muss.

**Vorschlag vor Festbuchung.** Auch ein eindeutiger Vorschlag wird erst zum
geprüften Entwurf, nie direkt zur Festbuchung. Ist das Vier-Augen-Prinzip
aktiv, schreibt die vorbereitende Person nicht selbst fest.

**Vier-Augen-Prinzip bei Direktbuchungen.** Einige Vorgänge legen ihre Buchung
selbst an: **Skonto** und **Ausbuchung** über **Ausgleichen** bei den offenen
Posten, **Auf Klärungskonto buchen** und **Auf Sachkonto buchen** im
Zahlungsabgleich, die **Interne Umbuchung**, **Startsalden übernehmen**, **Sondervorauszahlung buchen** und
**Stornieren** im Journal. Ohne Vier-Augen-Prinzip schreiben sie sofort fest.
Ist es aktiv, entsteht ein geprüfter Entwurf: Eine Meldung und ein Hinweis im
Dialog sagen das, und die Buchungs-Inbox führt ihn mit seiner Art
(**Skonto/Ausbuchung**, **Klärungsbuchung**, **Bankumsatz ohne Beleg**,
**Interne Umbuchung**,
**Startsalden**, **Sondervorauszahlung**, **Storno**) und dem Status **Bereit**
– unabhängig vom Zeitraum der Kopfzeile, solange **Alle Quellen** gewählt ist.
Wirksam wird der Vorgang erst, wenn eine zweite Person den Entwurf mit
**Festschreiben** oder **Alle übernehmen und festschreiben** festschreibt: Erst
dann ist der offene Posten ausgeglichen, der Bankumsatz gebucht und die
Sondervorauszahlung in der letzten Voranmeldung des Jahres angerechnet. Im
Journal bleibt **Sofort festschreiben** für die Person, die die Buchung anlegt,
gesperrt.

**Entwurf verwerfen.** Jeden Entwurf – aus einem dieser Vorgänge, aus einem
Buchungsvorschlag der Inbox, aus einer Buchungsvorlage oder von Hand erfasst –
löschen Sie mit **Entwurf verwerfen** in der Buchungs-Inbox oder auf seiner
Detailseite (mit Rückfrage). Entwürfe aus den genannten Vorgängen verwirft, wer
**Buchungen festschreiben** darf; für alle anderen genügt **Buchungen
vorbereiten**. Der Schritt wird protokolliert, und der Vorgang steht danach
wieder offen: Der Posten ist wieder ausgleichbar, Bankumsatz, Startsalden und
Sondervorauszahlung lassen sich neu buchen, die Buchung eines verworfenen
Stornos lässt sich erneut stornieren, eine interne Umbuchung entfällt samt
Kopplung ihrer Belege, ein über **Auf Sachkonto buchen** beiseitegelegter
Bankumsatz steht wieder offen, und der Beleg eines Buchungsvorschlags erscheint wieder
ungebucht in der Inbox. Der Vorgang einer Buchungsvorlage gilt als
**Übersprungen**; die nächste Periode läuft normal. Festgeschriebene Buchungen
lassen sich nicht verwerfen.

**Entwurf bearbeiten.** Solange eine Buchung nicht festgeschrieben ist, ändern
Sie sie auf ihrer Detailseite mit **Entwurf bearbeiten**: Buchungs- und
Belegdatum, Buchungstext, Beleg, Gegenpartei und die Zeilen mit Konto, Betrag,
Steuerkennzeichen, Kostenstelle und Zeilentext. Das gilt für Handbuchungen,
Entwürfe aus Buchungsvorlagen und Entwürfe aus Buchungsvorschlägen. Wer ändert,
gilt als Vorbereiter – bei Vier-Augen-Prinzip schreibt eine andere Person fest.
Die Änderung wird protokolliert. Entwürfe aus Skonto, Ausbuchung, Klärung,
Umbuchung, Bankumsatz ohne Beleg, Startsalden, Sondervorauszahlung und Storno
folgen ihrem Vorgang und lassen sich nicht umschreiben; an jedem Entwurf tragen
Sie aber über **Kostenstellen** die Kostenstelle je Zeile nach – etwa wenn ein
Konto mit **Kostenstelle Pflicht** das Festschreiben verhindert.
Festgeschriebene Buchungen bleiben unverändert.

**Blockiert statt geraten.** Fehlt eine Buchungsregel, nennt der Vorschlag
Rolle und Merkmale („Keine Buchungsregel für Erlös (tax_rate=19.00)"). Ein
geratenes Standardkonto fiele erst bei der Auswertung auf — dann ist die
Buchung festgeschrieben.

**Korrektur nur über Gegenbuchung.** Eine festgeschriebene Buchung ist
unveränderlich. Der Storno erzeugt eine gespiegelte Gegenbuchung mit
Pflichtbegründung; das Original bleibt stehen.

**Belege in Fremdwährung.** Ausgangs- und Eingangsrechnungen sowie Auslagen in
fremder Währung werden zum Monatskurs ihres Belegmonats umgerechnet
(§ 16 Abs. 6 UStG). Die Kurse pflegen Sie unter „Umrechnungskurse“ (bei den
Buchungsregeln) einzeln oder zeilenweise, etwa aus der BMF-Veröffentlichung.
Fehlt der Kurs, bleibt der Beleg mit Hinweis in der Inbox. Kurs und
Originalbetrag stehen im Buchungsnachweis. Zahlungen, Kasse und Anlagen in
Fremdwährung bucht die Buchhaltung weiterhin nicht; Kursdifferenzen beim
Ausgleich buchen Sie von Hand.

**Eingangsrechnungen je Steuersatz.** Trägt eine Eingangsrechnung mehrere
Steuersätze – in der Steueraufschlüsselung der E-Rechnung oder als Zeilen im
Dialog **Werte erfassen** –, bucht der Vorschlag Aufwand und Vorsteuer je Satz,
jeweils mit dem Vorsteuerkennzeichen dieses Satzes. So stimmen Kennziffer 66
und das Skonto je Satz. Ergibt die Aufschlüsselung nicht das Brutto, bleibt es
bei einer Zeile aus den Kopfwerten.

**Steuerschuld des Empfängers.** Nennt der Rechnungseingang als **Steuerfall**
§ 13b UStG oder einen innergemeinschaftlichen Erwerb, ist die Rechnung netto.
Der Vorschlag bucht Aufwand an Verbindlichkeit und dazu die selbst berechnete
Umsatzsteuer im Haben und dieselbe Vorsteuer im Soll – mit dem Kennzeichen
dieses Steuerfalls: Kennziffer 46/47 für sonstige Leistungen aus dem übrigen
Gemeinschaftsgebiet, 84/85 für die übrigen Fälle des § 13b, 89 bzw. 93 für den
Erwerb, die Vorsteuer in 67 bzw. 61. Der Satz ist der des Belegs, bei 0 % der
Regelsatz. Weist der Beleg trotzdem Steuer aus oder fehlt das Kennzeichen,
bleibt er mit Hinweis blockiert. Ein Skonto auf eine solche Rechnung ist netto:
Es mindert die Bemessungsgrundlage, Umsatzsteuer und Vorsteuer sinken um
denselben Betrag (§ 17 Abs. 1 Satz 5 UStG); ist das Skontokonto ein
DATEV-Automatikkonto, mindert das Skonto das Aufwandskonto des Belegs.
Festgeschriebene Eingangsrechnungen, deren Beleg Steuerschuld des Empfängers
nennt, deren Buchung aber keine Steuer trägt, nennt der Bericht
**Buchungsqualität**; nachgebucht wird nichts automatisch.

**Ausgangsrechnungen nach § 13b.** Ist ein Kunde im Inland als
Leistungsempfänger nach § 13b geführt (etwa bei Bauleistungen an
Bauleistende), geht die Rechnung ohne Umsatzsteuer und mit dem Hinweis
„Steuerschuldnerschaft des Leistungsempfängers“ hinaus. Die Buchung trägt das
Kennzeichen der Kennziffer 60, mit der Kontenplan-Vorlage auf dem Erlöskonto
8337 (SKR03) bzw. 4337 (SKR04); innergemeinschaftliche Lieferungen gehen auf
8125 bzw. 4125.

## Journal

Das **Buchungsjournal** öffnen Sie unter **Vertrieb & Abrechnung** →
**Buchhaltung** → **Journal**. Es zeigt alle vorbereiteten und
festgeschriebenen Buchungen im Zeitraum der Kopfzeile. Die Menüpunkte
**Journal**, **Offene Posten** und **Wiederkehrend** erscheinen, sobald Ihre
Organisation die lokale Buchhaltung führt oder geführt hat; führt derzeit ein
anderes System das Hauptbuch, weist ein Hinweis über der Liste darauf hin.

- **Liste:** **Nr.**, **Buchungsdatum**, **Buchungstext**, **Konten**,
  **Betrag** und **Status** (**Entwurf**, **Geprüft**, **Festgeschrieben**,
  **Storniert**). Die Suche findet Buchungstext und Beleg, der Statusfilter
  zeigt einen einzelnen Zustand. Die Journalnummer vergibt WorkDiary erst beim
  Festschreiben, fortlaufend und ohne Lücke.
- **Buchung erfassen:** Der Dialog bucht beliebig viele Zeilen. Pflicht sind
  **Buchungsdatum**, **Buchungstext** und mindestens zwei Zeilen mit **Konto**
  (Kontonummer, mit Vorschlagsliste) und einem Betrag im **Soll** oder im
  **Haben**; **Belegdatum**, **Beleg**, **Gegenpartei**,
  **Steuerkennzeichen**, **Kostenstelle** und **Zeilentext** sind optional.
  Leere Zeilen werden ignoriert. Mit **Steuerkennzeichen** tragen Sie den
  Nettobetrag ein; die Steuerzeile auf dem Steuerkonto des Kennzeichens
  entsteht beim Speichern, und die Buchung erscheint in den Kennziffern der
  Voranmeldung. Die **Gegenpartei** (Kunde oder Lieferant) gilt für Zeilen auf
  Konten mit offenen Posten und für Zeilen mit Steuerkennzeichen – so trägt
  der offene Posten den Kunden. Zur Wahl stehen nur aktive Konten und am
  Buchungstag gültige Steuerkennzeichen. Ohne **Sofort festschreiben**
  entsteht ein Entwurf.
- **Buchung ansehen:** Die Detailseite zeigt **Buchungskopf** und
  **Buchungszeilen** mit Soll- und Habensumme, dazu Hinweise auf einen Storno
  und auf überschrittene Monatsbudgets (ohne Sperre). Entwürfe und geprüfte
  Buchungen schreiben Sie hier mit **Festschreiben** fest.
- **Stornieren:** Eine festgeschriebene Buchung korrigieren Sie mit
  **Stornieren**: Die **Begründung** ist Pflicht, das **Buchungsdatum der
  Gegenbuchung** optional. Leer gelassen gilt der Originaltag, solange dessen
  Periode offen ist, sonst der heutige Tag. **Gegenbuchung erzeugen** schreibt
  die gespiegelte Buchung fest und nimmt auch die offenen Posten zurück, die
  aus dem Original entstanden sind. Ist das Vier-Augen-Prinzip aktiv, entsteht
  die Gegenbuchung als Entwurf: Die Buchung bleibt festgeschrieben und die
  offenen Posten unverändert, bis eine zweite Person das Storno festschreibt.
  Bis dahin ist kein zweites Storno derselben Buchung möglich; ihre
  Detailseite verweist mit **Wartenden Entwurf anzeigen** auf den Entwurf. Das
  automatische Storno beim Aufheben einer Zuordnung im Zahlungsabgleich
  schreibt immer sofort fest.

Beim Festschreiben prüft WorkDiary: Für das Buchungsdatum besteht eine offene
Periode, und die lokale Buchhaltung führt an diesem Tag das Hauptbuch; Soll und
Haben sind gleich hoch; alle Konten sind aktiv, und ein Konto mit **Kostenstelle
Pflicht** hat eine Kostenstelle. Ist das Vier-Augen-Prinzip aktiv, darf nicht
festschreiben, wer die Buchung angelegt hat – auch nicht über **Sofort
festschreiben**.

**Berechtigung:** Ansehen mit **Buchhaltung einsehen**, Erfassen mit
**Buchungen vorbereiten**, Festschreiben und Stornieren mit **Buchungen
festschreiben**.

## Offene Posten

Unter **Vertrieb & Abrechnung** → **Buchhaltung** → **Offene Posten** sehen
Sie Forderungen und Verbindlichkeiten aus festgeschriebenen Buchungen, die noch
nicht ausgeglichen sind – unabhängig vom Zeitraum der Kopfzeile. Ein offener
Posten entsteht, wenn eine Buchung auf ein Konto mit dem Merkmal **Offene
Posten** festgeschrieben wird; Zahlungen gleichen ihn über den Zahlungsabgleich
aus.

- Die Reiter **Forderung** und **Verbindlichkeit** trennen beide Richtungen.
- Die Kacheln summieren die offenen Beträge nach Alter ab Fälligkeit: **Nicht
  fällig**, **1–30 Tage**, **31–60 Tage**, **61–90 Tage** und **über 90 Tage**.
- Die Liste zeigt, nach Fälligkeit sortiert, **Beleg**, **Gegenpartei**,
  **Belegdatum**, **Fällig** (mit dem Hinweis „… Tage überfällig“),
  **Ursprung**, **Offen** und **Status** (**Offen**, **Teilweise
  ausgeglichen**, **Strittig**). **Buchung anzeigen** öffnet die zugrunde
  liegende Buchung.
- **Ausgleichen** erfasst einen Abzug ohne Zahlung: **Skonto**, **Einbehalt**
  oder **Ausbuchung**, mit **Betrag** und optionaler **Notiz**. Der Betrag
  darf den offenen Rest nicht übersteigen. Für Skonto und Ausbuchung schreibt
  WorkDiary zugleich eine Gegenbuchung ins Journal fest – auf das
  **Skontokonto** bzw. **Ausbuchungskonto** aus den DATEV-Einstellungen (leer = Vorgabe des Kontenrahmens: 8736 und 2400 im SKR03, 4736 und 6930 im
  SKR04). An einer Verbindlichkeit gelten eigene Konten: erhaltene Skonti (3736
  bzw. 5736) und für die Ausbuchung ein sonstiger Ertrag (2709 bzw. 4839).
  Skonto mindert auch die Steuer (§ 17 UStG): WorkDiary teilt den Betrag nach
  dem Steuersatz des Belegs in Netto (Skontokonto, mit Steuerkennzeichen) und
  Steueranteil (Umsatzsteuer- bzw. Vorsteuerkonto) – bei mehreren Sätzen
  anteilig, je Satz auf das Skontokonto des Satzes, solange kein eigenes Konto
  eingestellt ist. Die Kennziffern der Voranmeldung und die
  Umsatzsteuer-Vorschau zeigen die Minderung. Hat die Buchung des Postens
  keinen Steuerbezug (Kleinunternehmer, steuerfrei,
  Startsaldo), bleibt das Skonto ungeteilt; der Dialog sagt, was gilt.
  Dasselbe gilt für das Skonto aus dem Zahlungsabgleich. Früher ohne
  Steuerberichtigung gebuchte Skonti nennt der Bericht **Buchungsqualität**
  mit ihrem rechnerischen Steueranteil; nachgebucht wird nichts automatisch. Fehlt dieses Konto im Kontenplan, wird der Ausgleich mit einer
  Meldung abgewiesen und der Posten bleibt offen; der Dialog warnt schon
  vorher. Ein Einbehalt erzeugt keine Buchung. Ist das Vier-Augen-Prinzip
  aktiv, entsteht die Gegenbuchung als Entwurf in der Buchungs-Inbox, und der
  Posten bleibt offen, bis eine zweite Person sie festschreibt. Bis dahin
  zeigt die Liste **Entwurf wartet auf Freigabe**, **Wartenden Entwurf
  anzeigen** führt zur Buchung, und jeder weitere Ausgleich des Postens – auch
  ein Einbehalt – wird abgewiesen. Ist der Posten bis zur Freigabe anderweitig
  ausgeglichen, scheitert das Festschreiben, weil der Betrag den offenen Rest
  übersteigt. Skonto- und Ausbuchungsausgleiche aus der Zeit davor, die ohne
  Buchung geschlossen wurden, nennt der Bericht **Buchungsqualität**; sie sind
  von Hand nachzubuchen.
- **Als strittig kennzeichnen** markiert einen offenen Posten mit Pflichtgrund
  als **Strittig**. Er bleibt offen und ausgleichbar, auch nach einem
  Teilausgleich. An der Ausgangsrechnung setzt WorkDiary eine Mahnsperre,
  sofern dort noch keine steht – der Posten wird weder einzeln noch im
  Mahnlauf gemahnt –, und in der Liquiditätsübersicht und -vorschau zählt eine strittige Forderung nicht als erwarteter Eingang. Die
  Eingangsrechnung einer strittigen Verbindlichkeit steht im Zahlungsvorschlag
  gesperrt (**Offener Posten strittig**), bis das Kennzeichen zurückgenommen ist. **Strittig zurücknehmen**
  hebt das Kennzeichen und die daraus entstandene Mahnsperre wieder auf; eine
  von Hand gesetzte Mahnsperre bleibt. Der Filter über der Liste zeigt
  **Offen**, **Teilweise ausgeglichen** oder **Strittig**. Beide Schritte
  stehen im Änderungsprotokoll.

**Berechtigung:** Ansehen mit **Buchhaltung einsehen**, Ausgleichen mit
**Buchungen festschreiben**.

## Wiederkehrend

Unter **Vertrieb & Abrechnung** → **Buchhaltung** → **Wiederkehrend** (Seite
**Wiederkehrende Vorgänge**) planen Sie, was regelmäßig anfällt. Es gibt zwei
Arten von Vorlagen:

- **Belegerwartung:** für einen Beleg, der regelmäßig eingehen muss, etwa Miete
  oder Leasing. Sie erzeugt weder Beleg noch Buchung, sondern zur Fälligkeit
  einen offenen Vorgang mit dem Status **Beleg erwartet** – so bleibt sichtbar,
  dass das Original noch fehlt.
- **Buchungsvorlage:** erzeugt zur Fälligkeit einen Buchungsentwurf mit den
  Zeilen der Vorlage, datiert auf den Fälligkeitstag. Sie
  schreibt nie selbst fest; das erledigen Sie von Hand in der Buchungs-Inbox
  oder im Journal.

Die Seite gliedert sich in **Offene Vorgänge** (**Vorlage**, **Periode**,
**Fällig**, **Erwartet**, **Status**; bei **Blockiert** steht der Grund
darunter, bei **Entwurf erzeugt** führt **Buchung anzeigen** zum Entwurf, bei
**Beleg erwartet** ordnet **Beleg zuordnen** das Original zu),
**Vorlagen** (**Bezeichnung**, **Art**, **Rhythmus**, **Nächste Fälligkeit**,
**Verantwortlich**, **Status** mit Fassungsnummer) und **Serienrechnungen**:
aktive Abrechnungspläne nur zur Übersicht, bearbeitet über **Abrechnungspläne
öffnen**.

**Vorlage anlegen:** **Art**, **Bezeichnung**, **Rhythmus** (**Monatlich**,
**Vierteljährlich**, **Halbjährlich**, **Jährlich**), **Fälligkeitstag** (1–28,
damit jeder Monat ihn hat), **Erwartet**, **Beginn** und optional **Ende**,
für Buchungsvorlagen außerdem die Buchungszeilen, dazu **Verantwortlich**
und eine **Notiz**. Eine Buchungsvorlage ohne mindestens zwei Zeilen mit Konto
und Betrag wird nicht gespeichert. Beim Bearbeiten sind die gespeicherten Zeilen vorbelegt und
der Dialog zeigt die nächsten Fälligkeiten; jede Änderung speichert eine neue
Fassung, bereits erzeugte Vorgänge bleiben unverändert.

**Ablauf und Regeln:**

- Ein täglicher Lauf erzeugt die fälligen Vorgänge, solange die lokale
  Buchhaltung am Stichtag das Hauptbuch führt. Je Vorlage und Periode entsteht
  höchstens ein Vorgang.
- **Jetzt ausführen** erzeugt den Vorgang für die nächste Fälligkeit sofort,
  ohne auf den täglichen Lauf zu warten.
- Lässt sich ein Entwurf nicht anlegen, etwa weil für das Datum keine Periode
  besteht, steht der Vorgang als **Blockiert** mit Grund in der Liste.
- **Beleg zuordnen** erfüllt eine Belegerwartung: Sie wählen die eingegangene
  Rechnung im Feld **Eingangs-E-Rechnung**. Zur Wahl stehen Rechnungen aus
  **Eingangs-E-Rechnungen**, die Sie sehen dürfen, die nicht abgelehnt und noch
  keinem Vorgang zugeordnet sind – eine Rechnung erfüllt höchstens einen
  Vorgang. Der Vorgang steht danach auf **Erfüllt**.
- Wird der Entwurf einer Buchungsvorlage festgeschrieben, steht ihr Vorgang
  ebenfalls auf **Erfüllt**.
- Ist ein Vorgang mit **Beleg erwartet** oder **Entwurf erzeugt** überfällig,
  meldet WorkDiary das einmal über die Benachrichtigungen, ab Werk an die
  Buchhaltung und an die Person unter **Verantwortlich**.
- **Pausieren** hält eine Vorlage an; **Fortsetzen** macht mit der nächsten
  Fälligkeit ab heute weiter, ohne Versäumtes nachzuholen. **Beenden** stoppt
  die Vorlage endgültig, erzeugte Vorgänge bleiben bestehen.

**Buchungszeilen der Vorlage.** Eine Buchungsvorlage nimmt dieselben Zeilen wie
die Handbuchung: mehrere Zeilen, **Steuerkennzeichen**, **Kostenstelle** und
**Gegenpartei**. Soll und Haben müssen aufgehen, sonst wird die Vorlage nicht
gespeichert. Bleibt **Erwartet** leer, gilt die Buchungssumme. Ändert sich der
Satz eines Steuerkennzeichens, speichern Sie die Vorlage neu. Verwerfen Sie den
Entwurf einer Vorlage, gilt der Vorgang als **Übersprungen**.

**Berechtigung:** Ansehen mit **Buchhaltung einsehen**; Vorlagen anlegen,
bearbeiten, pausieren, fortsetzen und beenden mit **Buchhaltung einrichten**;
**Jetzt ausführen** und **Beleg zuordnen** mit **Buchungen vorbereiten**.

## Umsätze ohne Steuer und Auslagen

Die Kontenplan-Vorlagen führen eigene Erlöskonten für die Kennzeichen RC21
(Kennziffer 21), AUSF (Kennziffer 43) und NSTB (Kennziffer 45); Kontenpläne aus der
Vorlage erhalten sie mit dem Update. **Auslagen** buchen die Vorsteuer zum
Steuersatz des Belegs. Gibt es zu diesem Satz kein Vorsteuerkennzeichen — meist
ausländische Umsatzsteuer, die keine Vorsteuer ist —, hält der Vorschlag an:
Erfassen Sie die Auslage dann brutto ohne Steuersatz. Rechnungen mit Steuerschuld
des Empfängers gehören in den Rechnungseingang, der den Steuerfall kennt.

## Kontierungsvorschläge für Bankumsätze ohne Beleg

Miete, Leasing, Versicherung, Kontoführung: Ein Bankumsatz, den Ihre
Organisation schon einmal über **Auf Sachkonto buchen** gebucht hat, bekommt
beim nächsten Mal einen **Vorschlag** — Sachkonto, Steuerkennzeichen,
Kostenstelle und Buchungstext der letzten gleichartigen Buchung. Gleichartig
heißt: gleiche Richtung und, in dieser Rangfolge, gleiche **Mandatsreferenz**,
gleiche **Gläubiger-ID** der Lastschrift, gleiche **IBAN der Gegenpartei** oder
gleiche **Stichwörter im Verwendungszweck** (Monat, Datum und Nummern zählen
nicht). Die Begründung steht dabei, etwa „3-mal so gebucht, zuletzt 03/2026,
gleiche Mandatsreferenz“, dazu die Stärke des Vorschlags. Die jüngste Buchung
bestimmt den Vorschlag: Buchen Sie einmal auf ein anderes Konto, gilt beim
nächsten Mal dieses — eine eigene Regel müssen Sie nicht pflegen.

Der Dialog **Auf Sachkonto buchen** ist mit dem Vorschlag vorbelegt. Unter
**Kontierungsvorschläge** in der Buchungs-Inbox stehen gleichartige offene
Umsätze gebündelt, mit der Vorschau ihrer Buchungen; **Alle … Umsätze buchen**
bucht sie mit einer Bestätigung. Ein Umsatz, dessen Betrag um mehr als 10 %
vom letzten Mal abweicht, und Vorschläge mit Hinweis (Konto stillgelegt,
Steuerkennzeichen nicht mehr gültig) stehen einzeln unter **Einzeln prüfen**.
Gebucht wird nur, was Sie bestätigen; ist das Vier-Augen-Prinzip aktiv,
entstehen Entwürfe zur Freigabe.

Vorgeschlagen wird nur, was die Organisation selbst schon so gebucht hat —
keine KI. Die Gläubiger-ID übernimmt der Import aus CAMT- und MT940-Dateien,
sofern die Bank sie mitliefert. Der Verwendungszweck bleibt verschlüsselt; für
den Vergleich speichert workDiary nur einen geschlüsselten Abdruck der
Stichwörter. **Berechtigung:** **Buchungen festschreiben**.

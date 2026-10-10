---
title: "Buchen und Inbox"
topic: accounting.posting
version: 5
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
Posten, **Auf Klärungskonto buchen** im Zahlungsabgleich, die **Interne
Umbuchung**, **Startsalden übernehmen**, **Sondervorauszahlung buchen** und
**Stornieren** im Journal. Ohne Vier-Augen-Prinzip schreiben sie sofort fest.
Ist es aktiv, entsteht ein geprüfter Entwurf: Eine Meldung und ein Hinweis im
Dialog sagen das, und die Buchungs-Inbox führt ihn mit seiner Art
(**Skonto/Ausbuchung**, **Klärungsbuchung**, **Interne Umbuchung**,
**Startsalden**, **Sondervorauszahlung**, **Storno**) und dem Status **Bereit**
– unabhängig vom Zeitraum der Kopfzeile, solange **Alle Quellen** gewählt ist.
Wirksam wird der Vorgang erst, wenn eine zweite Person den Entwurf mit
**Festschreiben** oder **Alle übernehmen und festschreiben** festschreibt: Erst
dann ist der offene Posten ausgeglichen, der Bankumsatz gebucht und die
Sondervorauszahlung in der letzten Voranmeldung des Jahres angerechnet. Im
Journal bleibt **Sofort festschreiben** für die Person, die die Buchung anlegt,
gesperrt.

**Entwurf verwerfen.** Einen wartenden Entwurf aus diesen Vorgängen löschen
Sie mit **Entwurf verwerfen** in der Buchungs-Inbox oder auf seiner
Detailseite (Recht **Buchungen festschreiben**, mit Rückfrage). Der Schritt
wird protokolliert, und der Vorgang steht danach wieder offen: Der Posten ist
wieder ausgleichbar, Bankumsatz, Startsalden und Sondervorauszahlung lassen
sich neu buchen, die Buchung eines verworfenen Stornos lässt sich erneut
stornieren, und eine interne Umbuchung entfällt samt Kopplung ihrer Belege.
Festgeschriebene Buchungen und Entwürfe aus Buchungsvorschlägen lassen sich
nicht verwerfen.

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
- **Buchung erfassen:** Der Dialog bucht einen Betrag von einem **Soll**- auf
  ein **Haben**-Konto. Pflicht sind **Buchungsdatum**, **Buchungstext**, beide
  Konten und **Betrag**; **Belegdatum**, **Beleg** und – sofern Kostenstellen
  gepflegt sind – eine **Kostenstelle** für beide Zeilen sind optional. Zur
  Wahl stehen nur aktive Konten. Ohne **Sofort festschreiben** entsteht ein
  Entwurf.
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
  oder **Ausbuchung**, mit **Betrag** und optionaler **Notiz**. Der Betrag darf
  den offenen Rest nicht übersteigen. Für Skonto und Ausbuchung schreibt
  WorkDiary zugleich eine Gegenbuchung ins Journal fest – auf das Skonto- bzw.
  Ausbuchungskonto aus den DATEV-Einstellungen, sofern dieses Konto im
  Kontenplan angelegt ist. Ein Einbehalt erzeugt keine Buchung.
  Ist das Vier-Augen-Prinzip aktiv, entsteht die Gegenbuchung als Entwurf
  in der Buchungs-Inbox, und der Posten bleibt offen, bis eine zweite Person
  sie festschreibt. Bis dahin zeigt die Liste **Entwurf wartet auf Freigabe**,
  **Wartenden Entwurf anzeigen** führt zur Buchung, und jeder weitere
  Ausgleich des Postens – auch ein Einbehalt – wird abgewiesen. Ist der Posten
  bis zur Freigabe anderweitig ausgeglichen, scheitert das Festschreiben, weil
  der Betrag den offenen Rest übersteigt. Einbehalt und Ausgleich ohne
  angelegtes Gegenkonto erzeugen keine Buchung und gelten sofort.

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
- **Buchungsvorlage:** erzeugt zur Fälligkeit einen Buchungsentwurf mit Soll-
  und Habenkonto und dem erwarteten Betrag, datiert auf den Fälligkeitstag. Sie
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
für Buchungsvorlagen außerdem **Soll** und **Haben**, dazu **Verantwortlich**
und eine **Notiz**. Eine Buchungsvorlage ohne beide Konten und Betrag wird
nicht gespeichert. Beim Bearbeiten sind die gespeicherten Konten vorbelegt und
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

**Berechtigung:** Ansehen mit **Buchhaltung einsehen**; Vorlagen anlegen,
bearbeiten, pausieren, fortsetzen und beenden mit **Buchhaltung einrichten**;
**Jetzt ausführen** und **Beleg zuordnen** mit **Buchungen vorbereiten**.

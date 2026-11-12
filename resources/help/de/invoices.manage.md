---
title: "Rechnungen & Belege"
topic: invoices.manage
version: 17
keywords:
    - Rechnung erstellen
    - Rechnung schreiben
    - Ausgangsrechnung
    - Faktura
    - Rechnung stornieren
    - Gutschrift
    - E-Rechnung
    - XRechnung
    - Peppol
    - Zahlungslink
    - Kupferzuschlag
    - Stripe
    - Mollie
    - SumUp
    - zugehörige Belege
    - Belegkette
    - Rechnung an Lexoffice übertragen
    - Entwurfsnummer
    - Rechnungsnachfrage
    - Leistungsnachweis
    - Stundennachweis
    - rechnungsbegründende Unterlagen
    - Behörde
    - Leitweg-ID
    - OZG-RE
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - projects.manage
    - finance.datev-bookings
    - finance.transfers
    - travel-expenses.manage
---

## Zweck und Hintergrund

Die Rechnungsübersicht verwaltet lokale Rechnungen und angebundene
Belege. Welcher Weg führend ist, hängt von der Organisation und der
eingesetzten Faktura-Integration ab: Je Zeitraum stellt entweder
WorkDiary die Rechnungen oder genau ein externes System — nie beide
gleichzeitig.

## Voraussetzungen

- Geprüfte Stammdaten: Kunde, Empfängeradresse, Steuerangaben.
- **Leistungszeitraum und Projektbezug** der abzurechnenden Positionen.
- Das Recht, Rechnungen zu erstellen; für Mahnläufe die entsprechende
  Finanzrolle.

## Empfohlener Ablauf

1. Kunde und Zeitraum wählen — der Erstell-Dialog zeigt eine
   **Vorschau** der entstehenden Positionen (Anzahl, Dauer in Uhren-
   und Dezimalformat, Betrag, Nachzügler-Warnung).
2. Einzelne Zeiteinträge bei Bedarf per Häkchen **ausschließen** — sie
   bleiben offen und erscheinen im nächsten Lauf.
3. Entwurf prüfen und ergänzen; je Position sind die
   **Quell-Zeiteinträge** aufklappbar (1,50 h = 1:30 h). Bei einem
   Artikel mit Kupfergewicht fügt das Häkchen **Kupferzuschlag** im
   Positionsdialog den Zuschlag zum aktuellen DEL-Tagespreis als eigene
   Position an.
4. Stellen bzw. versenden — PDF, Versand und externe Synchronisation
   sind Ausgaben desselben dokumentierten Stands.
5. Bei Zahlungsverzug den **Mahnlauf** nutzen: Stufe 1 erzeugt eine
   Zahlungserinnerung als eigenes Mahnschreiben-PDF mit
   Forderungsübersicht, optionaler Mahngebühr und Zahlungsziel; die
   E-Mail enthält Mahnschreiben und Original-Rechnung. Ein neuer Beleg
   entsteht dabei nicht.

**E-Rechnung.** Die XRechnung entsteht in der UBL-Syntax; verlangt ein
Empfänger CII, wählen Sie beim Kunden oder beim Versand das Zustellformat
„XRechnung (XML, CII-Syntax)“. Über Peppol geht immer UBL. Ohne USt-IdNr. —
etwa als Kleinunternehmer nach § 19 UStG — genügt die Steuernummer in den
E-Rechnungs-Stammdaten: Sie wird zusätzlich als Verkäuferkennung eingetragen,
die die Prüfung beim Empfänger verlangt.

## Beispiel aus der Praxis

Zum Monatsende wählt die Buchhaltung „Müller GmbH" und den Vormonat:
Die Vorschau zeigt 14 Positionen und warnt vor zwei Nachzügler-Zeiten.
Ein strittiger Eintrag wird ausgeschlossen und wandert automatisch in
den nächsten Lauf — die Rechnung geht ohne Diskussion raus.

## Typische Fehler

- **Versandte oder übergebene Belege still ändern:** Gestellte,
  gebuchte oder extern übergebene Belege sind unveränderlich — für
  Fehler gibt es den Storno- bzw. Korrekturprozess.
- **Belegnummern oder Beträge nachträglich überschreiben** statt zu
  korrigieren — das zerstört die Nachvollziehbarkeit.
- **Doppelte Rechnungshoheit:** Führt ein externes System die Faktura,
  entstehen lokale Rechnungen bewusst nicht parallel.

## Auswirkungen und nächste Schritte

Gestellte Rechnungen fließen in offene Posten, Mahnwesen und die
Buchhaltungsübergabe. Als Nächstes: Zahllauf und Zahlungszuordnung
prüfen und den DATEV-Buchungsstapel für die Kanzlei erzeugen.

## Freie Rechnung ohne Zeiten

Im Erstell-Dialog steht **„Positionen selbst zusammenstellen“** gleichberechtigt
neben der Übernahme aus Zeiten oder Materialverbrauch. Der Entwurf braucht nur
den Kunden (optional Projekt, Endkunde und Zahlungsziel) und startet leer: Er
lässt sich speichern und später ergänzen, aber weder stellen noch versenden,
solange er keine Position hat — das gilt für Ausstellung, E-Mail, Peppol und
Lexoffice-Übergabe gleichermaßen. Ein Doppelklick auf „Entwurf erstellen“
erzeugt keine zweite Rechnung.

**Artikel, Material und Leistungen.** Eine Position ist ein Artikel (mit
optionaler Variante), Material oder Freitext — etwa „Montage pauschal“ oder
eine Sonderanfertigung ohne Fertigungsauftrag. Beschreibung, Artikelnummer,
Einheit und Preis werden als Belegwerte eingefroren; spätere Änderungen im
Artikelstamm ändern den Beleg nicht. Ein fehlender Preis muss bewusst
eingegeben werden (0,00 ist als Gratisposition erlaubt); ein Artikelpreis in
fremder Währung wird nicht still umgerechnet. Artikel- und Freitextpositionen
buchen **keinen Lagerbestand**; Lieferungen laufen über Lager/Auslieferung.

**Fertigungsauslieferungen abrechnen.** Mit aktivem Lagermodul übernimmt
„Auslieferung übernehmen“ erfolgte Auslieferungen des Kunden mit lokalem
Fakturaziel — jede vollständig als eine Position mit quellengebundener Menge
und dem Verkaufspreis der Auslieferung (nicht den Herstellkosten). Eine
Auslieferung kann nur in einem Entwurf zugleich stehen; die Ausstellung
kennzeichnet sie als abgerechnet, Entfernen der Position, Verwerfen des
Entwurfs oder ein Vollstorno geben sie wieder frei, die Herkunft bleibt am
Beleg sichtbar. Teilgutschriften geben nichts frei; der Lagerbestand bleibt bei
allen Rechnungsvorgängen unverändert.

## Online-Zahlung

Ist ein Zahlungsanbieter (Stripe, Mollie oder SumUp) als Plugin
eingerichtet, tragen ausgestellte Rechnungen einen Zahlungslink mit
QR-Code; dieselbe Adresse steht über den Platzhalter {{payment_link}} in
der Mail und im Kundenportal. Der Link führt immer über workDiary: Erst
beim Aufruf entsteht beim Anbieter die Bezahlseite über den dann offenen
Betrag, ein gedruckter Code bleibt also nach Teilzahlungen richtig.

Bestätigt der Anbieter die Zahlung, gilt die Rechnung als bezahlt oder
teilbezahlt — wie bei einem zugeordneten Bankumsatz; eine Erstattung nimmt
das zurück. Mit der lokalen Buchhaltung erscheint die Zahlung im
Buchungseingang (Geldtransit an Forderung, die Gebühr des Anbieters als
Nebenkosten des Geldverkehrs); die spätere Auszahlung des Anbieters buchen
Sie gegen das Geldtransit-Konto. Eine Online-Vorauszahlung aus dem Verleih
bucht schon am Zahlungstag als erhaltene Anzahlung mit Umsatzsteuer; die
Rechnung, die sie anrechnet, löst die Anzahlung auf und weist als
Forderung nur den offenen Rest aus. Den Link auf Rechnung und Mail
schalten Sie in den Organisationseinstellungen unter „Online-Zahlung“ ab;
im Kundenportal bleibt er.

## Stornieren und neu abrechnen

Ein Entwurf wird direkt storniert, eine ausgestellte Rechnung über einen
Stornobeleg mit eigener Nummer. Beide Wege geben alles frei, was die Rechnung
abgerechnet hat, wie der Storno einer Übergabe: gebuchte Zeiten, Material,
Anfahrten, Auslagen, Mietpositionen, Auslieferungen und die Auffüllung auf das
Projekt-Soll. Eine Online-Vorauszahlung aus dem Verleih geht an ihre Buchung
zurück und wird auf die nächste Rechnung angerechnet. Die Positionen der
stornierten Rechnung bleiben unverändert stehen; die nächste Rechnung für den
Zeitraum enthält die Stunden und die Auffüllung wieder. Eine bezahlte Rechnung
korrigiert eine Korrekturrechnung. Sie gibt nichts frei, weil sie auch nur
einen Teil gutschreiben kann.

Eine ausgestellte Rechnung, die nur eine Online-Vorauszahlung teilweise deckt,
stornieren Sie ebenfalls per Stornobeleg: Die Vorauszahlung geht an ihre
Buchung zurück und wird auf die nächste Rechnung angerechnet. Deckt eine
andere Zahlung die Rechnung teilweise – ein Bankumsatz im Zahlungsabgleich
(auch ein anerkanntes Skonto), eine Kassenzahlung, eine Online-Zahlung der
Rechnung oder ein Ausgleich am offenen Posten –, bleibt der Storno gesperrt,
und die Rechnung nennt den Grund. Heben Sie diese Zahlung zuerst auf; ist die
Rechnung vollständig bezahlt, korrigieren Sie sie mit einer Korrekturrechnung.

Mit der lokalen Buchhaltung gleicht der Stornobeleg genau den offenen Posten
der stornierten Rechnung aus, nach einer Vorauszahlung den Rest nach der
Anzahlung; andere offene Posten des Kunden bleiben unberührt. Die Anzahlung
steht danach wieder offen und wird mit der nächsten Rechnung aufgelöst. Buchen
Sie deshalb die Rechnung vor ihrem Stornobeleg, sonst wartet der Stornobeleg
im Buchungseingang mit einem Hinweis. Eine stornierte Rechnung mit Stornobeleg
erscheint dort ebenfalls zum Buchen, denn beide Belege gehören ins Journal.
Der Stornobeleg zählt nicht als Zahlung, auch nicht in den Auswertungen nach
Zahlungseingang (EÜR, Umsatzsteuer nach vereinnahmten Entgelten).

## Zugehörige Belege

Unten auf der Seite listet **Zugehörige Belege** alle Belege, die mit
diesem zusammenhängen: Angebot und seine Versionen, Rechnungen, Abschläge und
Schlussrechnung, Gutschriften und Stornos, Lieferscheine, Fertigungsaufträge
und Übergaben. Hat ein Buchhaltungsprogramm einen Beleg übernommen, steht
sein Gegenstück dort mit der Nummer des Programms. Bezüge, die das Programm
selbst meldet, etwa ein in Lexoffice angelegter Abschlag, erscheinen ebenfalls.
Die Spalte **Bezug** sagt, was der Beleg für diesen ist; „über …“ nennt den
Beleg, über den der Zusammenhang besteht.

## Nummer vom Buchhaltungsprogramm

Vergibt ein Buchhaltungsprogramm die Rechnungsnummern (Seite
**Nummernkreise**), trägt der Entwurf eine Entwurfsnummer, und die Rechnung
zeigt einen Hinweis. **Stellen** – auch der E-Mail-Versand eines Entwurfs –
überträgt die Rechnung an das Programm und übernimmt dessen Nummer, bevor PDF
und Betreff entstehen. Lexoffice und easybill übernehmen die
Auftragsbestätigung bzw. das Angebot als Vorgänger; orgaMAX legt die Rechnung über einen Auftrag an und schreibt sie fest. Gutschrift und Storno
gehen mit Bezug auf die übertragene Rechnung hinüber; bei sevDesk und
easybill muss die Rechnung dafür im Programm sein, orgaMAX übernimmt beide nicht. Lehnt das Programm ab,
bleibt der Beleg Entwurf, und die Meldung nennt den Grund. Abschlags- und
Schlussrechnungen legen Sie in diesem Fall im Programm an.

## Nachfragen zu Rechnungen

Antwortet ein Kunde per E-Mail auf eine Rechnung, erfasst WorkDiary beim
Abruf des Postfachs eine **Nachfrage zur Rechnung**: in der Historie der
Rechnung, als Kommunikationsnotiz beim Kunden und im Abschnitt
**Nachfragen** auf der Rechnungsseite. Erkannt wird die Antwort auf die von
WorkDiary versandte Rechnungsmail sowie eine Rechnungs- oder Belegnummer in
Betreff oder Text – auch für Belege, die Lexoffice, sevDesk, easybill oder
ein anderes angebundenes Programm gestellt hat. Bei einer Nummer muss der
Absender zum Vorgang gehören (Kunde, Ansprechpartner, Portalkonto oder
Empfänger der Rechnungsmail); sonst landet die Mail markiert in der
Zuordnungs-Inbox, ebenso bei mehreren Nummern. Jede Nachricht wird nur
einmal erfasst, Abwesenheitsnotizen nie.

Die Arbeitsliste **Rechnungsnachfragen** (Schaltfläche im Belegfluss) zeigt
offene und beantwortete Nachfragen aller Rechnungen und Belege; **Als
beantwortet markieren** schließt eine Nachfrage, **Wieder öffnen** holt sie
zurück. Beides steht in der Historie der Rechnung.

## Rechnungsbegründende Unterlagen und Leistungsnachweis

Stunden- und Leistungsnachweise gehen eingebettet in der E-Rechnung mit – in
der XRechnung, im ZUGFeRD-PDF und über Peppol. So verlangen es Behörden und
das Finanzamt: Ein loser Mail-Anhang oder ein Link gehört nicht zur Rechnung.
Die Karte **Rechnungsbegründende Unterlagen** auf der Rechnungsseite bietet im
Entwurf drei Wege:

- **Leistungsnachweis erzeugen** baut aus den abgerechneten Zeiten ein PDF: je
  Position Datum, Person, Uhrzeit, Dauer und Tätigkeit, dazu die Freigaben
  unterschriebener Stundenzettel. Auf Wunsch kommen die unterschriebenen
  Stundenzettel und eine CSV-Tabelle dazu (Organisation, Abschnitt
  **E-Rechnung: Unterlagen und Empfänger**).
- **Unterlage hinzufügen** nimmt eine eigene Datei: PDF, PNG, JPEG, CSV, XLSX
  oder ODS ohne Passwortschutz. Andere Formate nehmen Rechnungsempfänger nicht an.
- **Link hinzufügen** ist nur für Portale gedacht, etwa die „Großen Anlagen“
  der OZG-RE. Steuerlich ersetzt ein Link keinen eingebetteten Anhang.

Dateinamen vergibt WorkDiary selbst (`nachweis-1-…`), damit sie eindeutig
sind. Mit **Stellen** werden die Unterlagen festgeschrieben; ein erzeugter
Nachweis entsteht dabei neu mit der endgültigen Rechnungsnummer. Danach lassen
sich Unterlagen weder ändern noch entfernen. Wer per PDF versendet, bekommt die
Unterlagen als eigene Dateien in derselben Mail.

Die E-Rechnung nennt den Leistungszeitpunkt im strukturierten Teil: das
Leistungsdatum oder den Zeitraum der Positionen, sonst das Rechnungsdatum. Die
Leistungsbeschreibung bleibt in den Positionen; der Nachweis ergänzt sie.

### Behördenkunden

Im Kundenstamm stellen Sie das **Empfängerprofil der E-Rechnung** auf
„Öffentlicher Auftraggeber (Behörde)“. Dann gilt:

- Die Leitweg-ID (Käuferreferenz) ist Pflicht.
- Per E-Mail geht nur die XRechnung, ohne PDF daneben; der Versanddialog bietet
  nur diese Formate an. Elektronischer Versand stellt einen Entwurf zuerst,
  damit die XML die festgeschriebenen Unterlagen trägt.
- Der Leistungsnachweis wird beim Stellen automatisch beigelegt, wenn die
  Rechnung Zeiten trägt.
- Die E-Rechnung mit Unterlagen darf höchstens 10 MB groß sein; die
  E-Rechnungs-Prüfung meldet, wenn es mehr wird. Je Kunde lässt sich die
  Grenze ändern, ebenso, ob ein Nachweis immer oder nie beiliegt.

Vergibt ein Buchhaltungsprogramm die Nummer, bleiben die Unterlagen in
WorkDiary: An Lexoffice, sevDesk, easybill oder orgaMAX überträgt WorkDiary
sie nicht. Wer die E-Rechnung aus dem Programm verschickt, hängt sie dort von
Hand an; die Integrations-Inbox erinnert daran. Am Übergabenachweis steht dafür
**Leistungsnachweis** zum Herunterladen.

## Sicherheitseinbehalte freigeben

Hinterlegte Einbehalte stehen auf der Rechnungsseite in der Karte
**Sicherheitseinbehalte** mit Art, Betrag, Freigabetermin und Status. An der
ausgestellten Rechnung öffnet das Schloss-Symbol den Dialog **Freigeben**;
eine Notiz hält den Anlass fest. Mit der Freigabe wird der Betrag ein normaler
offener Posten, zählt wieder zum Zahlbetrag und wird gemahnt. Ein durch
Bürgschaft abgelöster Einbehalt ist bereits ausgezahlt: Seine Freigabe beendet
nur den Sicherungszweck, es entsteht keine neue Forderung, und das
Bürgschaftsregister meldet die Urkunde zur Rückgabe. Freigeben darf, wer das
Abrechnungsrecht hat.

## Freigabe und Steuerfall des Entwurfs

Mit **Freigabepflicht** gilt die Freigabe dem geprüften Stand: Ändern Sie danach
Positionen, Konditionen, Kunde, Datum oder Rechnungstext, ist die Freigabe
aufgehoben und muss erneut erteilt werden — nicht von der Person, die die
Rechnung angelegt hat. Das Protokoll hält die Aufhebung fest.

Den **Steuerfall** (Reverse Charge, Ausfuhr, steuerfrei) übernimmt jeder Entwurf
aus den Kundendaten, auch der freie Entwurf. Ändern sich die Kundendaten danach —
etwa durch das Merkmal „Leistungsempfänger schuldet die Umsatzsteuer“ oder eine
neue USt-IdNr. —, zieht die nächste Bearbeitung des Entwurfs den Fall nach.
Stellen Sie einen Entwurf mit veraltetem Steuerfall, passt workDiary ihn an und
hält die Ausstellung an: Prüfen Sie die Summen und stellen Sie erneut.

Bei **Reverse Charge** braucht die E-Rechnung die USt-IdNr. des Kunden (Regel
BR-AE-02), auch bei § 13b UStG im Inland; die Prüfung vor dem Versand meldet eine
fehlende. Alle Positionen stehen dann mit 0 % und dem Hinweis
„Steuerschuldnerschaft des Leistungsempfängers“ in der XRechnung.

Mit dem Steuerfall zieht der Entwurf den **Hinweistext** mit: Steht der
automatische Hinweis des alten Falls (etwa zu Reverse Charge oder zur Ausfuhr)
unverändert im Rechnungstext, ersetzt ihn der Hinweis des neuen Falls oder er
entfällt. Haben Sie den Hinweis von Hand geändert, bleibt Ihr Text stehen —
workDiary meldet das, und Sie prüfen den Hinweis selbst.

## Befreiungsgründe in der E-Rechnung

Jede Steuerkategorie ohne Steuer trägt in der XRechnung und im ZUGFeRD-PDF ihren
Befreiungsgrund, bei gemischten Rechnungen je Steueraufriss: Reverse Charge (AE),
innergemeinschaftliche Lieferung (K), Ausfuhr (G) und „nicht steuerbar“ (O) mit
Text und Code; Kategorie Z bleibt ohne Grund. „Steuerfrei“ (E) nennt § 19 UStG nur
für Kleinunternehmer. Für andere Steuerbefreiungen legen Sie in der
**Steuerregelmatrix** eine Regel mit der Satzart „Befreit“ an und tragen den
Befreiungstatbestand als Beleg-Hinweistext ein, etwa „Steuerfreie Heilbehandlung
(§ 4 Nr. 14 UStG)“; ohne Regel steht nur „Steuerfreier Umsatz“, und die Prüfung vor
dem Versand warnt.

Die Prüfung vor dem Versand meldet außerdem: fehlende USt-IdNr. des Kunden bei
innergemeinschaftlicher Lieferung, fehlende USt-IdNr. der Organisation bei
Lieferung ins EU-Ausland und Ausfuhr, „nicht steuerbar“ neben anderen Kategorien
und eine Position ohne Steuerkategorie mit Steuersatz über 0 %. Bei „nicht
steuerbar“ stehen keine USt-IdNrn. in der E-Rechnung, es genügt die Steuernummer.
Zwei Angaben fehlen der E-Rechnung noch (Warnung): das Lieferland bei
innergemeinschaftlicher Lieferung und der Verzicht auf den Positionssatz bei
„nicht steuerbar“.

## Befreiungshinweis im Rechnungs-PDF

Positionen ohne Steuer brauchen auch auf der Rechnung selbst einen Hinweis
(§ 14 Abs. 4 Nr. 8, § 14a UStG). Das PDF druckt unter dem Steuerausweis je
Steuerkategorie denselben Text wie der Befreiungsgrund der E-Rechnung, etwa
„Steuerfreie innergemeinschaftliche Lieferung“ bei K oder
„Steuerschuldnerschaft des Leistungsempfängers“ bei einer einzelnen Position mit
AE. Enthält der Rechnungstext den Hinweis schon — oder den automatischen Hinweis
dieser Kategorie aus dem Steuerfall —, entfällt die Zeile. Reverse Charge des
ganzen Belegs und § 19 UStG stehen wie bisher in einer eigenen Zeile.
Rechnungen, die vor dieser Erweiterung ausgestellt wurden, druckt workDiary
unverändert nach. Gutschrift und Stornorechnung übernehmen die Steuerkategorie
jeder Position des Originals.

Wählen Sie an einer Position oder im Abrechnungsplan eine Kategorie ohne Steuer
(AE, Z, E, G, K, O), setzt das Formular den Steuersatz auf 0 %; gespeichert wird
in jedem Fall 0 %.

## Empfänger und USt-IdNr. auf der Rechnung

Eine ausgestellte Rechnung druckt den Empfänger so, wie er beim Ausstellen
feststand: Name, Firma, Anschrift und USt-IdNr. des Kunden werden mit der
Ausstellung eingefroren. Ändern Sie danach die Kundendaten, bleiben Nachdruck,
PDF-Anhang, XRechnung, ZUGFeRD und GAEB-Export unverändert; erst neue Rechnungen
übernehmen die neue Anschrift. Ein Entwurf zeigt immer die aktuellen
Kundendaten. Stornobeleg und Korrekturrechnung frieren den Empfänger beim
eigenen Ausstellen ein. Die elektronische Zustelladresse (Peppol-Kennung oder
E-Mail) und Mahnschreiben folgen dagegen den aktuellen Kundendaten, weil sie
heute zugestellt werden.

Ist beim Kunden eine USt-IdNr. hinterlegt, steht sie im Kopf der Rechnung
(„Ihre USt-IdNr.“). Bei innergemeinschaftlicher Lieferung und Reverse Charge ist
sie Pflichtangabe (§ 14a UStG). Rechnungen, die vor dieser Erweiterung
ausgestellt wurden, druckt workDiary ohne die Zeile nach.

Den Ausweis der Arbeitskosten nach § 35a EStG schreibt die Ausstellung ebenfalls
fest: Eine spätere Änderung der Organisationsregel oder der Kundenart ändert eine
ausgestellte Rechnung nicht mehr.

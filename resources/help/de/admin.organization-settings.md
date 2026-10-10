---
title: "Organisation und Einstellungen"
topic: admin.organization-settings
version: 3
keywords:
    - Firmeneinstellungen
    - Mandanteneinstellungen
    - Firmendaten ändern
    - Kartendienst
    - Wetterdienst
    - Feiertagskalender
    - Feiertage Bundesland
    - Wartungsmodus
    - Zwei-Faktor-Pflicht
    - Mahnstufen einstellen
    - Geocoding
    - Routenplanung
audience:
    - admin
related:
    - admin.tenants
    - admin.settings
    - admin.license
    - reports.arbzg-compliance
    - catalog.holidays
    - finance.dunning
    - invoices.manage
    - accounting.fixed-assets
    - account.ai-assistant
    - admin.notification-rules
    - dispatch.board
    - tours.manage
---

Im Dialog **Organisation bearbeiten** pflegen Sie die Stammdaten Ihrer
Organisation und alle Einstellungen, die für ihre Mitglieder gelten:
Arbeitszeitregeln und Genehmigungsstufen, Vorgaben für Rechnungen und
Mahnungen, Karten- und Wetterdienste, die Feiertagsregion und den
Wartungsmodus. Sie öffnen ihn im Systemmenü (Zahnrad-Symbol **System** in der
Kopfzeile) unter **Organisation** → **Organisation**. Der Eintrag steht
Administratoren zur Verfügung und öffnet immer die eigene Organisation; der
Plattformbetrieb erreicht denselben Dialog über die Liste **Organisationen**.

## So wirken die Einstellungen

- **Geltung:** Jeder Wert gilt für die ganze Organisation. Wo Mitglieder,
  Kunden, Projekte oder Standorte eigene Werte haben können, nennt der
  jeweilige Abschnitt das; deren Werte gehen dann vor.
- **Standardwerte:** Viele Felder sind leer und zeigen grau „Standard …“,
  etwa „Standard 25“. Ein leeres Feld übernimmt den systemweiten Standard:
  eine Vorgabe, die der Betreiber in den Systemeinstellungen gesetzt hat,
  sonst den eingebauten Wert, den der graue Text nennt. Ein eingetragener
  Wert gilt nur für Ihre Organisation und geht jeder Systemvorgabe vor.
- **Zurücksetzen:** Leeren Sie ein Feld und speichern Sie, entfällt Ihr Wert,
  und der Standard greift wieder.
- **Vorbelegte Felder:** Abschnitte ohne grauen Platzhalter, etwa die
  Arbeitszeit-Grenzwerte oder die Genehmigungsstufen, zeigen den gültigen
  Wert an und speichern ihn beim nächsten Speichern mit.
- **Speichern:** **Speichern** übernimmt alle Abschnitte und Reiter auf
  einmal. Liegt ein Wert außerhalb der erlaubten Spanne, meldet der Dialog das
  am Feld und speichert nichts.
- **Löschen und Deaktivieren:** Deaktivieren, Datenexport und endgültiges
  Löschen einer Organisation sind dem Plattformbetrieb vorbehalten (siehe
  „Organisationen & Mandanten“); der Dialog zeigt dafür keine Schaltflächen.

## Stammdaten

- **Name** (Pflichtfeld, höchstens 255 Zeichen): Name der Organisation. Er
  dient auch als Firmenname der E-Rechnung, solange dort kein eigener steht.
- **Sprache** (Pflichtfeld): Oberflächensprache aller Mitglieder, die keine
  eigene Sprache gewählt haben, und Sprache ihrer Benachrichtigungen und
  Mails. Rechnungen, Angebote, Mahnungen und Lieferscheine erscheinen in
  dieser Sprache, wenn beim Kunden keine eigene Belegsprache hinterlegt ist.
- **Zeitzone** (Pflichtfeld): Anzeigezeitzone für Mitglieder ohne eigene
  Zeitzone. Sie bestimmt auch Tagesgrenzen wie „heute“ und den Wochenbeginn.
- **Datumsformat** und **Uhrzeitformat**: Vorgabe für alle Mitglieder, die im
  Profil kein eigenes Format gewählt haben. Die Auswahl zeigt jedes Format
  mit einem Beispiel; **— Standard —** übernimmt das Systemformat.
- **Vergessene Stempelungen**: wie fehlende Stempelungen nachgetragen werden –
  **Mitarbeiter beantragt – Personalverwaltung genehmigt** (Vorgabe) oder
  **Mitarbeiter darf selbst nachtragen**. Nachträge werden immer als
  „manuell“ gekennzeichnet und bleiben in der Korrektur-Inbox sichtbar.

## Plan & Status

- **Plan**: Tarif der Organisation – **Kostenlos**, **Pro** oder
  **Enterprise**. Er wird hier nur angezeigt: Welche Module freigeschaltet
  sind, ergibt sich aus der installierten Lizenz, und beim Einspielen einer
  Lizenz übernimmt die Organisation deren Plan. Ändern kann ihn nur der
  Plattformbetrieb, denn ein Wechsel auf einen kleineren Plan startet für die
  wegfallenden Module eine Karenzzeit von 30 Tagen, nach der ein nächtlicher
  Lauf die Daten löschbarer Module entfernt.
- **Aktiv**: Ob die Organisation gesperrt ist, schaltet ebenfalls nur der
  Plattformbetrieb um. Mitglieder einer gesperrten Organisation sehen nur
  noch „Diese Organisation ist deaktiviert. Bitte wenden Sie sich an den
  Betreiber.“
- **Sicherheit** – **Zwei-Faktor-Authentifizierung für alle Mitglieder
  verpflichtend**: Wer noch keinen zweiten Faktor eingerichtet hat, wird nach
  der Anmeldung zur Einrichtung geleitet und kann erst danach weiterarbeiten;
  das gilt auch für Zugänge zum Kundenportal. Solange die Pflicht besteht,
  lässt sich der letzte Faktor nicht entfernen und die
  Zwei-Faktor-Authentifizierung nicht abschalten.

## Compliance-Modus

**Modus** legt fest, wie streng die Arbeitszeitprüfungen nach dem
Arbeitszeitgesetz (ArbZG) reagieren:

- **Aus**: keine Prüfungen – weder bei der Dienst- und Schichtplanung noch in
  der ArbZG-Auswertung der erfassten Zeiten und ihrer ungeklärten Fälle.
- **Warnen** (Vorgabe): Verstöße werden angezeigt, gespeichert wird trotzdem.
- **Blockieren**: Eine geplante Schicht mit einem harten Verstoß lässt sich
  nicht speichern, außer die planende Person übersteuert die Prüfung im
  Schichtdialog bewusst. Hart sind Überlappung, unterschrittene
  Mindestruhezeit, überschrittene Tagesarbeitszeit und eine Schicht in
  genehmigtem Urlaub; die übrigen Regeln warnen nur.

## Arbeitszeit-Modell

**Standard-Arbeitszeit-Typ** ist das Arbeitszeit-Modell aller Mitglieder, für
die kein eigenes hinterlegt ist, und die Vorbelegung neuer Modelle:
**Gleitzeit** (Vorgabe), **Feste Wochenarbeitszeit**, **Wochentagsweise** oder
**Vertrauensarbeitszeit**. Ein eigenes Modell je Person geht vor.

## Arbeitszeit-Grenzwerte

Die Grenzwerte gelten für die Dienst- und Schichtplanung und für die
ArbZG-Auswertung der erfassten Zeiten:

- **Max. Stunden/Tag** (1–24, Vorgabe 10): Tagesarbeitszeit ohne Pausen.
- **Min. Ruhezeit (h)** (1–24, Vorgabe 11): Ruhezeit zwischen zwei
  Arbeitstagen bzw. Schichten.
- **Max. Stunden/Woche** (1–168, Vorgabe 48).
- **Max. Tage am Stück** (1–14, Vorgabe 6): aufeinanderfolgende Arbeitstage in
  der Schichtplanung.
- **Nachtzeit ab (Uhr)** (20–23, Vorgabe 23) und **Nachtzeit bis (Uhr)**
  (4–7, Vorgabe 6): Nachtfenster für die Prüfung der Nachtarbeit. Laut ArbZG
  gilt 23 bis 6 Uhr, in Bäckereien und Konditoreien 22 bis 5 Uhr.
- **Bagatellgrenze Rahmenzeit (Min.)** (0–240, Vorgabe 15): Erst wenn
  Stempelzeiten die Rahmenzeit des Arbeitszeit-Modells um mehr als diese
  Minuten überschreiten, entsteht ein ungeklärter Fall.
- **Gleitzeit-Ampel: Gelb ab (Min.)** (Vorgabe 1200, also 20 Stunden) und
  **Gleitzeit-Ampel: Rot ab (Min.)** (Vorgabe 2400, also 40 Stunden): Färbung
  des Gleitzeitsaldos im Arbeitszeitkonto und auf dem Dashboard. Plus- und
  Minusstunden zählen gleich. Liegt der Rot-Wert unter dem Gelb-Wert, gilt
  der Gelb-Wert auch für Rot.

## Genehmigungen

- **Urlaubs-Genehmigungsstufen**, **Überstunden-Genehmigungsstufen** und
  **Zeitkorrektur-Genehmigungsstufen**: je **Einstufig (eine Freigabe)**
  (Vorgabe) oder **Zweistufig (Vier-Augen-Prinzip)** – dann braucht ein Antrag
  zwei Freigaben.
- **Kaufmännisch: zuständige Rolle**, **Fachlich: zuständige Rolle** und
  **HR: zuständige Rolle**: Freigabestufen einer Vertragsverhandlung
  erscheinen im Genehmigungs-Eingang unter „Genehmigungen“ bei der Rolle, die
  ihrer Stufenart zugeordnet ist. Zur Wahl stehen alle Rollen außer Kunde.
  Leer gilt die Vorgabe: **Vorgabe (Buchhaltung)**, **Vorgabe (Teamleitung)**
  bzw. **Vorgabe (Personalverwaltung)**. Die Freigabe direkt an der Akte
  bleibt möglich.
- **Beantragte Fehlzeiten sofort unter Vorbehalt eintragen (Ablehnung nimmt
  sie zurück)**: Beantragte Fehlzeiten wirken schon vor der Genehmigung in der
  Planung, gekennzeichnet als Vorbehalt; eine Ablehnung nimmt sie zurück.
  Abgerechnet und exportiert wird weiterhin nur Genehmigtes.
- **Anwesenheits-Board (Aktuelle Belegung) aktivieren**: schaltet die Seite
  **Aktuelle Belegung** frei (ab Werk aus).

## Lenk- und Ruhezeiten

**Lenkzeitregeln anwenden (Fahrzeuge mit Flag „Lenk- und Ruhezeitregeln
anwenden")** (ab Werk aus) prüft Fahrten gegen die Grenzwerte der VO (EG)
561/2006 bzw. FPersV: Tages- und Wochenlenkzeit, Fahrtunterbrechung sowie
tägliche und wöchentliche Ruhezeit. Geprüft werden nur Fahrten mit
Fahrzeugen, an denen zusätzlich **Lenk- und Ruhezeitregeln anwenden** gesetzt
ist – beide Schalter müssen an sein. Das ist keine Rechtsberatung: Welche
Vorschriften im Einzelfall gelten, klärt der Betrieb.

## Aktive Regeln

Hier schalten Sie einzelne Prüfungen ab; ab Werk sind alle an. Eine
abgeschaltete Regel wird nicht mehr geprüft, der Modus **Aus** schaltet alle
ab. Die ersten acht Regeln betreffen die Schichtplanung:

- **Überlappende Schichten**: Zwei Schichten derselben Person überschneiden
  sich.
- **Mindestruhezeit**: Die Ruhezeit zwischen zwei Schichten ist zu kurz.
- **Tagesarbeitszeit** und **Wochenarbeitszeit**: Der Grenzwert ist
  überschritten.
- **Aufeinanderfolgende Tage**: mehr Arbeitstage am Stück als erlaubt.
- **Urlaubskonflikt**: Die Schicht fällt in beantragten oder genehmigten
  Urlaub.
- **Qualifikations-Match**: Der Person fehlt eine Qualifikation, die der
  Besetzungsbedarf der Schicht verlangt.
- **Feiertagsbuchung**: Die Schicht liegt auf einem gesetzlichen Feiertag der
  Feiertagsregion (Reiter **Region & Feiertage**) oder auf einem eigenen
  Feiertag unter **Feiertage**.

Die letzten vier prüfen die Stempelzeiten und erzeugen ungeklärte Fälle in
der ArbZG-Auswertung:

- **Vergessene Geht-Stempelung**: Eine Anwesenheit ist über den Tag hinaus
  offen.
- **Stempelung an freiem Tag**: gestempelt an einem Tag, der laut
  Arbeitszeit-Modell oder Dienstplan frei ist.
- **Stempelung trotz Abwesenheit**: gestempelt trotz genehmigter
  ganztägiger Abwesenheit wie Urlaub oder Krankheit.
- **Rahmenzeit (Stempelzeiten)**: Stempelzeit außerhalb der Rahmenzeit,
  jenseits der Bagatellgrenze.

## Erweiterte Einstellungen

Der letzte Abschnitt bündelt weitere Vorgaben in Reitern: **Listen**,
**Rechnungen**, **Datei-Uploads**, **Eingabe-Limits**,
**Benachrichtigungen**, **Oberfläche**, **Routing & Karten**, **Anfahrt**,
**Region & Feiertage**, **Wetter** und **Wartung**. Der Reiter **Rechnungen**
enthält außer den Rechnungsvorgaben auch Mahnwesen, E-Rechnung, Anlagen,
Versand und Zoll, Online-Zahlung, KI-Assistenten, Mietbedingungen, Fuhrpark,
Reklamationsmuster, wiederkehrende Probleme und Zeit-Import. Für alle Reiter
gilt der Hinweis „Leer lassen, um den systemweiten Standardwert zu nutzen.“
Die folgenden Abschnitte stehen in der Reihenfolge der Reiter.

## Listen

Wie viele Einträge eine Liste je Seite zeigt, jeweils 1 bis 500:
**Stundenzettel**, **Dienstpläne**, **Kunden** (auch Lieferanten und
Fremdkunden), **Touren**, **Fahrzeuge**, **Tags**, **Archiv** (jeder Reiter
der Archivseite), **Benachrichtigungen, Betriebsaufgaben, Wartungsfenster,
Fehlermeldungen** sowie die drei Listen der Fernwartungs-Inbox
(**Fernwartungs-Inbox: unzugeordnete Geräte**, **Fernwartungs-Inbox:
Mehrkundengeräte**, **Fernwartungs-Inbox: Sitzungen je Gerätekarte**). Wie
viele zuletzt verwendete Einträge das Dashboard zeigt, legt der Reiter
**Oberfläche** fest. Die Listengröße der Organisationsübersicht des
Plattformbetriebs ist eine Systemeinstellung unter **Einstellungen
(Registry)**.

## Rechnungen

- **Standard-Steuersatz (%)**: Leer ermittelt workDiary den Steuersatz von
  Inlandsrechnungen aus den Steuerregeln. Ein eingetragener Satz gilt für
  alle lokal erstellten Inlandsrechnungen und geht den Steuerregeln vor.
- **Standard-Währung (ISO-4217)**: Vorgabewährung der Organisation; die
  Beträge eines Belegs bleiben in der Währung des Kunden.
- **Zeit-Einheit für Positionen** (bis 8 Zeichen, Vorgabe h): Einheit der
  Zeitpositionen in der Übergabe.
- **Standardleistung (Artikel)**: liefert Bezeichnung, Einheit, Standardtext
  und – falls kein Satz auffindbar ist – den Preis der Übergabe-Positionen.
  Abrechnungsregeln am Projekt gehen vor. Ohne Artikel im Artikelstamm bleibt
  die Auswahl leer.
- **Vorlage: Einleitungstext der Übergabe** und **Vorlage: Schlussbemerkung
  der Übergabe** (je bis 2000 Zeichen): werden beim Anlegen einer Übergabe in
  den Nachweis kopiert und sind dort bearbeitbar. Platzhalter: :customer,
  :from, :to, :channel. Ohne Vorlage für die Schlussbemerkung greift der
  Rechnungstext des Kunden.
- **Standard-Stundensatz (Erlös)**: greift, wenn weder Eintrag,
  Kundenkondition, Mitarbeiter, Tätigkeit, Projekt noch Kunde einen Satz
  setzen. Leer bleiben solche Zeiten bei 0,00 €.
- **Kalkulationsstundensatz Montage**: bewertet die Montagezeit eines Artikels
  im Verkaufspreisvorschlag; leer gilt der Standard-Stundensatz.
- **Standard-Taktung (Minuten)** (1–1440): rundet abrechenbare Zeit auf diese
  Taktung auf, wenn weder Projekt noch Kunde eine setzen; leer =
  minutengenau.
- **Standard-Lücke zum Zusammenfassen (Minuten)** (0–1440): Einträge mit
  höchstens dieser Lücke werden beim Abrechnen zu einem Block zusammengefasst;
  leer = keine Zusammenfassung.
- **Fakturierungsweg**: Standard-Fakturierungsweg der Organisation, etwa
  **WorkDiary (lokal)** oder **Lexoffice führt**; Kunden können ihn einzeln
  übersteuern. Leer gilt **— WorkDiary (Standard) —**. Das Feld erscheint nur
  mit dem Recht „Finanzkonfiguration verwalten“.

Wie Rechnungen entstehen, beschreibt das Thema „Rechnungen & Belege“.

## Mahnwesen

Stufen-Vorgaben für Einzelmahnung und Mahnlauf; den Ablauf beschreibt das
Thema „Mahnwesen“.

- Je Stufe 1 bis 3: **Stufe 1: Karenz (Tage)** usw. – bei Stufe 1 die Tage
  Überfälligkeit, bevor die Zahlungserinnerung fällig wird, bei Stufe 2 und 3
  die Tage seit der letzten Mahnung (Vorgabe je 7); **Stufe 1: Gebühr (EUR)**
  usw. (Vorgabe 0,00); **Stufe 1: Zahlungsfrist (Tage)** usw. (Vorgabe 14, 10
  und 7 Tage).
- **Verzugszins berechnen**: **Fester Satz** (Vorgabe) oder **Basiszinssatz +
  Prozentpunkte** – der Basiszinssatz nach § 247 BGB wird monatlich bei der
  Bundesbank abgerufen.
- **Aufschlag (Prozentpunkte)**: nur im Basiszins-Modus. Anhalt nach § 288
  BGB: 5 Prozentpunkte gegenüber Verbrauchern, 9 im Geschäftsverkehr; die
  Höhe legt Ihr Betrieb fest.
- **Verzugszins (% p. a.)**: nur beim festen Satz; 0 = kein Zinsausweis.

Verzugszinsen erscheinen nur im Mahnschreiben, gebucht werden sie nicht.

## E-Rechnung (XRechnung)

Verkäuferdaten für XRechnung-Ausgaben (EN 16931) lokal erstellter
Rechnungen: **Firmenname** (leer = Name der Organisation), **Straße und
Hausnummer**, **PLZ**, **Ort**, **Ländercode (ISO 3166-1)**, **USt-IdNr.**,
**Steuernummer**, **Kontakt: Name**, **Kontakt: E-Mail** (gültige Adresse),
**Kontakt: Telefon**, **IBAN**, **BIC** und **Kontoinhaber**.

Drei Felder wirken darüber hinaus auf alle lokal erstellten Rechnungen:

- **Ländercode (ISO 3166-1)** (zwei Buchstaben, Vorgabe DE): Land des
  Verkäufers. Danach unterscheidet die Steuerermittlung Inlands-, EU- und
  Drittlandsrechnungen.
- **Zahlungsziel (Tage)** (0–365): gilt, wenn weder die Rechnung noch der
  Kunde ein Zahlungsziel haben; leer oder 0 = 14 Tage.
- **Kleinunternehmer (§ 19 UStG)**: Alle Rechnungen, die workDiary erstellt,
  weisen keine Umsatzsteuer aus und tragen den Hinweis „Keine Umsatzsteuer
  gemäß § 19 UStG (Kleinunternehmerregelung).“; die XRechnung erhält die
  Steuerkategorie E (steuerbefreit). Der Haken hat Vorrang vor
  **Standard-Steuersatz (%)** und Reverse Charge.

## Buchhaltung: Vier-Augen-Prinzip

Der Schalter **Vier-Augen-Prinzip** in der Gruppe **Buchhaltung** legt fest, dass eine zweite Person freigibt: Wer eine Buchung oder eine Direktbuchung (Skonto, Ausbuchung, Klärungsbuchung, interne Umbuchung, Startsalden, Sondervorauszahlung) vorbereitet, schreibt sie nicht selbst fest; wer einen SEPA-Zahlungslauf zusammenstellt, gibt ihn nicht selbst frei. Direktbuchungen entstehen dann als Entwurf in der **Buchungs-Inbox** und wirken erst nach der Festschreibung. Ohne den Schalter schreibt WorkDiary sie sofort fest.

## Anlagen: GWG und Sammelposten

Wertgrenzen (netto) für das Anlagenregister. Die Vorgaben entsprechen § 6
Abs. 2/2a EStG (Stand 2026); prüfen Sie sie bei Gesetzesänderungen.

- **GWG-Grenze** (Vorgabe 800): Grenze für die Sofortabschreibung
  geringwertiger Wirtschaftsgüter.
- **Sammelposten ab (über)** (Vorgabe 250), **Sammelposten bis** (Vorgabe
  1000) und **Sammelposten Jahre** (1–20, Vorgabe 5).
- **Preissteigerung für Ersatzprognose (% p. a.)** (0–50, Vorgabe 0).

Einzelheiten stehen im Thema „Anlagenregister und Abschreibung“.

## Versand und Zoll

**EORI-Nummer**: Zollnummer des Unternehmens (Ländercode und bis zu
15 Zeichen, z. B. DE1234567). Sie steht als Absenderangabe auf Handels- und
Proformarechnungen für Sendungen außerhalb der EU.

## Online-Zahlung

- **Zahlungsanbieter**: nur nötig, wenn mehrere Anbieter aktiv sind; Vorgabe
  **Automatisch (erster aktiver Anbieter)**. Die Anbieter (Stripe, Mollie oder
  SumUp) aktivieren Sie als Plugin mit eigenen Zugangsdaten.
- **Zahlungslink auf Rechnung und in der Mail** (ab Werk an): Zahlungslink und
  QR-Code stehen auf der Rechnung und in der Mail. Ausgeschaltet bleibt die
  Online-Zahlung im Kundenportal möglich.

## KI-Assistenten (MCP)

**KI-Assistenten über MCP zulassen** (ab Werk aus): KI-Assistenten wie Claude
oder ChatGPT können sich mit Zustimmung einzelner Nutzer anbinden und mit
deren Rechten lesen oder Entwürfe anlegen. Ausgeschaltet ist keine neue
Anbindung möglich; bestehende Zugänge liefern keine Werkzeuge und lassen sich
nicht erneuern. Die Anbindung selbst beschreibt „KI-Assistent verbinden“.

## Mietbedingungen im Geräteverleih

- **Übergabe nur mit unterschriebenen Mietbedingungen**: Die Mietbedingungen
  führen Sie als Kundenvereinbarung „Mietbedingungen (Geräteverleih)“ mit
  Fassung und Unterschrift.
- **Direktbuchung im Kundenportal erlauben**: Kunden reservieren freie, fürs
  Portal freigegebene Geräte sofort verbindlich; die Leitung wird
  benachrichtigt.
- **Radius um den Einsatzort (m)** (50–50 000, Vorgabe 500): Liegt die
  gemeldete Position eines verliehenen Geräts weiter vom Standort des
  Verleihs entfernt, wird die Abweichung gemeldet. Ohne Standort gelten die
  Geofences des Kunden.

## Fuhrpark

**Keine neue Fahrt bei überfälliger Pflichtprüfung** (ab Werk aus): Ist die
HU, UVV oder eine andere Pflichtprüfung des zugeordneten Assets überfällig
oder gesperrt, lässt sich ab heute keine Fahrt mehr erfassen. Vergangene
Fahrten bleiben dokumentierbar.

## Reklamationsmuster

Ab wie vielen gleichartigen Reklamationen ein Hinweis entsteht – gleiche
Charge, gleicher Artikel mit gleicher Mangelart oder Ursache oder gleicher
Lieferant: **Schwelle (Fälle)** (2–50, Vorgabe 3) innerhalb von
**Zeitfenster (Tage)** (7–365, Vorgabe 90).

## Wiederkehrende Probleme

Diese Frühwarnung findet Kunden und Objekte, zu denen im gewählten Zeitraum
auffällig viele Helpdesk-Tickets eingehen.

- **Tickets ab** (2–50, Vorgabe 3): Mindestzahl an Tickets für eine Warnung.
- **Zeitfenster (Tage)** (7–365, Vorgabe 90): betrachteter Zeitraum bis heute,
  gemessen am Meldedatum der Tickets.

So zählt workDiary:

- Es zählen alle Tickets mit Kunde, unabhängig von ihrem Status. Tickets mit
  Objekt zählen je Kunde und Objekt, Tickets ohne Objekt je Kunde.
- Erreicht ein Kunde oder ein Objekt die Schwelle, entsteht die Warnung
  „Wiederkehrende Tickets: …“ mit Anzahl, Zeitraum, einem Link auf Objekt bzw.
  Kunde und der Empfehlung, die Ursache mit dem Kunden zu klären, das Objekt
  zu prüfen oder auszutauschen und eine Arbeitsanweisung oder Schulung zu
  erwägen. Gezeigt werden höchstens die 20 Fälle mit den meisten Tickets.
- Die Warnungen erscheinen unter **Auswertungen** → **Projekte & Kunden** →
  **Probleme & Schulung** im Bereich **Wiederkehrende Probleme** (für
  Administratoren und Personen mit dem Recht „Auswertungen einsehen“) und in
  der Dashboard-Kachel **Auffälligkeiten**.
- Zusätzlich geht je Kunde bzw. Objekt einmal die Benachrichtigung
  „Frühwarnung aus den Auswertungen“ an Teamleitung und Administrator.
  Empfänger und Kanäle ändern Sie unter **Benachrichtigungsregeln**.

## Zeit-Import

**Zeiten anhand von Schlüsselwörtern dem Projekt zuordnen** (ab Werk an):
gilt für importierte Zeiten, etwa aus Fernwartung, Toggl oder Kimai. Enthält
der Text einer importierten Zeit den Namen oder ein Schlüsselwort eines
Projekts desselben Kunden, wird sie dort gebucht statt im Standardprojekt
bzw. in der Zuordnungs-Inbox. Gebucht werden nur eindeutige Treffer.

## Datei-Uploads

Größenlimits für Uploads in Kilobyte (1 bis 1 048 576 KB, also bis 1 GB):
**CSV-Import** (Vorgabe 10 240 KB, 10 MB), **Kundenanhang** (10 240 KB),
**Anhänge (allgemein)** (25 600 KB, 25 MB) und **Druckdaten** (262 144 KB,
256 MB). Größere Dateien werden beim Hochladen abgewiesen.

## Eingabe-Limits

Zeichen- und Bereichsgrenzen für Formularfelder, jeweils ab 1:

- **Zeiterfassung**: **Notiz, max. Zeichen** (Vorgabe 1000), **Geräte-ID, max.
  Zeichen** (64) und **Pause, max. Minuten** (600).
- **Tags**: **Tag-Name, max. Zeichen** (60).
- **Kommentare**: **Kommentartext, max. Zeichen** (5000).
- **Dienstpläne**: **Notiz, max. Zeichen** (2000).

## Benachrichtigungen

**Nachrichten-Vorschau, max. Zeichen** (20–500, Vorgabe 120): So viele Zeichen
des Nachrichtentexts zeigt eine Push-Benachrichtigung; der Rest wird
abgeschnitten.

## Oberfläche

- **Kalender** – **Slot-Länge in Minuten**: Raster der Wochenansicht; Termine
  ohne Ende erhalten diese Länge. Erlaubt sind 10, 15, 20, 30 oder 60
  (Vorgabe 30).
- **Dashboard** – **Anzahl letzter Einträge** (Vorgabe 5): wie viele zuletzt
  verwendete Einträge das Dashboard zeigt.

## Nominatim (Geocoding)

Nominatim ist ein Geocoding-Dienst auf Basis von OpenStreetMap: Er wandelt
eine Anschrift in Koordinaten um. workDiary nutzt ihn im **Fahrtenbuch**:
Verlassen Sie das Feld **Von (Adresse)** oder **Nach (Adresse)**, sucht
workDiary die Anschrift, und die gefundene Adresse erscheint als Tooltip am
Feld. Die eingegebene Anschrift geht dabei an den eingetragenen Dienst.

- **Basis-URL** (vollständige Adresse, bis 255 Zeichen): Adresse des
  Nominatim-Dienstes. Leer gilt die Vorgabe des Betreibers. Liegt eine
  eigene Adresse im internen Netz, etwa ein selbst betriebener Server, fragt
  workDiary sie nur ab, wenn der Betreiber das für Ihre Organisation
  freigegeben hat.
- **Kontakt-E-Mail**: wird bei jeder Anfrage mitgeschickt. Die
  Nutzungsregeln von Nominatim verlangen, dass sich die abfragende Anwendung
  mit einer Kontaktadresse ausweist.
- **Anfragen pro Sekunde** (1–50, Vorgabe 1): workDiary wartet zwischen zwei
  Anfragen entsprechend. Der öffentliche Nominatim-Dienst erlaubt höchstens
  eine Anfrage pro Sekunde; höhere Werte sind nur für einen eigenen Server
  gedacht.
- Ergebnisse werden je Dienstadresse zwischengespeichert (ab Werk 365 Tage);
  dieselbe Anschrift wird in dieser Zeit nicht erneut abgefragt.
- Ist der Dienst nicht erreichbar oder findet nichts, erscheint kein Tooltip;
  die Eingabe selbst bleibt davon unberührt.

## OSRM (Routing)

OSRM berechnet Fahrstrecken auf dem Straßennetz. workDiary nutzt ihn

- beim Optimieren einer Tour: Reihenfolge der Stopps nach echten
  Straßenentfernungen, Streckenverlauf auf der Karte sowie geplante Strecke
  und Fahrzeit, zu der die Aufenthaltszeiten der Stopps hinzukommen;
- bei den **Leerzeit-Vorschlägen** der **Leitstelle**: zusätzliche Fahrzeit
  hin und zurück für einen Auftrag, der in ein freies Zeitfenster passen
  würde.

Ist OSRM nicht erreichbar, rechnet workDiary mit der Luftlinie weiter: Touren
lassen sich weiter planen, nur ohne Streckenverlauf, und die Vorschläge
tragen den Hinweis **grobe Schätzung (Luftlinie)**.

- **Basis-URL** (vollständige Adresse, bis 255 Zeichen): Adresse des
  OSRM-Servers. Für eine eigene Adresse im internen Netz gilt dieselbe
  Freigabe durch den Betreiber wie bei Nominatim.
- **Profil (z. B. driving)** (bis 32 Zeichen, Vorgabe driving): Fahrprofil des
  Servers. Welche Profile es gibt, etwa für Fahrrad oder Fußweg, bestimmt der
  OSRM-Server.
- **Timeout (Sekunden)** (1–120, Vorgabe 10): So lange wartet workDiary auf
  eine Antwort, bevor es auf die Luftlinie zurückfällt.

## Kartenkacheln

Kartenkacheln sind die Bildausschnitte, aus denen die Karten in workDiary
bestehen, etwa bei **Touren**, auf der Karte der **Leitstelle** und im
Krisenmanagement. Der Browser jedes Nutzers lädt sie direkt vom
eingetragenen Kachelserver.

- **Tile-URL-Vorlage** (vollständige Adresse, bis 255 Zeichen): Adresse des
  Kachelservers mit den Platzhaltern {z} für die Zoomstufe sowie {x} und {y}
  für die Kachelposition. Vorgabe ist der Kachelserver von OpenStreetMap.
  workDiary erlaubt dem Browser automatisch, Bilder von diesem Server zu
  laden.
- **Maximaler Zoom** (1–22, Vorgabe 19): stärkste Vergrößerung der Karten.
  Wählen Sie höchstens die Stufe, die der Kachelserver liefert.
- Den Quellenvermerk unten auf der Karte legt die Grundkonfiguration des
  Betreibers fest; hier lässt er sich nicht ändern.

## Anfahrt-Abrechnung

Im Reiter **Anfahrt** legen Sie fest, ob Rechnungen eine Anfahrt enthalten.
Mit **Anfahrt automatisch berechnen** (ab Werk aus) setzt workDiary bei der
Projekt- oder Materialabrechnung eines Kunden eine Anfahrtsposition für jede
Tour mit Stopp bei diesem Kunden; die Position trägt das Datum der Tour. Abgesagte Touren
und bereits abgerechnete Anfahrten zählen nicht.

- **Modus**: **Pauschale** oder **Kilometer**.
- **Positionstext** (bis 50 Zeichen, Vorgabe „Anfahrt“ in der Sprache der
  Abrechnung): Text der Rechnungsposition, ergänzt um Datum bzw. Kilometer.
- **Pauschale (netto €)**: Betrag je Anfahrt im Modus **Pauschale**; ohne
  Betrag entsteht keine Position.
- **Satz (€/km)**: Preis je Kilometer im Modus **Kilometer**.
- **Kilometer-Quelle**: **Immer vom Firmenstandort** – Luftlinie vom
  Firmenstandort zur Kundenanschrift – oder **Je nach Tour (tatsächliche
  km)** – die Kilometer aus dem Fahrtenbuch für diesen Kunden an diesem Tag,
  sonst die geplante Tourstrecke.
- **Hin- und Rückfahrt (×2, nur Firmenstandort)**: verdoppelt die Luftlinie.
- **Firmenstandort Breite (lat)** und **Firmenstandort Länge (lng)**:
  Koordinaten des Firmenstandorts; leer gilt der Startpunkt der Tour.

Fehlen im Kilometer-Modus die Koordinaten des Kunden, entsteht keine
Position. Für einzelne Kunden übersteuern Sie die Werte im Kundendialog unter
**Anfahrt (Übersteuerung)**.

## Rechtsraum & Feiertage

**Feiertagsregion (Land / Bundesland)** legt fest, welche gesetzlichen
Feiertage für Ihre Organisation gelten. Zur Wahl stehen Deutschland mit
**Bundesweit (ohne regionale Feiertage)** und allen 16 Bundesländern,
**Österreich (bundesweit)** sowie **Schweiz (landesweit)** und alle 26
Kantone. Regionale Feiertage wie Fronleichnam oder Reformationstag gelten nur
in bestimmten Bundesländern – wählen Sie deshalb die Region Ihres Betriebs.
Leer gilt die systemweite Vorgabe, die der erste Eintrag der Liste als
„Standard …“ nennt.

Die Region wirkt überall, wo workDiary Feiertage berücksichtigt, unter
anderem bei:

- Feiertagszuschlägen und Kundenkonditionen mit Feiertagsregel;
- Arbeitstagen von Urlaub und Krankheit, Urlaubskonto und Gleitzeit-Soll;
- der ArbZG-Auswertung, etwa bei Arbeit an Feiertagen, und der
  Dienstplan-Regel **Feiertagsbuchung**;
- den Ansichten Kalender, Wochenansicht, Dienstplan, Abwesenheitskalender
  und **Aktuelle Belegung**;
- SLA-Fristen im Helpdesk und Abgabefristen von Steuermeldungen, die auf den
  nächsten Werktag rücken;
- Feiertagspreisen im Geräteverleih.

Eigene Feiertage oder Ruhetage pflegen Sie unter **Feiertage**; sie gelten
zusätzlich zur Region. Ein Standort kann unter **Standorte** im Feld
**Feiertags-Region** abweichen (Vorgabe **Feiertagsregelung der
Organisation**); das gilt für die Zuschläge der dort erfassten Zeiten.

## Wetter-Auto-Abruf

**Wetter bei Protokoll-Anlage automatisch abrufen** (ab Werk aus): Beim
Anlegen eines Protokolls holt workDiary im Hintergrund einen Wetter-Snapshot
für Ort und Zeitpunkt des Protokolls und hängt ihn als Beweiswert an.

- Ort sind die Koordinaten des Standorts, um den es im Protokoll geht, sonst
  die des Kunden – auch über das Projekt oder den Auftrag des Protokolls.
  Ohne Koordinaten passiert nichts.
- Projekte können abweichen: Im Projekt wählen Sie unter
  **Wetter-Auto-Abruf** → **Automatischer Wetter-Abruf** an, aus oder **Erben
  (Org-Einstellung)**. Die Wahl gilt auch für Unterprojekte.
- **Wetterdienst**: **Open-Meteo** (Vorgabe) arbeitet weltweit und ohne
  Anmeldung. **Deutscher Wetterdienst (DWD)** liefert amtliche deutsche
  Stationsdaten (Lizenz CC BY 4.0, Quellenvermerk „Deutscher Wetterdienst“),
  nur für Standorte in Deutschland mit einer Station in Reichweite.
- **DWD: maximale Stationsentfernung (km)** (1–200, Vorgabe 30): Liegt keine
  aktive DWD-Station innerhalb dieser Entfernung, entsteht kein Snapshot –
  lieber kein Wert als ein falscher.

## Wetterwarnungen für die Disposition

**Wetterwarnungen für disponierte Einsätze** (ab Werk an): workDiary prüft
stündlich die Tagesvorhersage für die Einsätze der nächsten drei Tage, heute
eingeschlossen, und meldet, wenn eine Schwelle gerissen wird.

- Geprüft werden Aufträge mit Termin in diesem Zeitraum, die jemandem
  zugewiesen oder eingeplant und weder erledigt noch storniert sind. Die
  Koordinaten stammen aus der Auftragsadresse, sonst vom Kunden; ohne
  Koordinaten keine Prüfung.
- Vorhersagen liefert nur **Open-Meteo**. Ist als **Wetterdienst** der DWD
  gewählt, entstehen keine Warnungen.
- Schwellen (leer = Vorgabe):
  - **Regen (mm/Tag)** – Vorgabe 20; warnt ab dieser Tagessumme.
  - **Böen (km/h)** – Vorgabe 60.
  - **Frost ab Tiefstwert (°C)** – Vorgabe 0; warnt, wenn der Tiefstwert diese
    Temperatur erreicht oder unterschreitet.
  - **Hitze ab Höchstwert (°C)** – Vorgabe 30.
- Jede Überschreitung meldet workDiary genau einmal je Einsatz, Tag und
  Schwelle – ab Werk an die zugewiesene Person und die Teamleitung. Empfänger und
  Kanäle legen Sie unter **Benachrichtigungsregeln** beim Ereignis
  „Wetterwarnung für Einsatz“ fest; für dieses Ereignis ist auch SMS möglich.
  Ist das Ereignis dort abgeschaltet, ruft workDiary keine Vorhersagen ab.

## Wartungsmodus

Im Reiter **Wartung** sperren Sie workDiary vorübergehend für Ihre
Organisation.

- **Wartungsmodus aktivieren**: Alle Mitglieder, die keine Administratoren
  sind, sehen statt der Anwendung eine Wartungsseite; An- und Abmelden bleibt
  möglich. Administratoren arbeiten weiter und sehen oben den Hinweis
  „Wartungsmodus aktiv — Nicht-Administratoren sehen derzeit eine
  Wartungsseite.“ mit dem Link **Einstellungen** zurück in diesen Dialog.
- **Hinweistext für die Wartungsseite** (bis 300 Zeichen).
- **Voraussichtliches Ende** (optional, in Ihrer Ortszeit): Nach diesem
  Zeitpunkt endet der Wartungsmodus automatisch; der Hinweis zeigt ihn als
  „Bis: …“, die Wartungsseite als „Voraussichtlich wieder verfügbar: …“.
- **Auch Terminal-/Webhook-Eingänge pausieren** (ab Werk aus): Ohne diesen
  Haken laufen Stempelterminals sowie Telefonie- und Standort-Eingänge während
  der Wartung weiter.

Das Ein- und Ausschalten des Wartungsmodus wird im Audit-Protokoll
festgehalten.

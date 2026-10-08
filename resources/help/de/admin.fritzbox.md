---
title: "FRITZ!Box-Anrufliste importieren"
topic: admin.fritzbox
version: 1
keywords:
    - FRITZ!Box
    - Anrufliste
    - Telefonate buchen
    - Anrufe als Zeit erfassen
    - Telefonbericht
    - Telefonstempeln
    - Stempeln per Anruf
    - CSV-Import Anrufe
    - AVM
    - Rufnummer zuordnen
    - Telefonzeit abrechnen
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - time-entries.edit
    - attendance.manage
    - contacts.manage
    - foreign-customers
---

Die Seite **FRITZ!Box-Import** übernimmt Telefonate aus der Anrufliste einer
FRITZ!Box als Zeiteinträge. Anrufe bekannter Kunden und Endkunden bucht
WorkDiary selbst; überschneidet sich ein Anruf mit einer bereits gebuchten
Zeit desselben Kunden, etwa einer Fernwartung, wird er damit verschmolzen
statt doppelt abgerechnet. Unbekannte Nummern landen gesammelt in der
**Zuordnungs-Inbox**. Zusätzlich können Mitarbeitende per Anruf auf eine
eigene Rufnummer kommen und gehen stempeln.

WorkDiary verbindet sich dafür nicht mit der FRITZ!Box. Es liest die
exportierte Anrufliste – als hochgeladene Datei oder als Telefonbericht per
E-Mail. Zugangsdaten zur Box brauchen Sie nicht.

## Voraussetzungen

- Das Plugin **FRITZ!Box-Anrufliste** ist unter **Plugins** aktiviert. Danach
  erscheint im Systemmenü (Zahnrad-Symbol **System**) in der Gruppe
  **Plugins** der Eintrag **FRITZ!Box-Import**.
- Die Rufnummern Ihrer Kunden und Endkunden sind in deren Stammdaten
  hinterlegt (Telefon oder Mobil). Darüber erkennt WorkDiary den Anrufer.
- Die Seite steht Administratoren Ihrer Organisation offen; die
  **Zuordnungs-Inbox** Personen, die die Abrechnung verwalten dürfen.

## Einstellungen des Plugins

Unter **Plugins** öffnen Sie bei **FRITZ!Box-Anrufliste** den Dialog
**Konfigurieren**:

- **Telefonate abrechenbar buchen** (Standard: an): Ausgeschaltet werden
  importierte Telefonate nie als abrechenbar markiert.
- **Zeiten buchen für Benutzer-ID**: die Kennung (ID) des Benutzers, dem die
  Telefonate gebucht werden. Leer bucht WorkDiary auf den Inhaber der
  Organisation bzw. den ersten Benutzer.
- **Mindestdauer (Minuten)** (Standard: 2): Kürzere Gespräche werden
  übersprungen.
- **Vorlauf-Fenster (Minuten)** (Standard: 15): Endet ein Anruf höchstens so
  viele Minuten vor einer gebuchten Zeit desselben Kunden, wird er mit ihr
  verschmolzen.
- **Nur eigene Rufnummern**: kommagetrennte Liste Ihrer eigenen Nummern, deren
  Anrufe importiert werden sollen, zum Beispiel nur die Firmenleitung. Leer
  importiert alle. Tragen Sie die Nummern genau so ein, wie sie in der Spalte
  „Eigene Rufnummer“ der Anrufliste stehen.
- **Typ 3 als ausgehend werten**: nur für Listen älterer FRITZ!OS-Versionen,
  die ausgehende Anrufe als Typ 3 exportieren.
- **Externe Kontakte abgleichen** (Standard: an): Unbekannte Nummern werden
  zusätzlich mit verbundenen Kontaktverzeichnissen wie Lexoffice und
  Microsoft 365 abgeglichen.
- **Stempel-Rufnummer: Kommen**, **Stempel-Rufnummer: Gehen** und
  **Stempel-Rufnummer: Kommen/Gehen**: eigene Rufnummern für das
  Telefonstempeln (siehe unten).

## Anrufliste hochladen

1. Exportieren Sie in der FRITZ!Box die Anrufliste: FRITZ!Box → Telefonie →
   Anrufe → Sichern (CSV).
2. Wählen Sie auf der Seite im Abschnitt **Anrufliste hochladen** die Datei
   aus (Endung .csv oder .txt, höchstens 20 MB) und klicken Sie auf
   **Importieren**.
3. Eine Meldung fasst das Ergebnis zusammen: gebucht, verschmolzen,
   gestempelt, offen (Inbox), übersprungen, ausgefiltert und gesperrt.

Dieselbe Liste dürfen Sie gefahrlos erneut hochladen: Bereits importierte
Anrufe überspringt WorkDiary.

Der Kasten **Kontaktabgleich** zeigt, welche externen Kontaktquellen gerade
verbunden sind. Ohne externe Quelle gleicht WorkDiary weiterhin mit Ihren
Kunden und Endkunden ab.

## Telefonbericht per E-Mail

Statt die Datei hochzuladen, kann die FRITZ!Box ihre Anrufliste als
Telefonbericht per E-Mail verschicken. Richten Sie dafür unter
**E-Mail-Eingang** ein Postfach ein, das diese Mails empfängt, und schalten
Sie dort **Telefonbericht-Postfach: FRITZ!Box-Anruflisten (CSV) in den
Anruflisten-Import übernehmen** ein. Der E-Mail-Eingang ruft Postfächer
standardmäßig alle fünf Minuten ab; erkannte Anruflisten gehen in denselben
Import wie ein Upload. Doppelt zugestellte Berichte führen nicht zu doppelten
Buchungen. Ist ein solches Postfach verbunden, meldet der Health-Check des
Plugins „Bereit — Telefonbericht-Mail-Intake verbunden.“

## Was mit jedem Anruf geschieht

- **Ausgefiltert:** Anrufe mit unterdrückter Nummer, verpasste und abgewiesene
  Anrufe, Anrufe über eigene Nummern außerhalb von **Nur eigene Rufnummern**
  sowie Nummern, die in der Inbox ignoriert wurden.
- **Übersprungen:** bereits importierte Anrufe und Gespräche unter der
  Mindestdauer. Senken Sie die Mindestdauer, holt ein erneuter Import solche
  Anrufe nach.
- **Verschmolzen:** Bei bekannter Nummer sucht WorkDiary eine gebuchte Zeit
  desselben Benutzers für denselben Kunden, die der Anruf überschneidet oder
  die höchstens im Vorlauf-Fenster nach dem Anruf beginnt. Der Anruf wird
  dieser Zeit als Nachweis angehängt, und ihr Beginn rückt auf den
  Anrufbeginn vor.
- **Gebucht:** Gibt es keine solche Zeit, entsteht ein eigener Zeiteintrag
  auf dem Standardprojekt des Kunden bzw. Endkunden (es wird bei Bedarf
  angelegt). Die Beschreibung nennt Richtung, Name und Nummer; abrechenbar ist
  der Eintrag laut Einstellung.
- **Gesperrt:** Fällt der Anruf in einen abgeschlossenen Monat, legt WorkDiary
  keinen Eintrag an. Bereits exportierte Zeiten werden nie verändert.
- **Offen (Inbox):** Unbekannte Nummern und als geteilt markierte Nummern
  kommen in die **Zuordnungs-Inbox**.

Bei der Erkennung haben gemerkte Nummern Vorrang, danach folgen die
Stammdaten; passt eine Nummer zu einem Endkunden, gewinnt der Endkunde als
genaueres Ziel. Ist eine Nummer in einem verbundenen Kontaktverzeichnis
bereits einem Kunden zugeordnet, bucht WorkDiary direkt.

## Unbekannte Rufnummern zuordnen

Der Abschnitt **Zuordnungs-Inbox** nennt die Zahl offener Import-Gruppen;
**Zur Inbox** führt dorthin. Die Anrufe einer Nummer stehen dort als Gruppe,
oft schon mit einem vorgeschlagenen Kunden:

- Wählen Sie einen Kunden oder Endkunden und klicken Sie auf **Zuordnen &
  buchen**. Alle Anrufe der Gruppe werden nach denselben Regeln gebucht wie
  beim Import. Mit **Nummer dauerhaft merken** (vorausgewählt) laufen künftige
  Anrufe dieser Nummer ohne Rückfrage durch.
- **Geteilte Nummer** ist für Nummern gedacht, über die mehrere Kunden
  anrufen, etwa eine Dienstleister-Hotline. Künftige Anrufe dieser Nummer
  landen einzeln zur Zuordnung in der Inbox und werden nie automatisch
  gebucht.
- **Nummer ignorieren** filtert die Nummer dauerhaft aus, etwa private
  Anrufe; künftige Anrufe werden nicht mehr importiert.
- **Gruppe verwerfen** verwirft nur die angezeigten Anrufe. Sie kommen auch
  bei einem erneuten Import nicht zurück; neue Anrufe der Nummer erscheinen
  wieder.

## Telefonstempeln

So stempeln Mitarbeitende per Anruf:

1. Tragen Sie in den Plugin-Einstellungen eine oder mehrere eigene Rufnummern
   als **Stempel-Rufnummer: Kommen**, **Stempel-Rufnummer: Gehen** oder
   **Stempel-Rufnummer: Kommen/Gehen** ein – genau so, wie sie in der
   Anrufliste stehen. Danach nennt der Abschnitt **Telefonstempeln** die
   aktiven Stempel-Rufnummern.
2. Ordnen Sie im Abschnitt **Telefonstempeln** jedem Mitarbeitenden seine
   Rufnummer zu: **Mitarbeiter** wählen, **Rufnummer** eintragen (zum Beispiel
   +49 151 2345678), **Zuordnen**. Ohne Ländervorwahl gilt Deutschland. Die
   Tabelle zeigt alle Zuordnungen; **Entfernen** löst eine.
3. Der Mitarbeitende ruft die Stempel-Rufnummer an. Der Anruf muss nicht
   angenommen werden – die Nummer des Anrufers dient als Ausweis.
4. Beim nächsten Import der Anrufliste wird aus dem Anruf ein Kommen- oder
   Gehen-Stempel zur Anrufzeit. Bei **Kommen/Gehen** gilt: Ist ein Stempel
   offen, wird gegangen, sonst gekommen.

Grenzen: Gestempelt wird erst beim Import, nicht im Moment des Anrufs.
Ausgehende Anrufe, unterdrückte und nicht zugeordnete Nummern werden ignoriert,
ebenso ein Gehen ohne offenes Kommen. Die Mindestdauer gilt hier nicht. Anrufe
auf eine Stempel-Rufnummer werden nie als Telefonat gebucht.

## Typische Fehlerbilder

- **Datei wird abgelehnt:** Meldet der Import, dass keine FRITZ!Box-Anrufliste
  erkannt wurde (leere Datei oder fehlende Kopfzeile), verwenden Sie den
  CSV-Export der Anrufliste unverändert.
- **„Kein buchbarer Benutzer in der Organisation.“** bzw. ein Health-Check, der
  meldet, der konfigurierte Standard-Benutzer existiere nicht mehr: Prüfen Sie
  **Zeiten buchen für Benutzer-ID** oder leeren Sie das Feld.
- **Ausgehende Anrufe fehlen:** Stammt die Liste von einer älteren Firmware,
  schalten Sie **Typ 3 als ausgehend werten** ein.
- **Fast alles ausgefiltert:** Prüfen Sie **Nur eigene Rufnummern** – die
  Schreibweise muss exakt der Anrufliste entsprechen.
- **Viele Gesperrt-Treffer:** Der Monat ist bereits abgeschlossen; Anrufe in
  diesem Zeitraum werden nicht mehr gebucht.
- **Stempel fehlt:** Ist die Rufnummer des Mitarbeitenden zugeordnet und wurde
  sie beim Anruf übertragen? Stimmt die Stempel-Rufnummer in den Einstellungen
  mit der Anrufliste überein?

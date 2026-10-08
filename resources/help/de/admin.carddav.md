---
title: "CardDAV-Adressbuch anbinden"
topic: admin.carddav
version: 1
keywords:
    - CardDAV
    - Adressbuch anbinden
    - Nextcloud Kontakte
    - Radicale
    - Baïkal
    - Kontakte importieren
    - Kontakte abgleichen
    - App-Passwort
    - vCard
    - Kontaktabgleich
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - contacts.manage
    - admin.scheduler
---

Die Seite **CardDAV** verbindet WorkDiary mit einem Adressbuch auf einem
eigenen CardDAV-Server, etwa Nextcloud, Radicale oder Baïkal. WorkDiary liest
die Kontakte nur und schlägt sie Ihren Kunden zur Zuordnung vor. Es schreibt
nichts in das Adressbuch zurück, führt keine Datensätze selbsttätig zusammen
und legt keine Kunden an. Ein Microsoft- oder Google-Konto brauchen Sie dafür
nicht.

## Voraussetzungen

- Das Plugin **CardDAV** ist unter **Plugins** für Ihre Organisation
  aktiviert. Danach erscheint im Systemmenü (Zahnrad-Symbol **System**) in der
  Gruppe **Plugins** der Eintrag **CardDAV**.
- Sie kennen die Adresse des CardDAV-Servers, einen Benutzernamen und ein
  Passwort. Ist am Server die Anmeldung in zwei Schritten aktiv (bei Nextcloud
  üblich), brauchen Sie ein App-Passwort, das Sie in Ihrem Konto auf dem
  Server erzeugen.
- Die Seite steht Administratoren Ihrer Organisation offen. Je Organisation
  gibt es genau eine CardDAV-Anbindung.

## Anbindung einrichten

1. Füllen Sie im Abschnitt **Anbindung** die Felder aus:
   - **Bezeichnung**: ein Name für die Anbindung, etwa „Nextcloud Büro“.
   - **DAV-Basis-URL**: bei Nextcloud die Adresse bis einschließlich
     /remote.php/dav, bei Radicale und Baïkal die Wurzel des Servers. Die
     Adresse muss mit http:// oder https:// beginnen.
   - **Benutzername** und **App-Passwort**. Das Passwort wird verschlüsselt
     gespeichert und nie wieder angezeigt. Lassen Sie das Feld bei einer
     bestehenden Anbindung leer, gilt das gespeicherte Passwort weiter.
   - **Private/interne Adressen erlauben**: nur einschalten, wenn der Server
     in Ihrem eigenen Netz steht (zum Beispiel 192.168.x.x). Ohne diesen
     Schalter lehnt WorkDiary interne Adressen ab. Das Einschalten wird
     protokolliert.
   - **Aktiv**: schaltet die Anbindung ein oder aus.
2. Klicken Sie auf **Speichern**.
3. Klicken Sie oben auf **Adressbücher suchen**. WorkDiary fragt den Server ab
   und listet die gefundenen Adressbücher im Abschnitt **Adressbuch**.
4. Wählen Sie ein Adressbuch aus und klicken Sie auf **Adressbuch
   übernehmen**. Es ist ab jetzt die Sync-Quelle; die Seite zeigt es als
   „Aktuelle Sync-Quelle“.

Wählbar sind nur Adressbücher aus der letzten Suche, die auf demselben Server
liegen wie die Basis-URL. Eine beliebige Adresse lässt sich nicht als Quelle
eintragen.

## Was abgeglichen wird und wann

- **Richtung:** nur vom CardDAV-Server zu WorkDiary.
- **Inhalt:** Name, Firma, E-Mail-Adresse, Telefon-, Mobil- und Faxnummer,
  Notiz sowie die Anschrift mit Land. Gibt es mehrere E-Mail-Adressen oder
  Nummern, bevorzugt WorkDiary die als geschäftlich markierten.
- **Zeitpunkt:** Der Abgleich läuft automatisch stündlich. Den Takt ändern Sie
  unter **Geplante Aufgaben**. Mit **Jetzt synchronisieren** stoßen Sie ihn
  sofort an; er läuft dann im Hintergrund, und die Seite zeigt danach
  „Zuletzt synchronisiert …“.
- **Nur Änderungen:** Unveränderte Kontakte überspringt WorkDiary. Verarbeitet
  werden nur neue und geänderte Karten.

## Zuordnung zu Kunden

- Passt ein Kontakt eindeutig zu genau einem Kunden, verknüpft WorkDiary
  beide. Ändert sich ein verknüpfter Kontakt später und weichen seine Angaben
  vom Kunden ab, entsteht ein Feld-Konflikt in der **Zuordnungs-Inbox** –
  Kundendaten werden nie still überschrieben.
- Alle übrigen Kontakte (kein passender Kunde oder mehrere Kandidaten) landen
  als Vorschlag in der **Zuordnungs-Inbox**. Dort ordnen Sie den Kontakt einem
  Kunden zu, legen ihn neu an oder verwerfen ihn.
- Wird ein Kontakt im Adressbuch gelöscht, verwirft WorkDiary dessen noch
  offenen Vorschlag. Bereits getroffene Zuordnungen bleiben bestehen.
- Die **Zuordnungs-Inbox** steht Personen offen, die die Abrechnung verwalten
  dürfen.

## Ändern, trennen, neu beginnen

- Ändern Sie die **DAV-Basis-URL**, verwirft WorkDiary das gewählte
  Adressbuch und den bisherigen Sync-Stand. Suchen und übernehmen Sie das
  Adressbuch danach erneut.
- Übernehmen Sie ein anderes Adressbuch, beginnt der Abgleich von vorn.
- **Trennen** schaltet die Anbindung inaktiv. Bereits erzeugte Vorschläge
  bleiben erhalten. Zum Fortsetzen schalten Sie **Aktiv** wieder ein und
  klicken auf **Speichern**.

## Typische Fehlerbilder

- **Interne Adresse abgelehnt:** Die Meldung „Die Basis-URL zeigt auf eine
  private/interne Adresse“ erscheint, wenn der Server im eigenen Netz steht.
  Schalten Sie **Private/interne Adressen erlauben** ein.
- **Suche schlägt fehl:** „Adressbuch-Suche fehlgeschlagen“ bedeutet, dass der
  Server nicht erreichbar ist oder die Zugangsdaten nicht stimmen. Prüfen Sie
  Basis-URL, Benutzername und App-Passwort.
- **Keine Adressbücher:** „Auf dem Server wurden keine Adressbücher gefunden“ –
  das Konto hat kein Adressbuch, oder die Basis-URL zeigt auf die falsche
  Ebene.
- **Fremdes Adressbuch:** „Die Adresse gehört nicht zum eingerichteten
  CardDAV-Server“ – das gewählte Adressbuch liegt auf einem anderen Server als
  die Basis-URL.
- **Kein Abgleich:** Fehlt **Jetzt synchronisieren** oder meldet WorkDiary
  „Sync nicht möglich“, ist die Anbindung inaktiv, es ist kein Adressbuch
  gewählt, oder sie wurde nach wiederholten Fehlern in Folge stillgelegt. Den
  letzten Fehler zeigt die Seite oben an.
- **Zustand prüfen:** Neben dem Seitentitel steht der zuletzt geprüfte Zustand
  der Anbindung. Mit **Verbindung testen** prüfen Sie ihn sofort.

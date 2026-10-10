---
title: "CalDAV-Kalender"
topic: admin.caldav
version: 3
keywords:
    - CalDAV
    - Nextcloud Kalender
    - ownCloud Kalender
    - Termine veröffentlichen
    - Kalender abonnieren
    - Dienstplan im Kalender
    - Urlaube im Kalender
    - Zwei-Wege-Abgleich
    - App-Passwort
    - Kalenderpfad
    - Private Adressen
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - events.manage
    - planning.shifts
    - absences.manage
    - admin.import
    - admin.notification-rules
---

Die Seite **CalDAV** veröffentlicht Termine aus WorkDiary in einen externen
CalDAV-Kalender, etwa in Nextcloud oder ownCloud – ohne Microsoft- oder
Google-Konto. Auf Wunsch kommen Dienstpläne und Urlaube hinzu, und Änderungen
aus dem Kalender lassen sich als Vorschläge zurückholen. WorkDiary bleibt
führend: Abgesagte und gelöschte Termine verschwinden dort, wiederholte Läufe erzeugen keine
Dubletten. Sie finden die Seite im Systemmenü (Zahnrad **System** in der
Kopfzeile) unter **Plugins** → **CalDAV**, sobald das Plugin aktiv ist.

## Voraussetzungen

- Das Plugin ist für Ihre Organisation aktiviert: **System** → **Plugins** →
  **Plugins**, beim Eintrag CalDAV **Aktivieren**. Die Anbindung selbst
  richten Sie nicht im Plugin-Dialog ein, sondern auf der Seite **CalDAV**.
- Die Seite steht Administratoren offen.
- Sie brauchen einen Kalender auf dem CalDAV-Server, ein Konto mit
  Schreibrecht darauf und ein App-Passwort (Nextcloud: Einstellungen →
  Sicherheit → App-Passwort).
- Der Server muss öffentlich erreichbar sein. Steht er im eigenen Netz,
  schalten Sie **Private/interne Adressen erlauben** ein (siehe unten).
- Je Organisation gibt es genau eine CalDAV-Anbindung.

## Anbindung einrichten

Im Abschnitt **Anbindung** füllen Sie aus:

- **Bezeichnung**: frei wählbarer Name; er erscheint auch bei der Auswahl
  als Importquelle.
- **DAV-Basis-URL**: die DAV-Adresse des Servers ohne Kalenderpfad, bei
  Nextcloud …/remote.php/dav. Sie muss mit http:// oder https:// beginnen.
- **Benutzername** und **App-Passwort**: Das Passwort ist beim ersten
  Speichern Pflicht und wird verschlüsselt gespeichert; ein leeres Feld behält
  später das gespeicherte Passwort.
- **Kalenderpfad (Collection)**: der Pfad des Kalenders relativ zur
  Basis-URL, etwa calendars/team/dienstplan. Eine mit „Link kopieren“ aus
  Nextcloud übernommene vollständige Adresse kürzt WorkDiary selbst, sofern
  sie mit der Basis-URL beginnt.
- **Private/interne Adressen erlauben**: nur einschalten, wenn der
  CalDAV-Server in Ihrem eigenen Netz steht (zum Beispiel 192.168.x.x). Ohne
  diesen Schalter lehnt WorkDiary interne Adressen schon beim Speichern ab.
  Das Einschalten wird protokolliert. Hat der Betreiber Ihrer Installation
  diese Freigabe gesperrt, bleibt der Schalter ohne Wirkung.
- **Aktiv**: schaltet die Anbindung ein oder aus.
- **Zwei-Wege: externe Änderungen als Inbox-Vorschläge importieren**: siehe
  unten.
- **Publizierte Inhalte**: **Termine** und/oder **Dienstpläne & Urlaube**.
  Ohne Auswahl gelten nur Termine.

Mit **Speichern** übernehmen Sie die Angaben. Ist die Anbindung aktiv, zeigt
die Seite ihren Zustand (etwa **Zustand ok**) und **Verbindung testen**.

## Was publiziert wird

- **Termine:** die Veranstaltungen Ihrer Organisation, die zwischen 30 Tagen in
  der Vergangenheit und 180 Tagen in der Zukunft beginnen. Abgesagte und
  gelöschte Veranstaltungen entfernt WorkDiary aus dem Kalender.
- **Dienstpläne & Urlaube:** veröffentlichte oder bestätigte Schichten mit
  Uhrzeiten ab zwei Monaten in der Vergangenheit sowie genehmigte Urlaube, die
  höchstens ein Jahr zurückliegen. Schichten im Entwurf, ohne Uhrzeit oder
  abgesagt und nicht mehr genehmigte Urlaube werden entfernt bzw. gar nicht
  erst angelegt.
- Benachrichtigungsregeln mit dem Kanal **Kalender** legen terminartige
  Benachrichtigungen ebenfalls in Anbindungen mit **Termine** ab.
- Geänderte Einträge werden aktualisiert, unveränderte nicht erneut gesendet.

## Wann publiziert wird

- Einmal täglich (Standard 04:35 Uhr) gleicht WorkDiary den Kalender ab. Der
  Takt lässt sich unter **Geplante Aufgaben** ändern.
- **Jetzt publizieren** startet den Abgleich sofort im Hintergrund – etwa nach
  dem Einrichten oder nach vielen Änderungen.
- Neue oder geänderte Termine erscheinen also erst nach dem nächsten Lauf im
  Kalender. Nur Benachrichtigungen über den Kanal **Kalender** gehen sofort
  hinaus.

## Zwei-Wege: Änderungen aus dem Kalender

Der Rückimport ist ausgeschaltet, bis Sie **Zwei-Wege: externe Änderungen als
Inbox-Vorschläge importieren** einschalten. Dann liest WorkDiary stündlich den
Kalender im Fenster von 30 Tagen zurück bis 180 Tage voraus:

- Neue Einträge im Kalender werden zu Vorschlägen in der Zuordnungs-Inbox.
  Nichts wird ungefragt angelegt.
- Serientermine erscheinen als Gruppe mit ihren Einzelterminen im Fenster;
  verschobene und abgesagte Einzeltermine berücksichtigt WorkDiary. Die Gruppe
  lässt sich auf einmal als Termine anlegen oder verwerfen.
- Externe Änderungen an publizierten Terminen erscheinen als Konflikt, im
  Kalender gelöschte als Fall „Termin im CalDAV-Kalender gelöscht“. WorkDiary
  löscht dabei nichts selbst.

Die Zuordnungs-Inbox steht Administratoren und der Buchhaltung offen.

## Kalender als Importquelle

Im CSV-Import (**Datentransfer** → **Import**) können Sie für Stempelungen
und Projektzeiten statt einer Datei den CalDAV-Kalender als Quelle wählen. Angeboten werden aktive
Anbindungen mit ihrer **Bezeichnung**; WorkDiary liest dann die Einträge des
gewählten Zeitraums.

## Trennen

**Trennen** schaltet die Anbindung ab. Bereits publizierte Einträge bleiben im
Kalender stehen. Zum Wiedereinschalten setzen Sie **Aktiv** und speichern.

Gelöschte Veranstaltungen, Schichten und Urlaube entfernt der nächste Abgleich
aus dem Kalender, ebenso abgesagte. Einträge, die nur aus dem Zeitfenster
herausfallen, bleiben dort stehen.

## Typische Fehler

- „Die Basis-URL muss mit http:// oder https:// beginnen.“: Tragen Sie die
  vollständige Adresse ein.
- „Die Kalender-URL liegt nicht unter der Basis-URL.“: Der eingefügte Link
  passt nicht zur DAV-Basis-URL. Geben Sie den Pfad relativ zur Basis-URL an.
- „Für eine neue Anbindung ist ein App-Passwort erforderlich.“: Beim ersten
  Speichern fehlt das Passwort.
- **Zustand fehlerhaft** mit „CalDAV-Server nicht erreichbar oder
  Zugangsdaten ungültig.“: Prüfen Sie Adresse, Kalenderpfad, Benutzername
  und App-Passwort. Ein CalDAV-Fehler mit RuntimeException deutet oft auf eine
  Adresse im internen Netz ohne Freigabe hin.
- „Die Basis-URL zeigt auf eine private/interne Adresse.“: Steht der Server im
  eigenen Netz, schalten Sie **Private/interne Adressen erlauben** ein. Hat
  der Betreiber diese Freigabe gesperrt, braucht der Server eine öffentlich
  erreichbare Adresse.
- „Keine aktive CalDAV-Anbindung vorhanden.“ bei **Jetzt publizieren**: Die
  Anbindung ist aus oder unvollständig.
- Dienstpläne fehlen im Kalender: Unter **Publizierte Inhalte** ist
  **Dienstpläne & Urlaube** nicht angehakt, oder die Schichten sind noch nicht
  veröffentlicht.

---
title: "Google Kalender anbinden"
topic: admin.google-calendar
version: 2
keywords:
    - Google Kalender
    - Google Calendar
    - Termine nach Google
    - Kalender synchronisieren
    - Google Workspace
    - Kalenderabgleich
    - Zwei-Wege-Kalender
    - Termine exportieren
    - Kalender publizieren
    - Google Cloud Console
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.msgraph
    - events.manage
    - admin.integration-inbox
    - admin.notification-rules
    - admin.import
    - admin.scheduler
---

Die Seite **Google Kalender** überträgt Veranstaltungen aus WorkDiary in einen
Kalender eines Google-Kontos. WorkDiary bleibt dabei führend: Änderungen werden
nachgezogen, abgesagte und gelöschte Veranstaltungen verschwinden aus dem Google-Kalender,
und wiederholte Läufe erzeugen keine Dubletten. Auf Wunsch liest WorkDiary den
Kalender zusätzlich zurück und legt externe Änderungen als Vorschläge zur
Prüfung vor.

## Voraussetzungen

- Das Plugin **Google Kalender** ist unter **Plugins** aktiviert. Danach
  erscheint im Systemmenü (Zahnrad-Symbol **System**) in der Gruppe
  **Plugins** der Eintrag **Google Kalender**.
- Es gibt einen OAuth-Client in der Google Cloud Console. Entweder hat der
  Betreiber einen für die gesamte Installation hinterlegt, oder Ihre
  Organisation nutzt einen eigenen: Öffnen Sie unter **Plugins** bei **Google
  Kalender** den Dialog **Konfigurieren** und tragen Sie **Client-ID (eigene
  Google-Cloud-App)** und **Client-Secret** ein. Ein eigener Client muss Ihre
  WorkDiary-Adresse mit dem Pfad /admin/google-calendar/oauth/callback als
  autorisierte Weiterleitungs-URI kennen.
- Google stuft den Kalenderzugriff als sensibel ein. Die App braucht deshalb
  eine Bestätigung durch Google, oder Sie stellen den Zustimmungsbildschirm in
  Google Workspace auf den Typ „Intern“.
- Fehlt ein OAuth-Client, zeigt die Seite einen Hinweis statt der
  Schaltfläche zum Verbinden.
- Sie brauchen ein Google-Konto mit Schreibrecht auf den Ziel-Kalender. Die
  Verbindung darf Termine bearbeiten und die Kalenderliste lesen.
- Die Seite steht Administratoren Ihrer Organisation offen. Je Organisation
  gibt es eine Verbindung.

## Verbinden

1. Klicken Sie auf **Mit Google verbinden**. Es öffnet sich die Anmeldung bei
   Google; melden Sie sich an und stimmen Sie dem Zugriff zu.
2. Google leitet Sie zurück. Die Meldung „Google-Konto verbunden.“ bestätigt
   die Verbindung; neben dem Titel steht das Abzeichen **Verbunden**.

Den Vorgang muss dieselbe Person in derselben Sitzung abschließen, die ihn
gestartet hat.

## Ziel-Kalender wählen

Im Abschnitt **Ziel-Kalender** wählen Sie unter **Kalender** einen der
Kalender des verbundenen Kontos. Ohne Auswahl gilt der **Hauptkalender
(primary)**. Dort schalten Sie bei Bedarf auch **Zwei-Wege: externe Änderungen
als Inbox-Vorschläge importieren** ein. Klicken Sie anschließend auf
**Speichern**. Wechseln Sie den Kalender, beginnt der Rückimport von vorn.

## Was übertragen wird und wann

- **Inhalt:** Veranstaltungen von 30 Tagen zurück bis 180 Tage voraus, mit
  Titel, Beschreibung, Zeit und Ort (gebuchte Räume). Abgesagte
  Veranstaltungen werden im Google-Kalender entfernt.
- **Zeitpunkt:** Ein Abgleich läuft täglich, standardmäßig um 4:55 Uhr; den
  Takt ändern Sie unter **Geplante Aufgaben**. **Jetzt publizieren** startet
  ihn sofort im Hintergrund.
- **Benachrichtigungen:** Benachrichtigungen mit einem Fälligkeitstermin gehen
  sofort als Kalendereintrag hinaus, wenn eine Benachrichtigungsregel den
  Kanal **Kalender** nutzt.
- **Ohne Zwei-Wege** liest WorkDiary keine Termine aus dem Google-Kalender.

## Zwei-Wege-Rückimport

Mit eingeschaltetem Zwei-Wege-Abgleich liest WorkDiary den Ziel-Kalender
stündlich zurück. Daraus entstehen ausschließlich Einträge in der
**Zuordnungs-Inbox**, nie selbsttätig angelegte Termine:

- Ein neuer Termin, der nicht aus WorkDiary stammt, wird ein Vorschlag.
- Ein übertragener Termin, der in Google geändert wurde, wird ein Konflikt –
  sonst würde der nächste Abgleich die Änderung still überschreiben.
- Ein übertragener Termin, der in Google gelöscht oder abgesagt wurde,
  erscheint als „Termin im Google-Kalender gelöscht“.
- Serientermine erscheinen als einzelne Termine im Importzeitraum und lassen
  sich als Gruppe übernehmen oder verwerfen.

Die **Zuordnungs-Inbox** steht Personen offen, die die Abrechnung verwalten
dürfen. Unabhängig vom Zwei-Wege-Abgleich bietet der Import von Stempelungen
und Projektzeiten den verbundenen Kalender als Quelle an.

## Trennen und erneut verbinden

**Trennen** entfernt den Zugriff. Bereits übertragene Termine bleiben im
Google-Kalender erhalten. Mit **Mit Google verbinden** stellen Sie die
Verbindung jederzeit wieder her; der gewählte Kalender bleibt gespeichert,
und die Fehlerzählung beginnt neu. Solange die Verbindung gestört ist, steht
dazu eine Betriebsaufgabe in der Übersicht der Betriebsaufgaben.

## Typische Fehlerbilder

- **Keine Schaltfläche zum Verbinden:** Es ist kein OAuth-Client hinterlegt
  (siehe Voraussetzungen).
- **„Der OAuth-Vorgang ist abgelaufen oder ungültig. Bitte erneut
  starten.“** Die Anmeldung dauerte zu lange oder wurde in einer anderen
  Sitzung beendet. Starten Sie die Verbindung neu.
- **„Die Verbindung wurde abgelehnt oder abgebrochen.“** Die Zustimmung wurde
  verweigert, oder Google lässt die App für dieses Konto nicht zu – etwa weil
  sie noch nicht bestätigt ist. Prüfen Sie den Zustimmungsbildschirm in der
  Google Cloud Console.
- **Abzeichen Nicht erreichbar:** Die Google-Calendar-API ist nicht
  erreichbar oder verweigert den Zugriff, zum Beispiel nach einem Widerruf im
  Google-Konto. **Trennen** und neu verbinden.
- **„Der gewählte Kalender wurde nicht gefunden.“** Der Kalender wurde
  gelöscht, oder das Konto hat den Zugriff verloren. Wählen Sie einen anderen.
- **Stillgelegt:** Nach wiederholten Fehlern in Folge legt WorkDiary die
  Verbindung still; dann erscheint wieder **Mit Google verbinden**. Prüfen Sie
  die Ursache und verbinden Sie neu.

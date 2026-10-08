---
title: "Microsoft 365 anbinden"
topic: admin.msgraph
version: 1
keywords:
    - Microsoft 365
    - Office 365
    - Outlook-Kalender
    - Mail über Microsoft versenden
    - Outlook-Kontakte
    - Microsoft To Do
    - OneNote
    - Teams-Meeting
    - Admin-Consent
    - Entra ID
    - Abwesenheitsnotiz
    - Exchange Online
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.sharepoint
    - admin.google-calendar
    - events.manage
    - admin.integration-inbox
    - admin.notification-rules
    - knowledge.collections
    - cloud-intake.overview
    - backup-targets.overview
---

Die Seite **Microsoft 365** bündelt die Verbindungen zu Microsoft 365 über
Microsoft Graph: Kalender, E-Mail-Versand, Kontakte nach Outlook, Microsoft
To Do und die Übernahme aus OneNote, dazu die tenantweite Freigabe. Jede
Funktion hat ihre eigene Verbindung mit eigener Anmeldung und nur den
Berechtigungen, die sie braucht – Sie verbinden nur, was Sie tatsächlich
nutzen. Jede Verbindung gilt für die ganze Organisation und arbeitet mit dem
Microsoft-Konto, das bei der Anmeldung zustimmt.

## Voraussetzungen

- Das Plugin **Microsoft 365** ist unter **Plugins** aktiviert. Danach
  erscheint im Systemmenü (Zahnrad-Symbol **System**) in der Gruppe
  **Plugins** der Eintrag **Microsoft 365**.
- Es gibt eine App-Registrierung in Microsoft Entra ID. Entweder hat der
  Betreiber eine App für die gesamte Installation hinterlegt, oder Ihre
  Organisation nutzt eine eigene: Öffnen Sie dazu unter **Plugins** bei
  **Microsoft 365** den Dialog **Konfigurieren** und tragen Sie **Client-ID
  (eigene App-Registrierung)**, **Client-Secret** und **Tenant
  (Verzeichnis-ID)** ein. Der Tenant ist die GUID Ihres Verzeichnisses oder
  einer der Werte common, organizations oder consumers; leer gilt der Wert
  der Installations-App.
- Fehlt eine App, zeigt die Seite einen Hinweis, und die Schaltflächen zum
  Verbinden fehlen.
- Sie brauchen ein Microsoft-Konto, das den Berechtigungen zustimmen darf. Die
  Seite steht Administratoren Ihrer Organisation offen.

## Kalender

Oben auf der Seite verbinden Sie den Kalender mit **Mit Microsoft 365
verbinden**. Danach stehen dort **Jetzt publizieren** und **Trennen**; neben
dem Titel zeigt ein Abzeichen **Verbunden**, **Nicht erreichbar** oder
**Inaktiv**.

- **Richtung:** Veranstaltungen aus WorkDiary werden in den Kalender des
  verbundenen Kontos übertragen – von 30 Tagen zurück bis 180 Tage voraus,
  mit Titel, Beschreibung, Zeit und Ort (gebuchte Räume). Änderungen werden
  nachgezogen, abgesagte Veranstaltungen dort entfernt, und wiederholte Läufe
  erzeugen keine Dubletten. WorkDiary bleibt führend.
- **Zeitpunkt:** Ein Abgleich läuft täglich, standardmäßig um 4:45 Uhr; den
  Takt ändern Sie unter **Geplante Aufgaben**. **Jetzt publizieren** startet
  ihn sofort im Hintergrund. Zusätzlich gehen Benachrichtigungen mit einem
  Fälligkeitstermin sofort als Kalendereintrag hinaus, wenn eine
  Benachrichtigungsregel den Kanal **Kalender** nutzt.
- **Ziel-Kalender:** Bei aktiver Verbindung wählen Sie im gleichnamigen
  Abschnitt unter **Kalender** einen Kalender des Kontos; ohne Auswahl gilt
  der **Standardkalender**. Klicken Sie anschließend auf **Speichern**.
- **Neue Termine als Teams-Meeting anlegen (Beitrittslink):** Neu übertragene
  Termine erhalten einen Teams-Beitrittslink. Bereits übertragene Termine
  ändert die Option nicht.
- **Zwei-Wege: externe Änderungen als Inbox-Vorschläge importieren:** Der
  Ziel-Kalender wird stündlich zurückgelesen, und Microsoft meldet Änderungen
  zusätzlich sofort. Neue externe Termine werden Vorschläge, Änderungen an
  übertragenen Terminen werden Konflikte, und gelöschte Termine erscheinen als
  „Termin in Microsoft 365 gelöscht“ – alles in der **Zuordnungs-Inbox**, nie
  als blind angelegter Termin. Serientermine erscheinen als einzelne Termine
  und lassen sich dort als Gruppe übernehmen oder verwerfen. Wechseln Sie den
  Ziel-Kalender, beginnt der Rückimport von vorn.

Die Kalenderverbindung nutzen außerdem:

- **Verfügbarkeit prüfen (Microsoft 365)** im Dialog einer Veranstaltung: zeigt
  frei oder belegt für die gewählten Teilnehmenden, ohne Termindetails.
- der Import von Stempelungen und Projektzeiten, der den verbundenen Kalender
  als Quelle anbietet.
- der Kasten **Team (Teams-Status)** auf der Seite **Stempeluhr**. Er erscheint
  nur, wenn die Installation den Lesezugriff auf den Teams-Status freigeschaltet
  hat und die Kalenderverbindung danach neu hergestellt wurde.

## E-Mail-Versand über Microsoft 365

Mit **Mail-Versand verbinden** erlaubt ein Konto WorkDiary, in seinem Namen
Mails zu senden – etwa Rechnungen, Mahnungen und Benachrichtigungen, ohne
SMTP-Zugang. Danach sehen Sie das **Verbundene Konto** und stellen ein:

- **Absenderadresse (optional)**: Leer sendet das Konto als es selbst. Eine
  andere Adresse, etwa ein gemeinsames Postfach, braucht in Exchange das Recht
  „Senden als“ und eine zusätzliche Berechtigung, die der Betreiber für die App
  freischaltet.
- **Kopie im Gesendet-Ordner ablegen**.
- **Speichern**.

**Testmail senden** schickt sofort eine Nachricht über diese Verbindung, an
**Empfänger (optional)** oder ohne Angabe an das verbundene Konto. Der Test
nutzt dieselbe Absenderadresse wie der echte Versand, sodass fehlende
Senderechte sofort auffallen. Ob WorkDiary seine Mails tatsächlich über diese
Verbindung verschickt, legt der Betreiber der Installation fest; ist das nicht
eingestellt, zeigt die Karte einen Hinweis. **Mail-Versand trennen** entfernt
den Zugriff.

## Kontakte nach Outlook übertragen

Nach **Kontakt-Übertragung verbinden** erscheint auf der Detailseite eines
Kunden die Schaltfläche **Nach Outlook**. Sie überträgt den Kunden als Kontakt
in Outlook des verbundenen Kontos: Name, Ansprechpartner, Firma, E-Mail,
Telefon, Mobilnummer, Website und Anschrift. Wiederholtes Übertragen
aktualisiert den Kontakt statt ihn zu verdoppeln; wurde er in Outlook
gelöscht, legt WorkDiary ihn neu an. Übertragen wird nur auf Knopfdruck und
nur in diese Richtung; dafür braucht man das Recht, den Kunden zu bearbeiten.

Die Outlook-Kontakte dieses Kontos dienen außerdem als Kontaktverzeichnis beim
Abgleich unbekannter Rufnummern, etwa im FRITZ!Box-Import.

## Microsoft To Do synchronisieren

1. Klicken Sie auf **To-Do-Sync verbinden**.
2. Legen Sie eine Zuordnung an: **To-Do-Liste** wählen, als **Ziel** ein
   **Projekt** oder das **Globales Kanban** wählen, beim Ziel Projekt das
   **Projekt** auswählen, die **Richtung** festlegen (**Beide Richtungen**,
   **Nur To Do → WorkDiary** oder **Nur WorkDiary → To Do**) und auf
   **Zuordnen** klicken.
3. Die Tabelle zeigt alle Zuordnungen. **Entfernen** löst eine; bereits
   abgeglichene Aufgaben bleiben erhalten.

Jede To-Do-Liste lässt sich einmal zuordnen; eine neue Zuordnung derselben
Liste ersetzt die alte. Abgeglichen werden Titel, Beschreibung, Status (offen,
in Arbeit, erledigt), Priorität und Fälligkeitsdatum. Der Abgleich läuft
stündlich; Änderungen in WorkDiary gehen zusätzlich sofort hinaus, und
Microsoft meldet Änderungen an importierenden Listen sofort. Haben beide Seiten
dieselbe Aufgabe geändert, entsteht ein Konflikt in der **Zuordnungs-Inbox** –
es gewinnt nicht einfach die letzte Änderung. Löschungen überträgt WorkDiary
nie; in To Do gelöschte Aufgaben werden nur markiert. Unteraufgaben,
Bearbeiter und Abschnitte kennt To Do in diesem Abgleich nicht.

## OneNote übernehmen

1. Schalten Sie unter **Plugins** bei **Microsoft 365** im Dialog
   **Konfigurieren** die Option **OneNote-Übernahme erlauben** ein. Vorher
   zeigt die Karte **Ausgeschaltet**, und die Verbindung fragt keinen Zugriff
   auf Notizbücher an.
2. Klicken Sie auf **OneNote verbinden**. Der Zugriff ist rein lesend.
3. **Zum Einstieg „Wissen“** führt zur Übernahme: Dort holt **OneNote
   übernehmen** ein Notizbuch einmalig oder auf Anstoß als Notizen oder
   Wissensartikel herein. Das Notizbuch wird eine Sammlung, seine Abschnitte
   werden Untersammlungen. Es gibt kein Zurückschreiben und keinen laufenden
   Abgleich.

## Entra-App und tenantweite Freigabe

Verbietet eine Richtlinie Ihres Microsoft-Tenants, dass Benutzer selbst
zustimmen, gibt ein Entra-Administrator die Berechtigungen einmalig für die
ganze Organisation frei: **Für Organisation freigeben (Admin-Consent)**. Die
Anmeldung verlangt eine Entra-Administratorrolle im Ziel-Tenant. Die Freigabe
umfasst Kalender, Mail-Versand, Kontakte, Aufgaben und Dokumenteingang, bei
eingeschalteter OneNote-Übernahme auch das Lesen der Notizbücher. Danach
verbinden Benutzer ohne eigene Einwilligungsabfrage.

Unter **Redirect-URIs für eine eigene App-Registrierung** stehen die Adressen,
die eine eigene App als Redirect-URI vom Typ „Web“ registrieren muss: für
Kalender, Mail-Versand, Kontakte, Aufgaben, Dokumenteingang, Admin-Consent
und – nur bei der Installations-App – das Backupziel. Die Adresse für OneNote
fehlt in dieser Liste: Tragen Sie bei eigener App zusätzlich Ihre
WorkDiary-Adresse mit dem Pfad /admin/msgraph/onenote/oauth/callback ein.
Nutzt die **SharePoint-Ablage** dieselbe App, gehört auch der Pfad
/admin/sharepoint/oauth/callback dazu.

## Weitere Funktionen des Plugins

- **Outlook-Abwesenheitsnotiz bei genehmigtem Urlaub setzen** (in den
  Plugin-Einstellungen, standardmäßig aus): Sobald ein Urlaub endgültig
  genehmigt ist, setzt WorkDiary die automatische Antwort im Postfach der
  Person. Die App braucht dafür die Anwendungsberechtigung
  MailboxSettings.ReadWrite mit Admin-Consent. Fehler halten die Genehmigung
  nicht auf.
- Der **Cloud-Dokumenteingang** und die **Cloud-Backupziele** nutzen eigene
  Microsoft-Verbindungen, die Sie auf diesen Seiten einrichten.

## Trennen und erneut verbinden

Jede Karte hat ihr eigenes Trennen. Es entfernt die Zugangsschlüssel dieser
Verbindung; übertragene Termine und Outlook-Kontakte bleiben bei Microsoft
erhalten. Sie können jederzeit neu verbinden; dabei setzt WorkDiary auch die
Fehlerzählung zurück. Wurde eine Verbindung nach wiederholten Fehlern in Folge
stillgelegt, erscheint die Schaltfläche zum Verbinden wieder.

## Typische Fehlerbilder

- **Keine Schaltflächen zum Verbinden:** Die App-Registrierung fehlt (siehe
  Voraussetzungen).
- **„Der OAuth-Vorgang ist abgelaufen oder ungültig. Bitte erneut
  starten.“** Die Anmeldung dauerte zu lange oder wurde in einer anderen
  Sitzung abgeschlossen. Die Person, die verbindet, muss den Vorgang selbst
  zu Ende führen.
- **„Die Verbindung wurde abgelehnt oder abgebrochen.“** Die Zustimmung wurde
  verweigert. Darf das Konto nicht selbst zustimmen, nutzen Sie den
  Admin-Consent.
- **Abzeichen Nicht erreichbar:** Microsoft Graph ist nicht erreichbar oder
  verweigert den Zugriff. Prüfen Sie das Konto und verbinden Sie neu.
- **„Testversand fehlgeschlagen: …“** Häufig fehlt bei abweichender
  Absenderadresse das Recht „Senden als“.
- **„Die gewählte To-Do-Liste ist nicht (mehr) verfügbar.“** Die Liste wurde
  in To Do gelöscht oder gehört nicht zum verbundenen Konto.
- **Hinweis auf Nebenverbindungen:** Meldet der Health-Check unter **Plugins**,
  dass Microsoft-365-Nebenverbindungen Aufmerksamkeit brauchen, ist eine
  Verbindung für Dokumenteingang, Backup oder Mail-Versand gestört. Melden Sie
  sich dort erneut an.

---
title: "Zammad-Anbindung"
topic: admin.zammad
version: 3
keywords:
    - Zammad
    - Helpdesk
    - Tickets importieren
    - Ticketsystem
    - Tickets als Aufgaben
    - Queue zuordnen
    - Gruppen-ID
    - Webhook
    - Ticket schließen
    - Status zurückmelden
    - Zeitbuchung ins Ticket
    - Nur zugeordnete Gruppen
    - Service-Tickets
    - Ticketziel
    - Private Adressen
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - helpdesk.overview
    - admin.data-ownership
    - admin.scheduler
    - projects.manage
---

Die Seite **Zammad** holt Tickets aus dem Ticketsystem Zammad als Aufgaben
nach WorkDiary, damit Sie dort Zeiten erfassen, Nachweise führen und
abrechnen. Zammad bleibt führend; ein erneuter Import erzeugt keine Dubletten.
Optional meldet WorkDiary erledigte Aufgaben an das Ticket zurück und bucht
erfasste Zeiten ins Ticket. Sie finden
die Seite im Systemmenü (Zahnrad **System** in der Kopfzeile) unter
**Plugins** → **Zammad**, sobald das Plugin aktiv ist.

## Voraussetzungen

- Das Plugin ist für Ihre Organisation aktiviert: **System** → **Plugins** →
  **Plugins**, beim Eintrag Zammad **Aktivieren**. Die Anbindung selbst
  richten Sie nicht im Plugin-Dialog ein, sondern auf der Seite **Zammad**.
- Die Seite steht Administratoren offen.
- Sie brauchen die Adresse Ihrer Zammad-Instanz und ein API-Token (in Zammad
  unter Profil → Token-Zugriff). Das Token muss die Tickets lesen dürfen und,
  wenn Sie die Status-Rückmeldung oder die Zeitbuchung nutzen, auch ändern.
- Die Instanz muss öffentlich erreichbar sein. Steht Zammad im eigenen Netz,
  schalten Sie **Private/interne Adressen erlauben** ein (siehe unten).
- Je Organisation gibt es genau eine Zammad-Anbindung.

## Anbindung einrichten

Im Abschnitt **Anbindung** füllen Sie aus:

- **Bezeichnung**: frei wählbarer Name.
- **Instanz-URL**: die Adresse, unter der Sie Zammad im Browser öffnen. Sie
  muss mit http:// oder https:// beginnen.
- **API-Token**: Pflicht beim ersten Speichern. Es wird verschlüsselt
  gespeichert; ein leeres Feld behält später das gespeicherte Token.
- **Webhook-Secret (optional)**: gemeinsames Geheimnis für Webhook-Aufrufe
  aus Zammad, die den Import sofort anstoßen. Ein leeres Feld behält beim
  Speichern das gespeicherte Secret. Ist die Anbindung gespeichert, steht
  unter dem Feld die **Webhook-Adresse**: Tragen Sie sie in Zammad unter
  Webhook als Endpunkt ein, das Secret als HMAC-SHA1-Signatur-Token, und
  lösen Sie den Webhook über einen Trigger aus. Ohne Webhook holt der
  regelmäßige Abruf die Tickets.
- **Standard-Projekt**: Ziel für Tickets, deren Gruppe keinem Projekt
  zugeordnet ist. **— ohne Projekt (global) —** legt sie als globale Aufgaben
  ohne Projekt an.
- **Status-Rückmeldung (Zielstatus)**: optional, siehe unten.
- **Zeitbuchung ins Ticket**: optional, siehe unten.
- **Private/interne Adressen erlauben**: nur einschalten, wenn Zammad in
  Ihrem eigenen Netz steht (zum Beispiel 192.168.x.x). Ohne diesen Schalter
  lehnt WorkDiary interne Adressen schon beim Speichern ab. Das Einschalten
  wird protokolliert. Hat der Betreiber Ihrer Installation diese Freigabe
  gesperrt, bleibt der Schalter ohne Wirkung.
- **Aktiv**: schaltet die Anbindung ein oder aus.

Mit **Speichern** übernehmen Sie die Angaben. Ist die Anbindung aktiv, zeigt
die Seite ihren Zustand (etwa **Zustand ok**) und **Verbindung testen**.

## Queue → Projekt

Unter **Queue → Projekt** ordnen Sie Zammad-Gruppen einem WorkDiary-Projekt
zu: links die **Gruppen-ID** aus Zammad, rechts das Projekt. Es gibt immer
drei freie Zeilen; für weitere Gruppen speichern Sie und tragen sie danach
ein. Zeilen ohne Gruppen-ID oder ohne Projekt verwirft WorkDiary beim
Speichern. Die Projektauswahl zeigt höchstens 500 Projekte.

Ein Ticket landet im Projekt seiner Gruppe, sonst im **Standard-Projekt**,
sonst als globale Aufgabe.

**Nur zugeordnete Gruppen** (Vorgabe aus) beschränkt den Import: Ist der
Schalter an, legt WorkDiary nur für Tickets der hier zugeordneten Gruppen
Aufgaben an; das **Standard-Projekt** greift dann nicht mehr. Ist er aus,
kommen alle Tickets, die das API-Token sieht. Ohne eine einzige Zuordnung
importiert WorkDiary mit eingeschaltetem Schalter nichts.

## Import und Zeitplan

- Alle 15 Minuten fragt WorkDiary Zammad nach Tickets. Der Takt lässt sich
  unter **Geplante Aufgaben** ändern.
- **Jetzt importieren** startet einen Import im Hintergrund.
- Mit Webhook-Secret stößt ein Webhook aus Zammad den Import zusätzlich sofort
  an. Fällt er aus, holt der regelmäßige Abruf nach.
- Abgerufen werden alle Tickets, die das API-Token sehen darf; mit **Nur
  zugeordnete Gruppen** entstehen Aufgaben nur aus den zugeordneten Gruppen.
  Jeder Lauf liest die Ticketliste vollständig, Seite für Seite.
- Jedes offene Ticket wird genau einmal zur Aufgabe. Ihr Titel ist
  Ticketnummer und Ticket-Titel; sie ist abrechenbar. Geschlossene oder
  zusammengeführte Tickets, die WorkDiary noch nicht kennt, holt der Import
  nicht nach.
- Wird ein bereits verknüpftes Ticket in Zammad geschlossen oder
  zusammengeführt, setzt WorkDiary die Aufgabe auf **Erledigt** – ohne
  Rückmeldung an das Ticket. Öffnen Sie die Aufgabe danach wieder, bleibt sie
  offen. Ein in Zammad wieder geöffnetes Ticket ändert an der Aufgabe nichts.
- Titel- und Gruppenänderungen am Ticket übernimmt WorkDiary nicht.
- Führt laut **Datenführerschaft** ein anderes System die Aufgaben, legt
  WorkDiary keine Aufgabe an, sondern einen Fall in der Zuordnungs-Inbox.

## Kunden erkennen

Enthält ein Ticket eine Kunden-E-Mail oder eine Organisation, sucht WorkDiary
den passenden Kunden. Bei einem eindeutigen Treffer hängt es die Aufgabe in
ein Projekt dieses Kunden um, bevorzugt in dessen Standardprojekt. Sonst
entsteht ein Vorschlag in der Zuordnungs-Inbox, wo Sie den Kunden bestätigen
oder wählen.

## Status-Rückmeldung

Tragen Sie unter **Status-Rückmeldung (Zielstatus)** einen Zammad-Status ein,
etwa closed. Setzt jemand eine verknüpfte Aufgabe in WorkDiary auf
**Erledigt**, setzt WorkDiary das Ticket auf diesen Status und hängt eine
interne Notiz „In WorkDiary erledigt.“ an. Die Übertragung läuft im
Hintergrund und wird bei Fehlern wiederholt. Ein leeres Feld schaltet die
Rückmeldung aus. Außer Status, Notiz und – mit der Zeitbuchung – Zeiten
schreibt WorkDiary nichts nach Zammad.

## Zeitbuchung ins Ticket

Wählen Sie unter **Zeitbuchung ins Ticket** die Einheit, in der Ihr Zammad
Zeiten erfasst: **Minuten** oder **Stunden**, passend zur Einheit der
Zeiterfassung in Zammad. Erfasst jemand in WorkDiary eine Zeit zu einer
verknüpften Aufgabe, bucht WorkDiary sie als Zeiterfassung ins Ticket; in
Stunden auf zwei Nachkommastellen gerundet. Jede Zeit wird höchstens einmal
gebucht. Die Übertragung läuft im Hintergrund und wird bei Fehlern
wiederholt. Später geänderte oder gelöschte Zeiten überträgt WorkDiary
nicht. **Aus** schaltet die Zeitbuchung ab.

## Ticketziel

Im Abschnitt **Ticketziel** steht hinter **Aktuell**, wie neue Tickets
ankommen: als **Aufgaben** (Vorgabe) oder als **Service-Tickets** einer
Queue. Zum Wechsel wählen Sie unter **Neue Tickets als** das Ziel, bei
Service-Tickets zusätzlich die **Queue**, und klicken auf **Ziel wechseln**;
WorkDiary fragt vorher nach.

- Service-Tickets setzen das Modul Helpdesk voraus. Die Queue legen Sie unter
  **Service Desk** → **Queues** an. Zammad führt danach die Tickets dieser
  Queue.
- Bereits importierte Tickets bleiben, wo sie sind.
- Gibt es in der Zuordnungs-Inbox offene Konflikte zur Datenführerschaft,
  lehnt WorkDiary den Wechsel ab, bis sie gelöst sind.
- Jeder Wechsel wird protokolliert.
- Service-Tickets bekommen keinen Kundenvorschlag, keine Status-Rückmeldung
  und keine Zeitbuchung; das gilt nur für Aufgaben.

## Grenzen

- **Trennen** schaltet die Anbindung nur ab. Aufgaben und Verknüpfungen
  bleiben erhalten, in Zammad ändert sich nichts. Zum Wiedereinschalten
  setzen Sie **Aktiv** und speichern.
- Aufgaben werden nie gelöscht, auch wenn das Ticket in Zammad verschwindet.

## Typische Fehler

- „Die Instanz-URL muss mit http:// oder https:// beginnen.“: Tragen Sie die
  vollständige Adresse ein.
- „Für eine neue Anbindung ist ein API-Token erforderlich.“: Beim ersten
  Speichern fehlt das Token.
- „Keine aktive Zammad-Anbindung vorhanden.“ bei **Jetzt importieren**: Die
  Anbindung ist aus oder unvollständig.
- „Die Instanz-URL zeigt auf eine private/interne Adresse.“: Steht Zammad im
  eigenen Netz, schalten Sie **Private/interne Adressen erlauben** ein. Hat
  der Betreiber diese Freigabe gesperrt, braucht die Instanz eine öffentlich
  erreichbare Adresse.
- **Zustand fehlerhaft** mit „Zammad-API nicht erreichbar oder Token
  ungültig.“: Prüfen Sie Adresse und Token. Ein Zammad-API-Fehler mit
  RuntimeException deutet oft auf eine Adresse im internen Netz ohne Freigabe
  hin.
- „Bitte wählen Sie eine Queue.“ bei **Ziel wechseln**: Für Service-Tickets
  fehlt die Queue.
- Tickets landen im falschen Projekt: Prüfen Sie die Gruppen-IDs unter
  **Queue → Projekt** und die Kundenvorschläge in der Inbox.

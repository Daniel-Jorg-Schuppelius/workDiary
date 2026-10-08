---
title: "Zammad-Anbindung"
topic: admin.zammad
version: 1
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
Optional meldet WorkDiary erledigte Aufgaben an das Ticket zurück. Sie finden
die Seite im Systemmenü (Zahnrad **System** in der Kopfzeile) unter
**Plugins** → **Zammad**, sobald das Plugin aktiv ist.

## Voraussetzungen

- Das Plugin ist für Ihre Organisation aktiviert: **System** → **Plugins** →
  **Plugins**, beim Eintrag Zammad **Aktivieren**. Die Anbindung selbst
  richten Sie nicht im Plugin-Dialog ein, sondern auf der Seite **Zammad**.
- Die Seite steht Administratoren offen.
- Sie brauchen die Adresse Ihrer Zammad-Instanz und ein API-Token (in Zammad
  unter Profil → Token-Zugriff). Das Token muss die Tickets lesen dürfen und,
  wenn Sie die Status-Rückmeldung nutzen, auch ändern.
- Die Instanz muss öffentlich erreichbar sein. Adressen im internen Netz lehnt
  WorkDiary ab.
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
  Speichern das gespeicherte Secret. Die Webhook-Adresse zeigt die
  Seite nicht an; ohne Webhook holt der regelmäßige Abruf die Tickets.
- **Standard-Projekt**: Ziel für Tickets, deren Gruppe keinem Projekt
  zugeordnet ist. **— ohne Projekt (global) —** legt sie als globale Aufgaben
  ohne Projekt an.
- **Status-Rückmeldung (Zielstatus)**: optional, siehe unten.
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

## Import und Zeitplan

- Alle 15 Minuten fragt WorkDiary Zammad nach Tickets. Der Takt lässt sich
  unter **Geplante Aufgaben** ändern.
- **Jetzt importieren** startet einen Import im Hintergrund.
- Mit Webhook-Secret stößt ein Webhook aus Zammad den Import zusätzlich sofort
  an. Fällt er aus, holt der regelmäßige Abruf nach.
- Abgerufen werden die Tickets, die das API-Token sehen darf – nicht nur die
  zugeordneten Gruppen.
- Jedes Ticket wird genau einmal zur Aufgabe. Ihr Titel ist Ticketnummer und
  Ticket-Titel; sie ist abrechenbar. Geschlossene oder zusammengeführte
  Tickets kommen als erledigte Aufgaben an.
- Spätere Änderungen am Ticket (Titel, Status, Gruppe) übernimmt WorkDiary
  nicht; die Aufgabe bleibt, wie sie angelegt wurde.
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
Rückmeldung aus. Weitere Daten schreibt WorkDiary nicht nach Zammad.

## Grenzen

- Ein Lauf ruft nur die erste Seite der Ticketliste ab, höchstens 100
  Tickets.
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
- **Zustand fehlerhaft** mit „Zammad-API nicht erreichbar oder Token
  ungültig.“: Prüfen Sie Adresse und Token. Ein Zammad-API-Fehler mit
  RuntimeException deutet oft auf eine Adresse im internen Netz hin.
- Tickets landen im falschen Projekt: Prüfen Sie die Gruppen-IDs unter
  **Queue → Projekt** und die Kundenvorschläge in der Inbox.

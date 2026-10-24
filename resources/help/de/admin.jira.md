---
title: "Jira-Aufgabenimport"
topic: admin.jira
version: 1
keywords:
    - Jira
    - Jira Cloud
    - Vorgänge importieren
    - Atlassian
    - Site
    - Projektzuordnung
    - Vorschau
    - Aufgabenimport
    - Klärfall
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.data-ownership
    - projects.manage
    - admin.scheduler
---

Die Seite **Jira** übernimmt Vorgänge ausgewählter Jira-Cloud-Projekte als
Aufgaben in Ihre WorkDiary-Projekte. So buchen Sie Zeiten auf die Arbeit, die
in Jira geplant wird, und rechnen sie wie gewohnt ab. Der Import liest nur:
nach Jira schreibt WorkDiary nichts. Sie finden die Seite im Systemmenü
(Zahnrad **System**) unter **Plugins** → **Jira**, sobald das Plugin aktiv ist.

## Voraussetzungen

- Das Plugin ist für Ihre Organisation aktiviert: **System** → **Plugins** →
  **Plugins**, beim Eintrag Jira **Aktivieren**.
- Das Modul Kanban ist lizenziert; die Seite steht Administratoren offen.
- Der Betreiber Ihrer Installation hat die Jira-App des Herausgebers
  hinterlegt. Fehlt sie, zeigt die Seite einen Hinweis; eine eigene App oder
  ein API-Token ist nicht nötig und nicht vorgesehen.
- Je Organisation gibt es genau eine Jira-Verbindung und genau eine Site.

## Verbinden und Site wählen

1. Klicken Sie auf **Mit Jira verbinden** und melden Sie sich bei Atlassian
   an. WorkDiary fragt nur Lesezugriff auf Projekte und Vorgänge an.
2. Zurück auf der Seite wählen Sie unter **Site wählen** die Jira-Site, deren
   Projekte Sie übernehmen möchten, und klicken auf **Übernehmen**. Angeboten
   werden nur Sites, für die die Anmeldung Lesezugriff gibt.
3. Solange Projektzuordnungen bestehen, lässt sich die Site nicht wechseln –
   bereits übernommene Aufgaben bleiben so eindeutig ihrer Site zugeordnet.

## Projekte zuordnen und Vorschau prüfen

1. **Projekt zuordnen** öffnet einen Dialog: Wählen Sie das **Jira-Projekt**
   und das **WorkDiary-Projekt**, in das die Aufgaben gehören. Je Jira-Projekt
   gibt es genau ein Ziel.
2. Danach öffnet sich die **Vorschau**. Sie zeigt die offenen Vorgänge und
   was der erste Abgleich mit ihnen täte: **Wird angelegt**, **Bereits
   verknüpft**, **Anderes Ziel**, **Klärfall** oder **Fremd geführt**. Die
   Vorschau speichert nichts.
3. Gibt es im Zielprojekt schon eine passende Aufgabe, verknüpfen Sie sie
   statt eine neue anzulegen: bei gleichem Titel über **Gleichnamige
   verknüpfen**, sonst im Abschnitt **Bestehende Aufgabe verknüpfen**.
   Abweichende Felder kommen zur Prüfung in die Inbox; leere Felder füllt
   WorkDiary aus Jira.
4. **Import bestätigen** gibt die Zuordnung frei. Erst jetzt legt der Abgleich
   die offenen Vorgänge als Aufgaben an. Erledigte Altvorgänge legt er nie an.

## Was übernommen wird

- **Titel** aus der Zusammenfassung, **Beschreibung** als reiner Text (Bilder,
  Tabellen und andere Inhalte, die sich nicht als Text darstellen lassen,
  ergeben einen Klärfall; eine vorhandene Beschreibung bleibt dann
  unverändert), **Fälligkeit** als Datum.
- **Status** über die Statuskategorie von Jira: zu erledigen → **Offen**, in
  Arbeit → **In Arbeit**, fertig → **Erledigt**. Die Namen der Status in Ihrem
  Jira spielen keine Rolle.
- Kunde, Zeiten, Sätze, Budgets und Bearbeiter führt weiterhin WorkDiary.
- Haben Sie eine Aufgabe in WorkDiary erledigt, öffnet Jira sie nicht wieder.
  Nur ein Abschluss, der aus Jira kam, wird mit Jira auch wieder geöffnet.
- Am Aufgabendialog führt **In Jira öffnen** zum Vorgang.

## Wann abgeglichen wird

- Alle 15 Minuten holt WorkDiary die seit dem letzten Lauf geänderten
  Vorgänge, einmal täglich alle Vorgänge der zugeordneten Projekte. Den Takt
  ändern Sie unter **Geplante Jobs**.
- **Jetzt abgleichen** im Menü ⋯ startet denselben Abgleich sofort.
- Bricht ein Lauf ab (etwa bei einer Störung von Jira), gilt er als
  unvollständig: die Zuordnung zeigt **unvollständig**, und der nächste Lauf
  setzt am letzten vollständigen Stand wieder an.

## Konflikte und Klärfälle

- Wurde ein Feld in WorkDiary und in Jira unterschiedlich geändert, entsteht
  ein Konflikt in der Inbox; dort entscheiden Sie, welcher Stand gilt. Nach
  Jira wird dabei nichts zurückgeschrieben.
- Klärfälle entstehen unter anderem, wenn ein Vorgang nicht mehr in Jira
  sichtbar ist (Ihre Aufgabe bleibt unverändert, keine Löschung), wenn er im
  Zielprojekt eines anderen WorkDiary-Projekts liegt (nichts wird verschoben)
  oder wenn ein unbekannter Status ankommt.
- Führt laut **Datenführerschaft** ein anderes System die Aufgaben, übernimmt
  WorkDiary nichts und legt je Vorgang einen Klärfall an.
- **Klärfälle** im Menü ⋯ öffnet die Inbox gefiltert auf Jira.

## Pausieren, entfernen, trennen

- **Pausieren** und **Fortsetzen** in der Zeile einer Zuordnung halten den
  Abgleich an. **Zuordnung entfernen** löst die Zuordnung; übernommene
  Aufgaben, Verknüpfungen und Zeiten bleiben.
- **Trennen** löscht die Anmeldung und pausiert alle Zuordnungen. Verbinden
  Sie später wieder mit derselben Site, laufen sie weiter.

## Typische Fehler

- „Jira ist für diese Installation nicht konfiguriert“: Die App-Registrierung
  fehlt; wenden Sie sich an den Betreiber.
- Keine Site zur Auswahl: Das angemeldete Konto hat auf keiner Site
  Lesezugriff auf Jira.
- Die Verbindung meldet eine abgelehnte Anmeldung: Die Freigabe wurde bei
  Atlassian widerrufen oder ist abgelaufen. Verbinden Sie neu.
- Nichts wird angelegt: Die Zuordnung ist noch nicht bestätigt, pausiert,
  oder die Vorgänge sind bereits erledigt.

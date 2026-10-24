---
title: "Asana-Aufgabenimport"
topic: admin.asana
version: 1
keywords:
    - Asana
    - Aufgaben importieren
    - Workspace
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

Die Seite **Asana** übernimmt Aufgaben ausgewählter Asana-Projekte in Ihre
WorkDiary-Projekte. So buchen Sie Zeiten auf die Arbeit, die in Asana geplant
wird, und rechnen sie wie gewohnt ab. Der Import liest nur: nach Asana
schreibt WorkDiary nichts. Sie finden die Seite im Systemmenü (Zahnrad
**System**) unter **Plugins** → **Asana**, sobald das Plugin aktiv ist.

## Voraussetzungen

- Das Plugin ist für Ihre Organisation aktiviert: **System** → **Plugins** →
  **Plugins**, beim Eintrag Asana **Aktivieren**.
- Das Modul Kanban ist lizenziert; die Seite steht Administratoren offen.
- Der Betreiber Ihrer Installation hat die Asana-App des Herausgebers
  hinterlegt. Fehlt sie, zeigt die Seite einen Hinweis; ein persönliches
  Zugriffstoken ist nicht nötig und nicht vorgesehen.
- Je Organisation gibt es genau eine Asana-Verbindung und genau einen
  Workspace.

## Verbinden und Workspace wählen

1. Klicken Sie auf **Mit Asana verbinden** und melden Sie sich bei Asana an.
   WorkDiary fragt nur Lesezugriff auf Aufgaben, Projekte, Workspaces und das
   eigene Konto an.
2. Zurück auf der Seite wählen Sie unter **Workspace wählen** den Workspace
   und klicken auf **Übernehmen**.
3. Solange Projektzuordnungen bestehen, lässt sich der Workspace nicht
   wechseln.

## Projekte zuordnen und Vorschau prüfen

1. **Projekt zuordnen** öffnet einen Dialog: Wählen Sie das **Asana-Projekt**
   und das **WorkDiary-Projekt**, in das die Aufgaben gehören.
2. Die **Vorschau** zeigt die offenen Aufgaben und was der erste Abgleich mit
   ihnen täte. Sie speichert nichts.
3. Bestehende Aufgaben des Zielprojekts verknüpfen Sie über **Gleichnamige
   verknüpfen** oder den Abschnitt **Bestehende Aufgabe verknüpfen**.
   Abweichende Felder kommen zur Prüfung in die Inbox.
4. **Import bestätigen** gibt die Zuordnung frei. Erst jetzt legt der Abgleich
   die offenen Aufgaben an. Erledigte Aufgaben legt er nie neu an.

## Was übernommen wird

- **Titel**, **Notizen** als Beschreibung und **Fälligkeit** als Datum. Hat
  eine Aufgabe in Asana eine Uhrzeit, zählt das Datum in der Zeitzone Ihrer
  Organisation.
- **Status**: In Asana erledigt → **Erledigt**, sonst **Offen**. Steht die
  Aufgabe in WorkDiary auf **In Arbeit**, bleibt das so – Asana kennt keinen
  Bearbeitungsstand.
- Liegt eine Aufgabe in mehreren zugeordneten Projekten mit demselben Ziel,
  entsteht sie nur einmal. Zeigen die Projekte auf verschiedene Ziele, legt
  WorkDiary sie nicht an, sondern meldet einen Klärfall.
- Haben Sie eine Aufgabe in WorkDiary erledigt, öffnet Asana sie nicht wieder.
- Am Aufgabendialog führt **In Asana öffnen** zur Aufgabe.

## Wann abgeglichen wird

- Alle 15 Minuten liest WorkDiary die offenen und die seit dem letzten Lauf
  erledigten Aufgaben, einmal täglich alle. Den Takt ändern Sie unter
  **Geplante Jobs**; **Jetzt abgleichen** im Menü ⋯ startet sofort.
- Bricht ein Lauf ab, gilt er als unvollständig, und der nächste Lauf setzt
  am letzten vollständigen Stand wieder an.

## Konflikte und Klärfälle

- Unterschiedliche Änderungen auf beiden Seiten ergeben einen Konflikt in der
  Inbox; nach Asana wird nichts zurückgeschrieben.
- Ist eine verknüpfte Aufgabe nicht mehr in Asana sichtbar, bleibt Ihre
  Aufgabe unverändert, und die Inbox meldet einen Klärfall.
- Führt laut **Datenführerschaft** ein anderes System die Aufgaben, übernimmt
  WorkDiary nichts.
- **Klärfälle** im Menü ⋯ öffnet die Inbox gefiltert auf Asana.

## Pausieren, entfernen, trennen

- **Pausieren** und **Fortsetzen** halten den Abgleich einer Zuordnung an;
  **Zuordnung entfernen** lässt übernommene Aufgaben und Zeiten bestehen.
- **Trennen** widerruft die Freigabe bei Asana, löscht die Anmeldung und
  pausiert alle Zuordnungen. Mit demselben Workspace verbunden, laufen sie
  später weiter.

## Typische Fehler

- „Asana ist für diese Installation nicht konfiguriert“: Die
  App-Registrierung fehlt; wenden Sie sich an den Betreiber.
- Die Verbindung meldet eine abgelehnte Anmeldung: Die Freigabe wurde in
  Asana entzogen. Verbinden Sie neu.
- Nichts wird angelegt: Die Zuordnung ist noch nicht bestätigt, pausiert,
  oder die Aufgaben sind bereits erledigt.

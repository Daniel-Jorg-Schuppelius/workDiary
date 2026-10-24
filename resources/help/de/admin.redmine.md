---
title: "Redmine-Aufgaben- und Zeitimport"
topic: admin.redmine
version: 1
keywords:
    - Redmine
    - Issues importieren
    - Zeiteinträge importieren
    - API-Schlüssel
    - selbst gehostet
    - Benutzerzuordnung
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

Die Seite **Redmine** übernimmt Issues ausgewählter Redmine-Projekte als
Aufgaben in WorkDiary-Projekte und auf Wunsch die dort erfassten Zeiteinträge
als Zeitbuchungen. Der Import liest nur: nach Redmine schreibt WorkDiary nichts.
Sie finden die Seite im Systemmenü (Zahnrad **System**) unter **Plugins** →
**Redmine**, sobald das Plugin aktiv ist.

## Zugang einrichten

1. Aktivieren Sie in Redmine unter **Administration** → **Einstellungen** →
   **API** die REST-Schnittstelle und kopieren Sie unter **Mein Konto** den
   **API-Zugriffsschlüssel**.
2. Öffnen Sie auf der Seite im Menü ⋯ **Zugangsdaten** und tragen Sie
   **Basis-URL** und **API-Schlüssel** ein. Für eine Instanz im eigenen Netz
   setzen Sie zusätzlich **Private Adressen erlauben**. Dort legen Sie auch
   fest, ob übernommene Zeiten abrechenbar sind (**Zeiten abrechenbar
   übernehmen**) und wie viele Tage zurück jeder Lauf liest (**Zeitfenster
   (Tage)**).
3. Klicken Sie auf **Verbindung prüfen**. Solange Zuordnungen bestehen, lässt
   sich keine andere Instanz eintragen.

Das Konto des Schlüssels braucht in jedem zugeordneten Projekt eine Rolle, die
Tickets und – für den Zeitimport – gebuchte Aufwände anzeigen darf. Stellen Sie
an dieser Rolle die Sichtbarkeit von Tickets und Zeiteinträgen auf **alle**;
sonst übernimmt WorkDiary nur, was das Konto selbst angelegt hat oder was ihm
zugewiesen ist.

## Projekte zuordnen und Vorschau prüfen

1. **Projekt zuordnen** wählt ein Redmine-Projekt und das WorkDiary-Projekt.
   Unterprojekte gehören nicht dazu, weder bei Issues noch bei Zeiten; ordnen
   Sie sie bei Bedarf eigens zu.
2. Die **Vorschau** zeigt die offenen Issues und was der erste Abgleich täte;
   sie speichert nichts. Bestehende Aufgaben verknüpfen Sie über
   **Gleichnamige verknüpfen** oder **Bestehende Aufgabe verknüpfen**.
3. **Import bestätigen** gibt die Zuordnung frei. Erst dann entstehen Aufgaben
   aus den offenen Issues; geschlossene Altissues nie.

## Was übernommen wird

- **Titel**, **Beschreibung** als Text und **Fälligkeit**.
- **Status**: Ein Issue mit geschlossenem Status (in Redmine als „geschlossen“
  markiert) wird **Erledigt**, sonst **Offen**; die Namen der Status spielen
  keine Rolle. Ein lokales **In Arbeit** bleibt.
- Am Aufgabendialog führt **In Redmine öffnen** zum Issue.

## Zeiteinträge

1. Schalten Sie im Abschnitt **Zeiteinträge** je Zuordnung **Zeiten
   übernehmen** ein.
2. Redmine kennt nur Tag und Stunden. WorkDiary bucht deshalb eine Dauer ohne
   Uhrzeit im Zielprojekt; hängt der Eintrag an einem verknüpften Issue, gehört
   er zu dessen Aufgabe.
3. Gebucht wird nur für zugeordnete Redmine-Benutzer. Unter
   **Benutzerzuordnung** erscheinen die Redmine-Benutzer mit wartenden
   Einträgen; wählen Sie den passenden Benutzer und klicken Sie auf
   **Zuordnen**. Der nächste Abgleich bucht die wartenden Einträge.
4. Jeder Lauf liest das Zeitfenster vollständig. Geänderte Stunden werden
   nachgezogen, in Redmine gelöschte Einträge verschwinden auch hier – außer bei
   bereits abgerechneten Zeiten: dort meldet die Inbox einen Konflikt, und die
   Zeit bleibt unverändert.

## Wann abgeglichen wird

Alle 15 Minuten holt WorkDiary die geänderten Issues und die Zeiteinträge des
Fensters, einmal täglich alle Issues. Den Takt ändern Sie unter **Geplante
Jobs**; **Jetzt abgleichen** im Menü ⋯ startet sofort.

## Konflikte, Klärfälle und Trennen

Unterschiedliche Änderungen auf beiden Seiten ergeben einen Konflikt in der
Inbox; nichts wird nach Redmine zurückgeschrieben. Nicht mehr sichtbare Issues,
fremde Datenführerschaft und unbekannte Status werden als Klärfall gemeldet.
**Trennen** beendet den Abgleich und pausiert die Zuordnungen; übernommene
Aufgaben und Zeiten bleiben. Den Schlüssel entziehen Sie in Redmine oder
löschen ihn unter **Zugangsdaten**.

## Typische Fehler

- Die Prüfung scheitert mit 401 oder 403: Der API-Schlüssel ist falsch oder die
  REST-Schnittstelle in Redmine ausgeschaltet.
- Zeiten erscheinen nicht: **Zeiten übernehmen** ist aus, der Redmine-Benutzer
  ist noch nicht zugeordnet oder der Eintrag liegt außerhalb des Zeitfensters.
- Der Abgleich bricht mit 401 ab: Redmine behandelt einen ungültigen Schlüssel
  wie einen nicht angemeldeten Besucher und zeigt dann nur Öffentliches.
  WorkDiary prüft deshalb vor jedem Lauf das Konto und liest bei einem
  ungültigen Schlüssel nichts.

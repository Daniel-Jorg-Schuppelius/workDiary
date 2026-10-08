---
title: "OpenProject-Integration"
topic: admin.openproject
version: 3
keywords:
    - Projektmanagement
    - Work Packages
    - Arbeitspakete
    - Zeitbuchungen
    - Zeiten importieren
    - Zeiten zurückbuchen
    - Zeitsynchronisation
    - Projekte synchronisieren
    - Projektzuordnung
    - Aufgaben importieren
    - Mapping
audience:
    - admin
related:
    - admin.plugins
    - admin.toggl
    - admin.import
    - admin.integration-inbox
---

Die OpenProject-Integration koppelt WorkDiary **bidirektional** mit
OpenProject: Zeiten werden importiert **und** erfasste Zeiten lassen
sich nach OpenProject zurückbuchen. Zugangsdaten und Optionen
hinterlegen Sie in den Plugin-Einstellungen (u. a. **Instanz-URL**,
**API-Token** und **Sync-Zeitfenster (Tage)**).

Synchronisieren (Seite **OpenProject synchronisieren**):

- **Struktur + Zeiten synchronisieren** mit **Jetzt synchronisieren**:
  gleicht Projekte und Work Packages ab und importiert anschließend die
  Zeiteinträge im eingestellten Zeitfenster.
- **Nur Struktur abgleichen** mit **Struktur abgleichen**: ordnet
  Projekte, Work Packages und Benutzer aus OpenProject den
  WorkDiary-Projekten, -Aufgaben und -Benutzern zu. Ist **Fehlende
  Projekte/Aufgaben anlegen** eingeschaltet, legt der Abgleich fehlende
  Einträge automatisch an.

Unzugeordnete Zeiteinträge:

- Was sich nicht automatisch zuordnen lässt, landet in der zentralen
  **Zuordnungs-Inbox**; **Zur Zuordnungs-Inbox** führt dorthin und
  zeigt die Zahl der offenen Einträge.
- Dort ordnen Sie eine Gruppe einem Kunden und Projekt zu (oder
  benennen ein neues) und buchen sie, oder Sie verwerfen sie. Künftige
  Importe ordnen anhand der gespeicherten Zuordnungen automatisch zu.

Rückbuchung (**Zeiten zurückbuchen**):

- Schreibt nicht exportierte Zeiten von Projekten, die einem
  OpenProject-Projekt zugeordnet sind, nach OpenProject zurück;
  Aufgaben werden – sofern zugeordnet – als Work Package gebucht.
  **Zeitraum (optional)** grenzt den Lauf ein (leer = alle offenen
  Einträge), **Jetzt zurückbuchen** startet ihn, **Letzte Rückbuchung**
  zeigt das Ergebnis. Bereits gebuchte Einträge werden übersprungen.
- Voraussetzung: In den Plugin-Einstellungen muss die
  **OpenProject-Activity-ID (Rückbuchung)** hinterlegt sein – sonst ist
  keine Rückbuchung möglich.

Zuordnungen (**Zuordnungen verwalten**):

- Die Seite **OpenProject – Zuordnungen** listet die gemerkten
  Verknüpfungen für Projekte, Work Packages und Benutzer. Mit
  **Umlegen** ändern Sie das Ziel, mit **Entfernen** löschen Sie eine
  Zuordnung.

Risiken: Die Rückbuchung verändert Daten im verbundenen OpenProject-
System. Prüfen Sie vor dem ersten Lauf die Zuordnungen und die
Activity-ID, um Fehlbuchungen zu vermeiden.

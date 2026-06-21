---
title: "OpenProject-Integration"
topic: admin.openproject
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.toggl
    - admin.import
---

Die OpenProject-Integration koppelt WorkDiary **bidirektional** mit
OpenProject: Zeiten werden importiert **und** erfasste Zeiten lassen
sich nach OpenProject zurückbuchen.

Struktur-Sync:

- Importiert Projekte, Work Packages und Nutzer aus OpenProject und
  verknüpft sie mit WorkDiary-Projekten und -Aufgaben.
- Voraussetzung für den Zeit-Import.

Zeit-Import (Sync):

- Übernimmt Zeiteinträge aus OpenProject.

Posteingang (unzugeordnete Einträge):

- OpenProject-Projekte ohne automatische Zuordnung sammeln sich hier
  (mit Anzahl, Dauer und Zeitraum).
- Du ordnest sie einem bestehenden Projekt zu, legst ein neues an
  oder verwirfst sie. Künftige Importe ordnen anhand der gespeicher­
  ten Zuordnungen automatisch zu.

Rückbuchung (Push):

- Schreibt in WorkDiary erfasste Zeiten nach OpenProject zurück.
  Bereits exportierte Einträge werden übersprungen und neu
  exportierte als exportiert markiert.
- Voraussetzung: Im Plugin muss eine **Standard-Aktivität**
  (default_activity_id) hinterlegt sein – sonst schlägt die
  Rückbuchung fehl.

Zuordnungen (Mappings):

- Verknüpfungen für Projekte, Work Packages und Nutzer. Lassen sich
  ändern oder löschen.

Risiken: Die Rückbuchung verändert Daten im verbundenen OpenProject-
System. Prüfe vor dem ersten Push die Mappings und die
Standard-Aktivität, um Fehlbuchungen zu vermeiden.

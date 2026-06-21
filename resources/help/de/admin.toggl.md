---
title: "Toggl-Import"
topic: admin.toggl
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

Der Toggl-Import übernimmt Zeiteinträge aus Toggl Track in
WorkDiary. Der Import ist **einseitig** (nur Lesen) – es werden
keine Zeiten nach Toggl zurückgeschrieben.

Zwei Importwege:

- **API-Import**: zieht Zeiteinträge direkt über die Toggl-API. Du
  hinterlegst ein API-Token und wählst einen Zeitraum.
- **Datei-/Export-Import**: lädt einen Toggl-Detailbericht (CSV) oder
  ein vollständiges Workspace-Export-Archiv hoch und importiert
  daraus.

Posteingang (unzugeordnete Einträge):

- Toggl-Kunden/-Projekte, die sich nicht automatisch einem
  WorkDiary-Kunden/-Projekt zuordnen ließen, sammeln sich hier –
  gruppiert mit Anzahl, Dauer und Zeitraum.
- Du ordnest jede Gruppe einem bestehenden Kunden/Projekt zu, legst
  neue an oder verwirfst Einträge.
- Der erste Import erfolgt manuell; künftige Importe ordnen anhand
  gespeicherter Zuordnungen automatisch zu.

Zuordnungen (Mappings):

- Gespeicherte Verknüpfungen merken sich, welcher Toggl-Kunde bzw.
  welches Toggl-Projekt zu welchem WorkDiary-Kunden/-Projekt gehört.
- Zuordnungen lassen sich ändern (neu verknüpfen) oder löschen
  (zurück zur manuellen Zuordnung).

Risiken: Wiederholte Importe desselben Zeitraums können zu Dubletten
führen, wenn Quelldaten geändert wurden – prüfe Zeitraum und
Posteingang vor dem Übernehmen. Das Verwerfen von Einträgen ist
endgültig.

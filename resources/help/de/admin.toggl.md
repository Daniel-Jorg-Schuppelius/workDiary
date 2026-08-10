---
title: "Toggl-Import"
topic: admin.toggl
version: 2
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

Der Toggl-Import übernimmt Zeiteinträge aus Toggl Track in
WorkDiary. Standardmäßig liest der Import nur; optional lassen sich
Korrekturen zurückschreiben („Korrekturen zurückschreiben") und lokal
erfasste Zeiten übertragen („Zeit-Übertragung aktivieren").

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

Benutzerzuordnung (MVP-509):

- Jeder Toggl-Eintrag wird über die Benutzer-E-Mail des Workspaces dem
  passenden WorkDiary-Benutzer zugeordnet: zuerst gespeicherte
  Zuordnungen („Zuordnungen verwalten"), dann E-Mail-Gleichheit.
- Unbekannte oder nicht abrufbare Toggl-Benutzer werden nicht still auf
  den Hauptbenutzer gebucht — sie landen als offener Fall in der
  Zuordnungs-Inbox. Dort wählst du den Benutzer; die Wahl wird gemerkt
  und künftige Importe buchen automatisch richtig.
- Nur im ausdrücklich aktivierten Einbenutzer-Modus (Plugin-Einstellung)
  bucht der Import Einträge ohne Benutzersignal auf den
  Standard-Benutzer.
- Bereits falsch zugeordnete Alt-Importe repariert der Befehl
  `toggl:repair-entry-users` (erst Dry-Run, mit `--apply` schreiben);
  abgerechnete oder signierte Zeiten werden nie automatisch verändert.

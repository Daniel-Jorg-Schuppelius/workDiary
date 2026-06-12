# Mobiler Field-Workflow

## Status

In Progress

## Ziel

Mitarbeitende im Außendienst sollen Auftrag, Arbeitszeit, Fahrt, Material,
Fotos, Checklisten, Protokolle, Spesen und Kundenunterschrift mobil in einem
durchgehenden Ablauf erfassen können. Die PWA soll auch bei schlechtem Netz
nutzbar bleiben und später sauber synchronisieren.

## Warum

Viele Zeiterfassungssysteme enden beim Start-/Stop-Button. Für Service- und
Einsatzteams entsteht der eigentliche Wert aber erst durch den vollständigen
Arbeitsnachweis: Was wurde getan, wann, wo, mit welchem Material, mit welchem
Ergebnis, mit welcher Dokumentation und mit welcher Kundenbestätigung.

## MVP

- Mobile Tagesansicht mit aktuellen Aufträgen, Schichten und offenen Aufgaben.
- Offlinefähiges Erfassen von Zeit, Notizen, Fotos, Checklisten und Material.
- Mobile Abnahme- und Serviceprotokolle mit Unterschrift.
- Kundenunterschrift für Stundenzettel oder Einsatzabschluss.
- Sync-Status pro lokal erfasstem Datensatz.
- Konfliktanzeige, wenn Daten serverseitig geändert wurden.
- Tagesabschluss aus mobilen Daten.

## Akzeptanzkriterien

- Ein Außendiensttag kann ohne stabile Netzverbindung dokumentiert werden.
- Lokale Daten werden nicht still überschrieben.
- Admins sehen, welche Daten mobil/offline entstanden sind.
- Fotos, Material und Zeiten hängen am richtigen Auftrag oder Stundenzettel.
- Protokolle können vor Ort vollständig abgeschlossen oder als offene Punkte
  weitergegeben werden.

## Abhängigkeiten

- PWA-Assets und Service Worker
- `DiaryEntry`
- `Timesheet`
- `TimeEntry`
- `TravelLog`
- `MaterialUsage`
- `Attachment`
- Dokumentation und Abnahmeprotokolle

## GitHub Issues

- TBD

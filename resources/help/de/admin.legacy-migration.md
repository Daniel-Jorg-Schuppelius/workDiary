---
title: "Legacy-Migration"
topic: admin.legacy-migration
version: 1
audience:
    - admin
related:
    - admin.import
    - admin.data-transfer
    - admin.handbook
---

Die Legacy-Migration übernimmt Daten aus dem Altsystem in WorkDiary
und zeigt den Übernahmestand je Datenbereich. Voraussetzung ist eine
konfigurierte Datenbankverbindung zum Altsystem; ist sie nicht
erreichbar, wird der Bereich als „nicht konfiguriert" angezeigt.

Die Übersicht stellt pro Bereich gegenüber, wie viele Datensätze im
Altsystem vorhanden und wie viele bereits importiert sind:

- **Benutzer**
- **Tagebuch-Einträge**
- **Bereitschaftsdienste (Schichten)**
- **Notdienst-Einsätze (Zuordnungen)**

Der Import wird je Bereich gestartet und ruft im Hintergrund den
Befehl `legacy:import` auf. Bereits importierte Datensätze sind über
eine Legacy-Kennung verknüpft, sodass wiederholte Läufe keine
Dubletten anlegen.

Hinweis: Der Schreibzugriff hängt von der Konfiguration
(`legacy_write_enabled`) ab. Schlägt ein Import fehl, prüfe die
Log-Dateien. Der Zugriff erfordert das Recht zur Einsicht in
Audit-Logs.

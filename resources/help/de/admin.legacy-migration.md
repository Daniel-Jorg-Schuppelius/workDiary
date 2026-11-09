---
title: "Legacy-Migration"
topic: admin.legacy-migration
version: 2
keywords:
    - Altsystem
    - Altdaten
    - Altdaten importieren
    - Datenübernahme
    - Datenmigration
    - Migration
    - Umstieg
    - Tagebucheinträge übernehmen
    - Bereitschaftsdienste übernehmen
    - Notdienst-Einsätze
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
- **Support-Portal (Kunden und Aufträge)**

Der Import wird je Bereich gestartet und ruft im Hintergrund den
Befehl `legacy:import` auf. Bereits importierte Datensätze sind über
eine Legacy-Kennung verknüpft, sodass wiederholte Läufe keine
Dubletten anlegen.

Das **Support-Portal** wird in eine wählbare **Zielorganisation**
übernommen: Die Kundenzugänge werden über den Firmennamen vorhandenen
Kunden zugeordnet oder als Kunden angelegt, Aufträge und Archiv werden
Servicetickets mit der Nummer KP-… und behalten Termin, Mechaniker,
Antwort und interne Notiz. Passwörter werden nicht übernommen; den
Zugang zum Kundenportal erteilen Sie im neuen System per Einladung. Ein
erneuter Lauf aktualisiert bereits übernommene Tickets. Zeiten des
Altsystems gelten als Ortszeit und werden beim Import umgerechnet.

Hinweis: Der Schreibzugriff hängt von der Konfiguration
(`legacy_write_enabled`) ab. Schlägt ein Import fehl, prüfen Sie die
Log-Dateien. Die Seite öffnet nur der Plattformbetrieb, weil die
Altdatenbank der ganzen Installation gehört.

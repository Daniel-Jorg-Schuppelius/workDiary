---
title: "Plugins"
topic: admin.plugins
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.toggl
    - admin.openproject
    - admin.lexoffice
    - admin.remote-support
---

Hier verwaltest du die installierten Plugins und Integrationen.
Plugins erweitern WorkDiary um externe Anbindungen (z. B. Toggl,
OpenProject, Lexoffice, Fernwartung).

Wichtig: Plugins werden **pro Organisation** gesteuert. Aktivierung,
Einstellungen, Health-Status und Fehler gelten jeweils für deine
Organisation – ein Plugin kann in einer anderen Organisation einen
ganz anderen Zustand haben.

Übersicht (Liste):

- **Status**: aktiv, inaktiv oder automatisch deaktiviert.
- **Health**: ok / eingeschränkt / fehlerhaft samt Zeitpunkt der
  letzten Prüfung.
- **Aktionen je Plugin**: Konfigurieren, Aktivieren/Deaktivieren,
  Health-Check sofort ausführen, bei Auto-Deaktivierung
  zurücksetzen und reaktivieren.

Konfigurieren (Bearbeiten):

- Einstellungen je Plugin (z. B. API-Token, Endpunkte). Passwörter/
  Token: leeres Feld lässt den bestehenden Wert unverändert.
- **Verbindung testen** löst einen Health-Check aus, ohne zu
  speichern.

Health-Check und Auto-Deaktivierung:

- Der Health-Check prüft die Erreichbarkeit/Funktion und schreibt
  das Ergebnis pro Organisation fort. Er läuft manuell oder
  geplant (Cron).
- Treten wiederholt Fehler auf, wird das Plugin nach Erreichen der
  Schwelle **automatisch deaktiviert** – nur für die betroffene
  Organisation. So bleibt der Betrieb für andere unberührt.
- Nach Behebung der Ursache setzt du den Fehlerzähler zurück und
  reaktivierst das Plugin.

Fehlerprotokoll (Plugin-Fehler):

- Liste aller erfassten Fehler mit Zeitpunkt, Plugin, Phase
  (Boot/Laufzeit/Health-Check), Ausnahmeklasse und Meldung.
- Filter nach Plugin, Phase und Status (offen/bestätigt).
- In der Detailansicht: vollständige Meldung, Kontext und Stacktrace.
- Fehler lassen sich als **bestätigt** markieren (mit Bearbeiter und
  Zeitstempel); sie bleiben zur Nachvollziehbarkeit erhalten.

Berechtigung: Diese Bereiche sind Administratoren vorbehalten und
benötigen einen Organisationskontext.

Risiken: Ein deaktiviertes Plugin stellt seine Synchronisation ein –
Importe/Exporte und Health-Checks pausieren, bis es reaktiviert ist.
Prüfe nach jeder Konfigurationsänderung den Health-Status.

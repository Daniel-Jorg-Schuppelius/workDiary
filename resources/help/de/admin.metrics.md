---
title: "Metriken"
topic: admin.metrics
version: 1
audience:
    - admin
related:
    - admin.diagnostics
    - admin.handbook
    - admin.backups
---

Die Metriken-Seite zeigt schreibgeschützte Betriebs- und
Leistungskennzahlen zur Überwachung des Systems. Sie ergänzt die
Diagnose, die den Ampel-Status der Health-Checks liefert.

Erfasste Kennzahlen sind unter anderem:

- **Version** der Anwendung
- **Queue**: ausstehende und fehlgeschlagene Jobs
- **Backups**: jüngste Sicherungen (Zeitpunkt, Größe, Quelle)
- **Plugin-Fehler**: Anzahl und letzte Vorfälle
- **Storage**: Speichernutzung (z. B. Anhänge, Dokumentversionen)
- **Aktive Benutzer**
- **Modul-Zahlen**: Bestände je Modul (z. B. Tagebuch, Dokumente)
- **Feature-Nutzung**: aggregierte Nutzung einzelner Funktionen

Die Werte werden bei jedem Aufruf frisch erhoben; einzelne Bereiche
fallen bei Nichtverfügbarkeit auf leere Vorgaben zurück, ohne die
Seite zu blockieren.

Der Aufruf erfordert das Metriken-Recht. Detaillierte Health-Checks
und die Test-Mail findest du unter **Diagnose**.

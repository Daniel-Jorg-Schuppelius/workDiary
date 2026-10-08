---
title: "Metriken"
topic: admin.metrics
version: 3
keywords:
    - Kennzahlen
    - Betriebskennzahlen
    - Statistiken
    - Monitoring
    - Systemauslastung
    - Speicherplatz
    - Speichernutzung
    - aktive Benutzer
    - fehlgeschlagene Jobs
    - Queue
    - Nutzungsstatistik
    - Performance
audience:
    - admin
related:
    - admin.diagnostics
    - admin.handbook
    - admin.backups
---

Die Seite **Betriebsmetriken** zeigt schreibgeschützte Betriebs- und
Leistungskennzahlen zur Überwachung des Systems. Sie ergänzt die
Diagnose, die den Ampel-Status der Health-Checks liefert. Alle
Kennzahlen werden ausschließlich lokal erhoben und gespeichert; ein
Versand an externe Systeme findet nicht statt.

Die Seite gliedert sich in folgende Bereiche:

- **Version** der Anwendung (im Seitenkopf)
- **Queue**: **Wartende Jobs** und **Fehlgeschlagene Jobs**
- **Backup-Heartbeats**: jüngste gemeldete Sicherungen (Zeitpunkt,
  Größe, Quelle)
- **Plugin-Fehler (7 Tage)**: Anzahl und letzte Vorfälle
- **Speicher**: Anzahl und Größe der **Anhänge** und
  **Dokument-Versionen** laut Datenbank-Metadaten (die Disk-Belegung
  zeigt die Diagnose)
- **Aktive Benutzer (30 Tage)**: eindeutige Benutzer mit Login laut
  Audit-Log
- **Datensätze je Kernmodul**: Bestände z. B. für **Aufträge
  (Tagebuch)**, **Dokumente**, **Protokolle** und **Wissensartikel**
- **Feature-Nutzung (30 Tage)**: je Funktion **Anzahl** und **Zuletzt
  genutzt**, aggregiert je Organisation und Tag
- **Metrik-Transparenz**: welche Nutzungszähler erhoben werden und ob
  sie gerade aktiv sind

Die Werte werden bei jedem Aufruf frisch erhoben; einzelne Bereiche
fallen bei Nichtverfügbarkeit auf leere Vorgaben zurück, ohne die
Seite zu blockieren.

Der Aufruf erfordert das Recht **Betriebsmetriken einsehen**.
Detaillierte Health-Checks und die Test-Mail finden Sie unter
**Diagnose**.

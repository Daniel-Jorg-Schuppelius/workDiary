# Produktmetriken und Betriebsmetriken

## Status

Proposed

## Ziel

WorkDiary soll datenschutzfreundliche Betriebsmetriken erfassen, um SaaS und
lokale Installationen sicher zu betreiben: Performance, Fehler, Jobstatus,
Speicher, Feature-Nutzung, Lizenzstatus und Systemzustand.

## Warum

Für Betrieb und Weiterentwicklung sind technische Metriken wichtig. Sie dürfen
aber nicht zum verdeckten Tracking oder zur Verwertung von Kundendaten werden.

## MVP

- Health-Metriken: Jobs, Queues, Fehler, Speicher, Version, Backupstatus.
- Feature-Nutzung nur aggregiert und mandantenbezogen, wenn aktiviert.
- Keine Inhalte aus Aufträgen, Protokollen oder Kundendaten in Telemetrie.
- Opt-in/Opt-out pro Installation oder Mandant.
- Admin-Ansicht, welche Metriken erhoben werden.

## Akzeptanzkriterien

- Kunden können technische Metriken nachvollziehen und begrenzen.
- Keine personenbezogenen oder fachlichen Inhalte werden als Telemetrie
  übertragen.
- SaaS-Betrieb erkennt technische Probleme frühzeitig.
- Datenschutzgrundsätze aus `016` gelten vollständig.

## Abhängigkeiten

- Datenschutz, Sicherheit und Datenlebenszyklus
- Mandantenfähigkeit und Betriebsmodelle
- Backup, Restore und Disaster Recovery
- Release-, Update- und Plugin-Strategie

## GitHub Issues

- TBD

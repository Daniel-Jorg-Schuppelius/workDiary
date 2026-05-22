# Support und Fehlerdiagnose für Kundeninstallationen

## Status

Proposed — Grundsätze und Auditpunkte verbindlich dokumentiert in
[`docs/security/supportzugriff-grundsaetze.md`](../security/supportzugriff-grundsaetze.md)
(MVP-004, Issue #4).

## Ziel

WorkDiary soll Support und Fehlerdiagnose für lokale Installationen, Private
Cloud und SaaS erleichtern. Betreiber und Kundenadmins sollen Systemzustand,
Version, Lizenz, Jobs, Queues, Speicher, Mail, Push, Backups, Integrationen und
Fehler schnell prüfen können.

## Warum

Ein verkaufbares Produkt braucht wartbare Installationen. Support darf nicht
darauf angewiesen sein, direkt in Kundendaten oder Server einzusehen. Diagnose
muss datenschutzfreundlich, nachvollziehbar und reproduzierbar sein.

## MVP

- Admin-Diagnoseseite mit Systemstatus.
- Exportierbarer Supportbericht ohne fachliche Kundendaten.
- Checks für Lizenz, Version, Migrationen, Queue, Scheduler, Mail, Storage,
  Backups und Integrationen.
- Protokollierte Supportfreigabe durch Kundenadmin.
- Fehlercodes und verständliche Handlungsempfehlungen.

## Akzeptanzkriterien

- Kundenadmins können häufige Betriebsprobleme erkennen.
- Supportberichte enthalten keine Auftrags-, Kunden- oder Personendaten.
- Supportzugriffe werden protokolliert und zeitlich begrenzt.
- Lokale Installationen können ohne direkten Datenbankzugriff diagnostiziert
  werden.

## Abhängigkeiten

- Datenschutz, Sicherheit und Datenlebenszyklus
- Mandantenfähigkeit und Betriebsmodelle
- Backup, Restore und Disaster Recovery
- Release-, Update- und Plugin-Strategie
- Produktmetriken und Betriebsmetriken

## GitHub Issues

- TBD

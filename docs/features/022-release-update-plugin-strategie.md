# Release-, Update- und Plugin-Strategie

## Status

Proposed

## Ziel

WorkDiary soll für lokale Installationen und SaaS sicher aktualisierbar sein.
Releases, Datenbankmigrationen, Plugin-Versionen, kundenspezifische Erweiterungen
und Rollbacks müssen kontrolliert werden.

## Warum

On-Premise-Kunden brauchen planbare Updates. SaaS braucht schnelle, sichere
Rollouts. Plugins dürfen den Kern nicht destabilisieren und müssen zu Versionen
passen.

## MVP

- Release-Prozess mit Changelog und Migrationshinweisen.
- Update-Anleitung für lokale Installationen.
- Plugin-Kompatibilitätsangaben.
- Wartungsfenster und Rollback-Plan für SaaS.
- Version-/Build-Anzeige in der App.
- Health-Check nach Update.

## Akzeptanzkriterien

- Eine lokale Installation kann nachvollziehbar aktualisiert werden.
- Migrationen sind vor Produktivbetrieb prüfbar.
- Inkompatible Plugins werden erkannt.
- SaaS-Rollouts können gestoppt oder zurückgerollt werden.

## Abhängigkeiten

- Mandantenfähigkeit und Betriebsmodelle
- Lizenzierung
- Plugin-System
- Backup und Restore

## GitHub Issues

- TBD

# Release-, Update- und Plugin-Strategie

## Status

In Progress

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
- Maschinenlesbare Software-Stückliste (SBOM) je Release mit exakten
  Composer-, NPM-, Modul- und Plugin-Versionen.
- Signierte Release-Metadaten mit Build-Hash und Prüfsummen.
- Security Advisories sowie dokumentierte Betroffenheitsbewertung für
  veröffentlichte Versionen.
- Wartungsfenster und Rollback-Plan für SaaS.
- Version-/Build-Anzeige in der App.
- Health-Check nach Update.

## Akzeptanzkriterien

- Eine lokale Installation kann nachvollziehbar aktualisiert werden.
- Migrationen sind vor Produktivbetrieb prüfbar.
- Inkompatible Plugins werden erkannt.
- Eine konkrete Installation kann ihre Komponenten und Versionen einem
  veröffentlichten Release und dessen SBOM zuordnen.
- SaaS-Rollouts können gestoppt oder zurückgerollt werden.

## Abhängigkeiten

- Mandantenfähigkeit und Betriebsmodelle
- Lizenzierung
- Plugin-System
- Backup und Restore
- ISMS und ISO/IEC 27001-Auditbereitschaft

## GitHub Issues

- TBD

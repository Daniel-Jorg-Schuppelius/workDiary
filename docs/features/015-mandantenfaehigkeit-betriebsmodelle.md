# Mandantenfähigkeit und Betriebsmodelle

## Status

Proposed

## Ziel

WorkDiary soll sowohl als lokal installierbare Kundeninstanz als auch als
SaaS-Dienst verkauft und betrieben werden können. Dafür braucht das Produkt
eine belastbare Multi-Mandanten-Architektur, klare Betriebsmodelle,
Lizenzierung, Update-Strategie, Datenisolation, Backups, Monitoring und
Administrationswerkzeuge.

## Warum

Das Geschäftsmodell beeinflusst die Architektur. Eine lokale Installation muss
einfach auslieferbar, lizenzierbar, updatefähig und beim Kunden wartbar sein.
Ein SaaS-Dienst muss Mandanten sicher voneinander trennen, zentral betrieben,
überwacht, gesichert und abgerechnet werden. Beide Modelle dürfen nicht erst am
Ende nachträglich aufgesetzt werden, weil Mandantenfähigkeit, Rechte, Storage,
Jobs, Kalenderfeeds, Anhänge, Exporte und Integrationen tief in das System
greifen.

## Betriebsmodelle

- On-Premise: eine Instanz pro Kunde, lokal oder auf Kundenserver.
- Private Cloud: eine dedizierte Instanz pro Kunde, vom Anbieter betrieben.
- SaaS Multi-Tenant: mehrere Kunden auf einer Plattform mit sauberer
  Datenisolation.
- Hybrid: lokale Installation mit optionalen Update-, Lizenz- oder
  Supportdiensten.

## MVP

- Organisation/Mandant als harte fachliche Grenze für alle Kundendaten.
- Zentrale Mandantenverwaltung für SaaS-Betrieb.
- Lizenzprüfung pro Instanz oder Mandant mit Feature-Flags und Nutzerlimit.
- Mandantenfähige Dateispeicherung für Anhänge, Logos, Exporte und PDFs.
- Mandantenfähige Queues, Jobs, Kalenderfeeds, API-Tokens und Webhooks.
- Backup-/Restore-Konzept pro Mandant und pro Instanz.
- Update- und Migrationsstrategie für lokale Installationen.
- Systemadmin-Rolle getrennt von Kundenadmin-Rolle.
- Betriebsstatus: Version, Lizenz, Jobs, Speicher, letzte Backups, Fehler.

## Akzeptanzkriterien

- Ein Mandant kann keine Daten eines anderen Mandanten lesen, schreiben,
  suchen, exportieren oder über Anhänge abrufen.
- Exporte, Reports, Kalenderfeeds, API-Antworten und Suchergebnisse sind
  mandantensicher.
- Systemadmins können Mandanten verwalten, ohne fachliche Kundendaten unnötig
  offenzulegen.
- Eine lokale Installation kann lizenziert, aktualisiert und diagnostiziert
  werden.
- SaaS-Betrieb kann Mandanten anlegen, sperren, deaktivieren, exportieren und
  löschen.
- Backups und Restore sind so definiert, dass ein einzelner Kunde wiederherstellbar
  ist, ohne andere Mandanten zu beschädigen.

## Sicherheits- und Betriebsfragen

- Wird SaaS mit gemeinsamer Datenbank und `organization_id` betrieben oder mit
  getrennten Datenbanken pro Mandant?
- Wie werden Anhänge und private Dateien mandantensicher abgelegt?
- Wie werden Hintergrundjobs mandantengebunden ausgeführt?
- Wie wird verhindert, dass globale Admins versehentlich im falschen Mandanten
  arbeiten?
- Wie werden Supportzugriffe protokolliert?
- Wie werden Updates bei lokalen Installationen verteilt und geprüft?
- Welche Feature-Flags hängen an Lizenz, Tarif oder Mandant?
- Wie werden Test-, Staging- und Produktionsmandanten getrennt?

## Vorhandene Basis

- `Organization` und organisationsbezogene Modelle.
- `BelongsToOrganization` und `OrganizationScope`.
- Organisationswechsel für globale Admins.
- Rollen, Rechte und Gruppen.
- Lizenzierung mit Domain, Nutzerlimit und Feature-Flags.
- Branding pro Organisation.
- Deaktivierung, Reaktivierung, Export und Purge von Organisationen.
- Audit- und Organisation-Audit-Logs.

## Später

- Mandanten-Self-Service für Registrierung, Tarifwechsel und Rechnungsdaten.
- Anbieter-Admin-Dashboard für SaaS-Betrieb.
- Mandantenbezogene Nutzungsabrechnung.
- Automatisierte Health-Checks und Update-Berichte.
- Wartungsmodus pro Mandant.
- Support-Freigabe durch Kundenadmin mit Ablaufzeit.
- Datenresidenz und Regionenauswahl für SaaS.
- Installationspakete oder Container-Distribution für On-Premise.

## Abhängigkeiten

- Lizenzierung
- Organisationen
- Rollen und Rechte
- Audit
- Anhänge und Storage
- Queue und Scheduler
- API und Integrationen
- Exporte und Reports
- Backups und Deployment

## GitHub Issues

- TBD

# Demo-, Testdaten und Musterbranchen

## Status

In Progress — [Demo-Mandant mit vollständigem Beispielauftrag](../demo-mandant.md)
konzipiert (MVP-050, Issue #49). MVP umgesetzt: `DemoSeederService` erzeugt je
**Musterbranche** (IT-Service, Elektro, Facility Management — `DemoIndustry`)
ein End-to-End-Szenario auf Basis des `BranchProfileInstaller` inkl. Material,
Asset, signiertem Abnahmeprotokoll, offenem Punkt und Kommunikationseintrag.
**Resetbarer Demo-Modus** über `demo:reset`/Admin-Aktion (idempotent, wirkt
ausschließlich auf `is_demo`-Orgs — echte Mandanten werden nie angefasst) und
**Demo-Anlage** über `demo:seed {org?} --industry=`/Admin-Aktion mit
Branchen-Auswahl. Offen: Prozedur-Run mit Backup-Proof/Vier-Augen-Schritt,
Anhänge (JPEG/PDF), eigenständige `freshDemoOrg`-Org-Anlage.

## Ziel

WorkDiary soll realistische Demo- und Testdaten sowie Musterkonfigurationen für
typische Branchen bereitstellen. Vertrieb, Entwicklung, Tests, Onboarding und
Kundendemos sollen damit reproduzierbar und verständlich werden.

## Warum

Ein leeres System zeigt den Produktwert schlecht. Musterbranchen helfen Kunden,
sich WorkDiary im eigenen Betrieb vorzustellen, und erleichtern QA für komplexe
Workflows.

## MVP

- Seed-Daten für Demo-Organisationen.
- Musterbranchen: IT-Service, Handwerk, Facility Management, Wartung/Service.
- Beispielkunden, Aufträge, Protokolle, Assets, Materialien, Zeiten, Rechnungen.
- Beispiel-SLA, Prozeduren, Checklisten und Auswertungen.
- Resetbarer Demo-Modus.

## Akzeptanzkriterien

- Demo-Daten enthalten keine echten Kundendaten.
- Demos zeigen vollständige End-to-End-Flows.
- Testdaten sind reproduzierbar.
- Musterkonfigurationen können als Startpunkt für neue Mandanten dienen.

## Abhängigkeiten

- Import, Migration und Onboarding
- Dokumentation und Abnahmeprotokolle
- Prozeduren, Arbeitsanweisungen und Checklisten
- Auswertungen und Entscheidungsgrundlagen

## GitHub Issues

- TBD

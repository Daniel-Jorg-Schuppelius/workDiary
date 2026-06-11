# Zertifizierungsmanagement und integriertes Managementsystem

## Status

Proposed

## Produktversprechen

WorkDiary unterstützt Organisationen dabei, zertifizierbare Managementsysteme
aufzubauen, zu betreiben und gegenüber internen sowie externen Prüfern
nachzuweisen. Anforderungen verschiedener Normen werden auf einer gemeinsamen
Basis aus Geltungsbereichen, Risiken, Maßnahmen, Dokumenten, Nachweisen,
Audits, Abweichungen und Managementbewertungen bearbeitet.

WorkDiary erteilt keine Zertifizierung und garantiert keine Konformität. Die
Organisation bleibt für ihren Geltungsbereich, ihre tatsächlichen Prozesse und
die Wirksamkeit ihrer Maßnahmen verantwortlich. Zertifizierungsentscheidungen
trifft ausschließlich eine unabhängige und dafür zuständige
Zertifizierungsstelle.

## Ziel

Die Software soll Unternehmen nicht nur Checklisten bereitstellen, sondern den
vollständigen Weg bis zur Zertifizierungsreife unterstützen:

1. Anwendbare Normen, Rechtsvorgaben und vertragliche Anforderungen bestimmen.
2. Organisation, Standorte, Prozesse und Geltungsbereiche abbilden.
3. Anforderungen Verantwortlichen und realen betrieblichen Abläufen zuordnen.
4. Risiken, Maßnahmen, Dokumente und Nachweise gemeinsam verwalten.
5. Wirksamkeit prüfen, interne Audits durchführen und Abweichungen bearbeiten.
6. Managementbewertungen und fortlaufende Verbesserung dokumentieren.
7. Einen freigegebenen Stand für Zertifizierungs- und Überwachungsaudits
   reproduzierbar bereitstellen.

## Priorisierte Normprofile

Die Normprofile werden nicht als getrennte Datensilos umgesetzt. Sie verwenden
denselben Managementsystem-Kern und ergänzen ihn um fachliche Anforderungen,
Auswertungen und Vorlagen.

1. **ISO/IEC 27001:2022:** Informationssicherheits-Managementsystem.
2. **ISO/IEC 27701:2025:** Datenschutz-Informationsmanagement.
3. **ISO 9001:** Qualitätsmanagement; Normrevisionen müssen versioniert
   unterstützt werden.
4. **ISO 22301:2019:** Business Continuity Management.
5. **ISO 45001:2018:** Arbeits- und Gesundheitsschutzmanagement.
6. **ISO 37301:2021:** Compliance-Managementsystem.
7. **ISO/IEC 42001:2023:** Managementsystem für Entwicklung und Einsatz von KI.

Ergänzende Leitfäden und Kontrollprofile wie ISO/IEC 27017, ISO/IEC 27018,
ISO/IEC 20000-1 oder ISO 31000 können Anforderungen und Maßnahmen ergänzen,
ohne zwingend ein eigenes Produktmodul zu bilden.

## Gemeinsamer Managementsystem-Kern

### Normen, Versionen und Anforderungen

- Normprofile mit Herausgeber, Ausgabe, Gültigkeitsstatus und Übergangsfrist.
- Anforderungen mit Referenznummer und eigener Kurzbeschreibung.
- Import lizenzierter Norminhalte durch die Organisation.
- Parallele Unterstützung alter und neuer Normfassungen während einer
  Übergangsfrist.
- Nachvollziehbare Migration zwischen Normversionen.
- Mapping einer betrieblichen Maßnahme auf mehrere Anforderungen und Normen.
- Keine ungeprüfte Auslieferung urheberrechtlich geschützter Normtexte.

### Geltungsbereiche und Organisation

- Mehrere Managementsystem-Geltungsbereiche je Organisation.
- Standorte, Gesellschaften, Prozesse, Produkte, Leistungen und Ausschlüsse.
- Relevante Parteien und deren Anforderungen.
- Rollen, Verantwortlichkeiten, Vertretungen und erforderliche Unabhängigkeit.
- Verknüpfung gemeinsamer und normspezifischer Leitlinien und Ziele.

### Risiken, Chancen und Verpflichtungen

- Gemeinsames Register mit normspezifischen Bewertungsmethoden.
- Risiken, Chancen, Rechtsanforderungen und vertragliche Verpflichtungen.
- Verantwortliche, Akzeptanzkriterien, Behandlung und Reviewtermine.
- Verknüpfung zu Prozessen, Assets, Lieferanten, Standorten und Maßnahmen.
- Freigegebene historische Bewertungen statt Überschreiben alter Stände.

### Maßnahmen und Nachweise

- Eine Maßnahme kann mehrere Normanforderungen erfüllen.
- Nachweise werden nur einmal geführt und kontrolliert wiederverwendet.
- Eigentümer, Freigabe, Gültigkeit, Vertraulichkeit und Reviewdatum.
- Prüfung der Angemessenheit und Wirksamkeit getrennt vom Umsetzungsstatus.
- Nachweislücken nach Norm, Geltungsbereich und Auditzeitraum.
- Versionierte Richtlinien, Verfahren, Protokolle, Verträge und technische
  Exporte.

### Audit und Verbesserung

- Mehrjähriges Auditprogramm und einzelne Auditpläne.
- Kriterien, Umfang, Stichproben, Auditoren und Unabhängigkeitsprüfung.
- Feststellungen, Nichtkonformitäten, Ursachen und Korrekturmaßnahmen.
- Wirksamkeitskontrolle und formeller Abschluss.
- Managementbewertung mit Eingaben, Entscheidungen und Folgemaßnahmen.
- Auditpakete und zeitlich begrenzter, lesender Prüferzugang.

## Zertifizierungsfreundliche Produktarchitektur

WorkDiary darf durch seine eigene Architektur die Zertifizierbarkeit einer
Organisation nicht unnötig erschweren. Dafür gelten produktweit folgende
Mindestanforderungen:

- **Datenhoheit:** vollständiger, dokumentierter Export der kundeneigenen
  Stamm-, Prozess- und Nachweisdaten in offenen oder verbreiteten Formaten.
- **Nachvollziehbarkeit:** manipulationsgeschützte Audit-Ereignisse für
  sicherheits-, freigabe- und nachweisrelevante Änderungen.
- **Historisierung:** freigegebene Stände bleiben stichtagsbezogen
  reproduzierbar; Korrekturen ersetzen keine bestehende Historie.
- **Zugriffsschutz:** Mandantentrennung, minimale Rechte, Rollentrennung,
  starke Authentisierung und kontrollierte Prüferzugänge.
- **Vertraulichkeit:** Klassifizierung, eingeschränkte Sichtbarkeit und
  geschützte Ablage sensibler Audit-, Personal- und Sicherheitsinformationen.
- **Integrität:** Hashes, Signaturen oder gleichwertige Verfahren für
  freigegebene Exporte und Auditpakete, wo dies fachlich erforderlich ist.
- **Verfügbarkeit:** dokumentierte Sicherung, Wiederherstellung,
  Wiederanlaufziele, Restore-Tests und Betriebsüberwachung.
- **Änderungsmanagement:** versionierte Releases, Migrationen,
  Sicherheitsinformationen, SBOM und nachvollziehbare Updatepfade.
- **Aufbewahrung und Löschung:** konfigurierbare Fristen, Legal Hold,
  dokumentierte Löschung und Konfliktbehandlung zwischen Nachweis- und
  Datenschutzpflichten.
- **Integrationen:** Schnittstellen dürfen Freigaben, Herkunft,
  Verantwortlichkeiten und Auditspuren nicht umgehen.
- **Konfigurationstransparenz:** sicherheits- und compliance-relevante
  Einstellungen sind exportierbar und Änderungen werden protokolliert.
- **Betriebsmodelle:** SaaS, Private Cloud und On-Premise haben dokumentierte
  Verantwortungsgrenzen zwischen Betreiber, Kunde und Dienstleistern.
- **Kein Lock-in bei Nachweisen:** Ein Kunde kann seine Auditunterlagen auch
  nach Vertragsende vollständig und lesbar übernehmen.

## Konformitätsstatus und Zertifikate

Die Anwendung unterscheidet strikt:

- `nicht bewertet`
- `Lückenanalyse durchgeführt`
- `in Umsetzung`
- `intern auditbereit`
- `externes Audit geplant`
- `zertifiziert`
- `Zertifikat ausgesetzt`
- `Zertifikat abgelaufen`

Nur ein hinterlegtes und geprüftes Zertifikat darf den Status `zertifiziert`
auslösen. Dafür werden mindestens Norm und Ausgabe, zertifizierte Organisation,
Geltungsbereich, Zertifizierungsstelle, Zertifikatsnummer, Ausstellungsdatum,
Gültigkeitszeitraum und Überwachungstermine dokumentiert.

Ein Reifegrad, eine vollständig ausgefüllte Checkliste oder das Fehlen offener
Maßnahmen darf niemals automatisch als Normkonformität oder Zertifizierung
bezeichnet werden.

## Architekturprinzipien

- Normanforderungen sind versionierte Referenzen und keine fest im Fachcode
  verdrahteten Wahrheitswerte.
- Fachobjekte wie Risiko, Maßnahme, Nachweis, Audit und Abweichung bleiben
  normneutral.
- Normspezifische Logik liegt in versionierten Profilen und Bewertungsregeln.
- Mandantenbezogene Anpassungen überschreiben keine globalen Referenzkataloge.
- Automatische Bewertungen erzeugen begründete Vorschläge oder Findings,
  aber keine endgültige Konformitätsentscheidung.
- Jede Freigabe enthält Person, Zeitpunkt, Gegenstand und zugrunde liegende
  Version.
- Exporte enthalten Erstellungszeitpunkt, Geltungsbereich, Filter,
  Datenversion und Integritätsnachweis.

## Akzeptanzkriterien

- Eine Organisation kann mehrere Normprofile und Geltungsbereiche verwalten.
- Eine Maßnahme und ein Nachweis können kontrolliert mehreren
  Normanforderungen zugeordnet werden.
- Der Stand einer Anforderung ist zu einem historischen Stichtag
  nachvollziehbar.
- Normrevisionen können parallel geführt und ihre Änderungen bewertet werden.
- Interne Audits, Nichtkonformitäten und Korrekturmaßnahmen bilden einen
  geschlossenen, prüfbaren Ablauf.
- Ein Auditpaket ist vollständig, versioniert, lesbar exportierbar und gegen
  nachträgliche unbemerkte Änderung geschützt.
- Die Anwendung behauptet ohne gültigen Zertifikatsnachweis weder Konformität
  noch Zertifizierung.
- Ein Datenexport ermöglicht die Übergabe relevanter Nachweise an Prüfer oder
  ein anderes System, ohne proprietäre WorkDiary-Oberflächen vorauszusetzen.
- Sicherheits- und compliance-relevante Konfigurationsänderungen sind
  nachvollziehbar.
- Für jedes unterstützte Betriebsmodell sind Verantwortungsgrenzen und
  technische Voraussetzungen dokumentiert.

## Abgrenzung

- Kein Ersatz für fachkundige Beratung, interne Auditoren oder
  Zertifizierungsstellen.
- Keine automatische Rechtsberatung oder verbindliche Rechtsauslegung.
- Keine Garantie, dass ein Unternehmen ein Zertifikat erhält.
- Keine Konformitätsaussage allein aufgrund gespeicherter Dokumente.
- Keine unlizenzierte Verteilung vollständiger Normtexte.
- Keine Beschränkung auf ISO-Normen; andere Regelwerke können denselben Kern
  verwenden.

## Abhängigkeiten

- [ISMS und ISO/IEC 27001-Auditbereitschaft](./044-isms-iso-27001-auditbereitschaft.md)
- [Datenschutzmanagement](./043-datenschutzmanagement-vvt-avv-betroffenenrechte.md)
- [Qualität, Sicherheit und Arbeitsschutz](./013-qualitaet-sicherheit-arbeitsschutz.md)
- [Backup, Restore und Disaster Recovery](./017-backup-restore-disaster-recovery.md)
- [Dokumentenmanagement](./031-dokumentenmanagement.md)
- [Prozeduren, Arbeitsanweisungen und Checklisten](./026-prozeduren-arbeitsanweisungen-checklisten.md)
- [Rollen, Rechte und Produktprofile](./019-rollen-rechte-produktprofile.md)
- [KI-Assistenz und intelligente Automatisierung](./025-ki-assistenz-automatisierung.md)

## GitHub Issues

- TBD

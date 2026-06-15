# ISMS und ISO/IEC 27001-Auditbereitschaft

## Status

In Progress — MVP1 KOMPLETT (2026-06-12). MVP1 umgesetzt (2026-06-10), am
2026-06-11 auf den 046-Kern refactort: Geltungsbereiche (isms_scopes),
versionierte Normanforderungen (isms_requirements, Annex-A-Katalog als
Normprofil ISO/IEC 27001:2022), normneutrale Maßnahmen mit
n:m-Anforderungs-Mapping, SoA als eigene Applicability-Statements je Scope
(inkl. Datenmigration). Zusätzlich umgesetzt: Softwareinventar
(Produkte/Installationen, EOL-Automatik) und Release-SBOM (CycloneDX 1.5,
sbom:generate, Admin-Komponentenübersicht).
Am 2026-06-11 (046-Inkrement C) ergänzt: interne Audits
(isms_audits) mit Feststellungen (isms_audit_findings), Korrekturmaßnahmen
inkl. Wirksamkeitsprüfung (isms_corrective_actions) und Managementbewertung
mit unveränderlicher Freigabe (isms_management_reviews); überfällige
Korrekturmaßnahmen meldet der Fristen-Scanner.
Am 2026-06-12 abgeschlossen: Auditbereitschafts-Dashboard je
Geltungsbereich (ReadinessService: SoA-Fortschritt je Norm, hohe Risiken,
überfällige Bewertungs-Reviews/unbewertete Risiken, überfällige
Korrekturmaßnahmen, offene Nichtkonformitäten, Nachweislücken,
Zertifikatstermine < 90 Tage, Software-EOL; KPI-Kacheln mit Drill-down,
erster Eintrag im ISMS-Menü) sowie JSON-/CSV-Direkt-Exporte für
Risikoregister, Anforderungen/SoA (je Scope) und Maßnahmen
(RegisterExportService: meta-Block mit Organisation/Scope/generated_at/
App-Version; CSV mit Semikolon + BOM). „Versioniert" im Sinne der
Exporte leistet der unveränderliche Auditpaket-Snapshot
(AuditPackageService); die Direkt-Exporte weisen den Datenstand
(generated_at) aus. Die Kennzahl „ungeprüfte Lieferanten" entfällt in
MVP1 bewusst — es gibt noch kein Lieferantenmodul (MVP 2).
Am 2026-06-14 (MVP2 „Betrieb und Wirksamkeit", Kernscheibe) ergänzt:
**Sicherheitsvorfälle** (`isms_security_incidents`) mit Lebenszyklus,
Verknüpfung zu Risiken/Maßnahmen (`isms_incident_risk`/`isms_incident_control`)
und synchroner Meldung kritischer Vorfälle an die Leitung; **Schwachstellen-
register** (`isms_vulnerabilities`) mit Kritikalität (CVSS-v3-ableitbar),
Verantwortung/Frist, Inventar-Bezug und Statusmaschine, wobei die
Ausnutzbarkeits-Entscheidung eine begründete Nutzeraktion ist (nie
automatisch) und überfällige Schwachstellen über `notifications:scan-deadlines`
(`isms.vulnerabilityOverdue`) gemeldet/eskaliert werden; **Advisory-Import
(CSAF/VEX)** nativ per `json_decode`, der betroffene Komponenten gegen das
Softwareinventar und die letzte Release-SBOM abgleicht (`known_affected` ⇒
offen + „in Untersuchung", `known_not_affected` ⇒ „nicht betroffen" mit
VEX-Begründung), Original-Advisory mit SHA-256 als Nachweis
(`isms_advisories`), Re-Import idempotent. Routen unter `compliance/isms`,
Gating `module.isms`, bestehende `isms.*`-Permissions.
Am 2026-06-14 (MVP2/3-Rest) ergänzt: **Lieferantenbewertung**
(`isms_supplier_assessments`) mit Kritikalitäts-/Risikoeinstufung
(`IncidentSeverity` wiederverwendet), geforderten Sicherheitsanforderungen,
Vertragsmerkmalen (NDA/AVV/Prüfungsrecht), wiederkehrenden Reviews und einer
Statusmaschine (draft→assessed→approved bzw. flagged). Der Supplier-Bezug ist
optional: nullable FK auf das bestehende `Supplier`-Stammdatenmodell ODER
Freitext-Name als Fallback; der AVV-Bezug zum Datenschutzmanagement bleibt
BEWUSST lose (Flag `has_dpa` + Freitext `dpa_ref`, KEIN FK auf die
Privacy-WIP-Tabellen). Überfällige Reviews (`next_review_on`, nicht
freigegeben) füllen die in MVP1 bewusst entfallene Kennzahl „ungeprüfte
Lieferanten" im Auditbereitschafts-Dashboard und werden über
`notifications:scan-deadlines` (`isms.supplierReviewOverdue`) gemeldet/
eskaliert. Zusätzlich: **Reifegrad-/Readiness-Assessment**
(`ReadinessAssessmentService`, Seite `isms.readiness`), das aus den
vorhandenen Registern je Domäne (SoA-Abdeckung, Risiken, Nachweise, Audits/
Korrekturen, Betrieb aus Vorfällen+Schwachstellen, Lieferanten) einen
Reifegrad (Ampel + Score 0–100 mit begründenden Signalen) und daraus eine
Gesamteinschätzung „intern auditbereit? ja/nein mit Begründung" ableitet.
WICHTIG (046-Prinzip): das Ergebnis ist ausschließlich eine begründete
SELBSTEINSCHÄTZUNG/Empfehlung — NIE eine automatische Konformitäts-
behauptung oder „zertifiziert"; ein prominenter Disclaimer kennzeichnet dies,
und der Service setzt keinerlei Konformitätsstatus.
Offen (MVP3-Rest): lizenzierter Katalog-/Normtext-Import, automatischer
Advisory-Feed, vollständige VEX-Profile.

Refactoring auf den gemeinsamen Managementsystem-Kern aus
[Feature 046](./046-zertifizierungsmanagement-integriertes-managementsystem.md)
(2026-06-11): `isms_scopes` (Default-Scope „Gesamtorganisation"),
`isms_requirements` (Annex A als versionierte Referenzen, norm/edition/
ref_no), `isms_control_requirement` (Maßnahme erfüllt n:m Anforderungen,
normübergreifend) und `isms_applicability_statements` (SoA je Scope mit
Anwendbarkeit, Begründung, Umsetzungsstatus, Nachweis). `isms_controls`
ist seitdem die normneutrale Maßnahme ohne Normreferenz/SoA-Felder;
Risiken tragen den Geltungsbereich (`isms_scope_id`).

## Produktversprechen

WorkDiary gibt Organisationen ein Werkzeug an die Hand, mit dem sie ein
Informationssicherheits-Managementsystem (ISMS) aufbauen, betreiben,
nachweisen und auf eine Zertifizierung nach ISO/IEC 27001:2022 vorbereiten
können.

WorkDiary zertifiziert keine Organisation und garantiert keine
Normkonformität. Die Konformitätsbewertung und Zertifizierung erfolgen durch
eine dafür zuständige, unabhängige Zertifizierungsstelle.

Das ISMS ist das erste Normprofil des gemeinsamen
[Zertifizierungsmanagements](./046-zertifizierungsmanagement-integriertes-managementsystem.md).
Fachobjekte und Nachweise werden deshalb so aufgebaut, dass weitere
Managementsystemnormen dieselbe Basis verwenden können und keine parallelen
Register entstehen.

## Ziel

Die Anwendung soll nicht nur eine Kontroll-Checkliste anbieten, sondern den
vollständigen Managementkreislauf unterstützen:

1. Kontext, Geltungsbereich und Verantwortlichkeiten bestimmen.
2. Informationswerte und Risiken erfassen und bewerten.
3. Risikobehandlung und erforderliche Maßnahmen beschließen.
4. Maßnahmen umsetzen und mit Nachweisen belegen.
5. Wirksamkeit prüfen, interne Audits durchführen und Abweichungen bearbeiten.
6. Managementbewertung dokumentieren und das ISMS fortlaufend verbessern.

Damit kann ein Unternehmen seinen tatsächlichen ISMS-Stand erkennen und
begründet auf ein Zertifizierungsaudit hinarbeiten.

## Fachliche Bereiche

### ISMS-Kontext und Geltungsbereich

- Organisation, Standorte, Leistungen, Prozesse und relevante Parteien.
- Dokumentierter ISMS-Geltungsbereich mit Ein- und Ausschlüssen.
- Rechtliche, regulatorische, vertragliche und kundenspezifische
  Anforderungen.
- Informationssicherheitsleitlinie, Ziele und messbare Kennzahlen.
- Rollen für Geschäftsleitung, ISMS-Verantwortliche, Asset Owner,
  Risk Owner, Control Owner und interne Auditoren.

### Informationswerte und Schutzbedarf

- Register für Informationen, Systeme, Anwendungen, Geräte, Standorte,
  Dienstleister und wesentliche Geschäftsprozesse.
- Verantwortliche Person und unterstützende Organisationseinheiten.
- Schutzbedarf für Vertraulichkeit, Integrität und Verfügbarkeit.
- Abhängigkeiten zwischen Assets, Prozessen, Lieferanten und
  Verarbeitungstätigkeiten.
- Verknüpfung mit bestehenden WorkDiary-Assets, Software,
  Organisationseinheiten und Datenschutzdaten.

### Softwareinventar und Software-Stückliste

WorkDiary unterscheidet zwei Ebenen:

1. Das organisationsbezogene Softwareinventar zeigt, welche Produkte und
   Versionen auf welchen Assets, Servern oder Diensten eingesetzt werden.
2. Die produktbezogene Software Bill of Materials (SBOM) zeigt, welche
   Komponenten und Abhängigkeiten in einer konkreten WorkDiary-Version
   enthalten sind.

Für jede ausgelieferte WorkDiary-Version wird automatisiert eine
maschinenlesbare SBOM erzeugt und unveränderlich dem Release zugeordnet.
Sie umfasst mindestens:

- WorkDiary-Version, Build-Hash und Erstellungszeitpunkt.
- PHP-, Laravel-, Datenbank- und Laufzeitversionen.
- Composer- und NPM-Abhängigkeiten einschließlich transitiver
  Abhängigkeiten und exakter Versionen aus den Lock-Dateien.
- Aktivierte WorkDiary-Module und Plugins mit Implementierungs- und
  Schema-Version.
- Komponentenname, Anbieter, Paketkennung, Version, Hashes und Lizenzangaben,
  soweit zuverlässig ermittelbar.
- Abhängigkeitsbeziehungen zwischen Primärprodukt, Modulen, Plugins und
  Bibliotheken.

Als Austauschformat wird CycloneDX bevorzugt; ein SPDX-Export kann zusätzlich
angeboten werden. Die fachlichen Mindestanforderungen orientieren sich an der
jeweils unterstützten Fassung der BSI TR-03183-2.

Die SBOM ist kein Schwachstellenbericht. Schwachstelleninformationen ändern
sich dynamisch und werden separat mit der verwendeten Produktversion
abgeglichen. Ergebnisse werden als Betroffenheitsprüfung dokumentiert und
können über Security Advisories, CSAF oder VEX begründet werden.

Eine administrative Komponentenübersicht zeigt:

- installierte, aktivierte und deaktivierte Module und Plugins,
- aktuelle und verfügbare Version,
- Support- und End-of-Life-Status,
- bekannte potenzielle Betroffenheiten,
- Ergebnis der fachlichen Exploitability-Prüfung,
- erforderliche Aktualisierung, Ausnahme oder Risikobehandlung.

Die vollständige Komponentenliste und Schwachstellendetails sind nur für
berechtigte Personen sichtbar. Öffentliche SBOMs oder Advisories werden
bewusst pro Release freigegeben.

### Risikomanagement

- Konfigurierbare Bewertungsmethode mit Eintrittswahrscheinlichkeit,
  Auswirkung und Akzeptanzkriterien.
- Bedrohungen, Schwachstellen, bestehende Maßnahmen und Risikoeigentümer.
- Brutto-, Netto- und Zielrisiko mit nachvollziehbarer Berechnung.
- Risikobehandlung: vermeiden, vermindern, übertragen oder akzeptieren.
- Genehmigung von Restrisiken mit Ablauf- und Reviewdatum.
- Änderungsverlauf und stichtagsbezogener Export des Risikoregisters.

### Maßnahmen und Statement of Applicability

- Zentraler Maßnahmenkatalog mit Verantwortlichen, Status, Fälligkeit,
  Wirksamkeit und Nachweisen.
- Mapping eigener Maßnahmen zu ISO/IEC 27001:2022 sowie optional zu weiteren
  Regelwerken.
- Statement of Applicability (SoA) mit Anwendbarkeit, Begründung,
  Umsetzungsstatus und Nachweisverweisen.
- Verknüpfung zu Risiken, Informationswerten, Richtlinien,
  Datenschutz-TOMs, Verträgen und Vorfällen.
- Versionierung und Freigabe, damit ein Audit den Stand zu einem Stichtag
  nachvollziehen kann.

Die Anwendung darf urheberrechtlich geschützte Normtexte nicht ungeprüft
mitliefern. Vollständige Norm- und Control-Texte werden nur aus einer
zulässigen, vom Kunden lizenzierten Quelle übernommen. WorkDiary kann eigene
Kurzbezeichnungen, Referenznummern und kundeneigene Beschreibungen verwalten.

### Dokumente und Nachweise

- Richtlinien, Verfahrensanweisungen, Prüfberichte, Schulungsnachweise,
  Protokolle, Verträge, Zertifikate und technische Exporte.
- Dokumenteneigner, Freigabe, Gültigkeit, Review- und Ablaufdatum.
- Eindeutige Verknüpfung eines Nachweises mit Maßnahmen und Risiken.
- Kennzeichnung fehlender, veralteter oder nicht freigegebener Nachweise.
- Zugriffsschutz für vertrauliche Sicherheitsinformationen.

### Sicherheitsvorfälle und Schwachstellen

- Informationssicherheitsvorfälle unabhängig vom Personenbezug.
- Verknüpfung zu Datenschutzvorfällen, falls personenbezogene Daten
  betroffen sind, ohne die Fallakten zusammenzulegen.
- Bewertung, Eindämmung, Ursachenanalyse, Kommunikation und Lessons Learned.
- Maßnahmenverfolgung und Rückführung in Risiko- und Kontrollbewertungen.
- Schwachstellenregister mit Kritikalität, Verantwortlichkeit und Fristen.
- Automatisierter Abgleich von Softwareinventar und Release-SBOMs mit
  Security Advisories und Schwachstellenquellen.
- Dokumentierte Entscheidung, ob eine gefundene Komponenten-Schwachstelle
  in der konkreten WorkDiary- oder Kundenkonfiguration ausnutzbar ist.
- Import und Export maschinenlesbarer Advisories, insbesondere CSAF und VEX.

### Lieferanten und Verträge

- Kritikalitäts- und Risikobewertung von Lieferanten.
- Anforderungen an Informationssicherheit, Meldewege, Verfügbarkeit,
  Prüfungsrechte und Unterauftragnehmer.
- Übersicht fehlender, ablaufender oder ungeprüfter Sicherheitsnachweise
  und Vertragsklauseln.
- Wiederverwendung von AVV, GVV, TOM-Anlagen und
  Unterauftragsverarbeiterregister aus dem Datenschutzmanagement.

### Interne Audits und Managementbewertung

- Mehrjähriges Auditprogramm, Auditumfang, Kriterien und Auditoren.
- Wahrung der Unabhängigkeit interner Auditoren.
- Feststellungen, Nichtkonformitäten, Beobachtungen und Verbesserungen.
- Ursachenanalyse, Korrekturmaßnahmen, Verantwortliche und
  Wirksamkeitsprüfung.
- Managementbewertung mit Eingaben, Entscheidungen und freigegebenem
  Protokoll.

### Auditbereitschaft

- Dashboard je ISMS-Geltungsbereich mit Reifegrad und offenen Lücken.
- Drill-down von einer Normanforderung zu Risiko, Maßnahme, Nachweis,
  Auditfeststellung und Verantwortlichem.
- Kennzahlen für überfällige Reviews, hohe Restrisiken, fehlende Nachweise,
  offene Nichtkonformitäten und ungeprüfte Lieferanten.
- Auditpaket für einen gewählten Stichtag mit SoA, Risikoregister,
  Richtlinienverzeichnis, Auditnachweisen und Maßnahmenstatus.
- Optionaler zeitlich begrenzter, lesender Prüferzugang.

## Branchenprofile

WorkDiary kann Branchenprofile als Startpunkt bereitstellen, zum Beispiel für
IT-Service, Managed Services, Handwerk, Pflege oder Facility Management.
Diese Profile enthalten typische Assets, Risiken, Nachweise und
Vertragsanforderungen.

Profile sind Vorlagen und keine pauschale Konformitätsentscheidung. Der
konkrete Geltungsbereich, die Risiken und die angemessenen Maßnahmen müssen
jede Organisation selbst bestimmen und freigeben.

## MVP 1: ISMS-Grundlage

- ISMS-Geltungsbereich, Rollen und Informationssicherheitsziele.
- Informationswert- und Risikoregister.
- Risikobehandlungsplan und Maßnahmenverwaltung.
- SoA mit Anwendbarkeit, Begründung, Status und Nachweisen.
- Dokumenten- und Nachweislückenübersicht.
- Dashboard für hohe Risiken, überfällige Maßnahmen und fehlende Nachweise.
- Softwareinventar mit Produkt, Installation, Version, Verantwortlichem und
  Supportstatus.
- Releasebezogene WorkDiary-SBOM aus `composer.lock`, `package-lock.json`,
  Modulen und Plugins.
- Geschützte Komponenten- und Versionsübersicht für Administratoren.
- Versionierte JSON-, CSV- und druckbare Exporte.

## MVP 2: Betrieb und Wirksamkeit

- Sicherheitsvorfälle und Schwachstellenmanagement.
- SBOM-/Inventarabgleich mit Advisories, dokumentierter Betroffenheit und
  Patch- oder Risikobehandlungsworkflow.
- Lieferantenbewertung und Vertragslücken.
- Interne Audits, Nichtkonformitäten und Korrekturmaßnahmen.
- Kennzahlen, Wirksamkeitsprüfungen und Managementbewertung.
- Wiederkehrende Reviews und Eskalationen.

### Umsetzungsstand MVP 2 (Kern umgesetzt)

Die Kernbausteine „Betrieb und Wirksamkeit" sind umgesetzt; interne Audits,
Nichtkonformitäten und Managementbewertung sind bereits aus Feature 046
(gemeinsamer Managementsystem-Kern) vorhanden.

**Umgesetzt:**

- **Sicherheitsvorfälle** (`isms_security_incidents`): Vorfallregister
  unabhängig vom Personenbezug, mit Kategorie/Kritikalität, Statusmaschine
  (gemeldet → Bewertung → eingedämmt → bereinigt → wiederhergestellt →
  geschlossen). Der Abschluss erzwingt Ursachenanalyse **und** Lessons
  Learned. Rückführung in Risiken/Maßnahmen über die Pivots
  `isms_incident_risk` / `isms_incident_control`. Die Datenschutz-Kopplung ist
  bewusst lose: ein Flag „personenbezogene Daten betroffen" weist auf die
  **separate** Datenschutzmeldung hin (Fallakten werden nicht zusammengelegt);
  ein optionaler Freitext-Verweis (`privacy_incident_ref`, kein FK auf die
  Privacy-WIP-Tabelle) referenziert den zugehörigen Datenschutzvorfall.
  Neue **kritische** Vorfälle melden synchron an die Leitung.
- **Schwachstellenregister** (`isms_vulnerabilities`): Kritikalität (aus
  CVSS-v3 ableitbar), Verantwortung, Frist, Inventar-Bezug
  (`isms_software_product_id`) und Statusmaschine. Die **Ausnutzbarkeits-
  Entscheidung** ist eine bewusste, begründete Nutzeraktion (Pflichtnotiz bei
  „ausnutzbar"/„nicht ausnutzbar"); überfällige Schwachstellen werden über den
  Fristen-Scanner gemeldet und eskaliert.
- **Advisory-Import + Abgleich (CSAF/VEX)**: nativer Parse per `json_decode`
  (kein zusätzliches Paket). Betroffene Komponenten werden gegen das
  Softwareinventar (Name/Version) und optional gegen die letzte Release-SBOM
  (`storage/app/sbom/workdiary-latest.cdx.json`, CycloneDX) abgeglichen.
  `known_affected` ⇒ offener Eintrag mit Ausnutzbarkeit „in Untersuchung"
  (**nie automatisch ausnutzbar**); `known_not_affected` (VEX) ⇒ „nicht
  betroffen"/„nicht ausnutzbar" mit der VEX-Begründung als Pflichtnotiz. Das
  Original-Advisory wird mit SHA-256 als Nachweis in `isms_advisories`
  abgelegt; der Re-Import derselben Datei ist idempotent.

**Offen / Folgeinkremente:**

- **Lieferantenbewertung und Vertragslücken** (Kritikalitäts-/Risikobewertung
  von Lieferanten, Sicherheitsnachweise, AVV/GVV-Wiederverwendung) — am
  2026-06-14 umgesetzt (`isms_supplier_assessments`, loser Supplier-/
  AVV-Bezug; überfällige Reviews speisen die Kennzahl „ungeprüfte
  Lieferanten"). Wiederverwendung des AVV bleibt ein loser Verweis (Flag +
  Freitext), bis das Datenschutzmodul stabil ist.
- Vollständige VEX-Profile (CycloneDX-VEX, Status `under_investigation`/`fixed`
  als eigene Workflows) und ein automatischer Advisory-Feed (Pull statt
  Upload).
- Vollständiger SBOM-Abhängigkeitsgraph für den Komponentenabgleich
  (derzeit flache Komponentenliste).

## MVP 3: Auditvorbereitung

- Stichtagsbezogene Auditpakete.
- Zeitlich begrenzter Prüferzugang.
- Reifegrad- und Readiness-Assessment mit begründeten Feststellungen — am
  2026-06-14 umgesetzt (`ReadinessAssessmentService`, Seite `isms.readiness`):
  Reifegrad je Domäne (Ampel/Score aus den Registern) plus begründete
  Gesamtempfehlung; ausdrücklich Selbsteinschätzung, nie „zertifiziert".
- Mapping zu weiteren Regelwerken, ohne Nachweise zu duplizieren.
- Import eines kundenseitig lizenzierten Norm- und Maßnahmenkatalogs.

## Gemeinsame Nachweisbasis

Das ISMS verwendet bestehende WorkDiary-Daten, statt parallele Register
anzulegen:

- Datenschutz-TOMs als Maßnahmen und Nachweise.
- Datenschutzvorfälle als verknüpfte Spezialfälle.
- AVV, GVV und Dienstleisterprüfungen als Lieferantennachweise.
- Backup- und Restore-Tests als Verfügbarkeitsnachweise.
- Rollen-, Zugriffs- und Auditprotokolle als Kontrollnachweise.
- Qualifikationen und Unterweisungen als Kompetenznachweise.
- Prozeduren und Checklisten als dokumentierte Abläufe.

## Kernmodell

- `isms_scopes`
- `isms_requirements`
- `isms_assets`
- `isms_software_products`
- `isms_software_installations`
- `isms_software_bills`
- `isms_software_components`
- `isms_component_relations`
- `isms_vulnerability_assessments`
- `isms_risks`
- `isms_risk_assessments`
- `isms_risk_treatments`
- `isms_controls`
- `isms_control_mappings`
- `isms_control_evidence`
- `isms_applicability_statements`
- `isms_security_incidents`
- `isms_supplier_assessments`
- `isms_audits`
- `isms_audit_findings`
- `isms_corrective_actions`
- `isms_management_reviews`

## Akzeptanzkriterien

- Eine Organisation kann den freigegebenen ISMS-Geltungsbereich benennen.
- Eine WorkDiary-Installation kann ihre App-, Modul-, Plugin- und
  Komponentenversionen für einen Release-Stichtag nachweisen.
- Für jede veröffentlichte WorkDiary-Version existiert eine eindeutig
  zugeordnete, maschinenlesbare SBOM.
- Eine potenzielle Schwachstelle wird nicht automatisch als ausnutzbar
  bezeichnet; die Betroffenheitsentscheidung ist begründet und freigegeben.
- Jedes wesentliche Risiko hat Risk Owner, Bewertung, Behandlung und
  Reviewdatum.
- Das SoA erklärt für jede verwaltete Referenz die Anwendbarkeit und verweist
  auf Umsetzung und Nachweise.
- Fehlende oder veraltete Nachweise sind in einer priorisierten Übersicht
  sichtbar.
- Maßnahmen und Nichtkonformitäten haben Verantwortliche, Fristen und
  Wirksamkeitsprüfungen.
- Ein Auditpaket bildet den freigegebenen Stand zu einem gewählten Stichtag
  unveränderlich ab.
- WorkDiary bezeichnet eine Organisation nur dann als zertifiziert, wenn ein
  gültiges Zertifikat mit Geltungsbereich, Zertifizierungsstelle und
  Gültigkeitszeitraum hinterlegt wurde.

## Abgrenzung

- Keine Zertifizierung durch WorkDiary.
- Keine automatische Behauptung von Normkonformität.
- Keine allgemeingültige Risikobewertung allein aus dem Gewerk.
- Kein Ersatz für unabhängige interne Audits oder Zertifizierungsaudits.
- Keine unlizenzierte Auslieferung vollständiger ISO-Normtexte.
- Eine SBOM allein gilt nicht als Schwachstellen- oder Sicherheitsnachweis.
- Produktweite Anforderungen, durch die WorkDiary die Zertifizierbarkeit
  seiner Kunden nicht behindern darf, sind in
  [Feature 046](./046-zertifizierungsmanagement-integriertes-managementsystem.md)
  verbindlich beschrieben.

## Abhängigkeiten

- Datenschutzmanagement: VVT, AVV und Betroffenenrechte
- Datenschutz, Sicherheit und Datenlebenszyklus
- Dokumentenmanagement
- Prozeduren, Arbeitsanweisungen und Checklisten
- Backup, Restore und Disaster Recovery
- Rollen, Rechte und Produktprofile
- Benachrichtigungen und Eskalationen
- Gewerke- und Branchenprofile
- Zertifizierungsmanagement und integriertes Managementsystem

## Fachliche Referenzen

- ISO/IEC 27001:2022: Anforderungen an ein
  Informationssicherheits-Managementsystem.
- ISO/IEC 27002:2022: Leitfaden für Informationssicherheitsmaßnahmen.
- ISO-Übersicht zu ISO/IEC 27001:
  <https://www.iso.org/standard/82875.html>
- ISO-Übersicht zu ISO/IEC 27002:
  <https://www.iso.org/standard/75652.html>
- BSI TR-03183: Cyber-Resilienz-Anforderungen an Hersteller und Produkte,
  insbesondere Teil 2 zur Software Bill of Materials:
  <https://www.bsi.bund.de/dok/TR-03183>
- BSI TR-03191: maschinenlesbare Security Advisories im CSAF-Format:
  <https://www.bsi.bund.de/dok/TR-03191>

## GitHub Issues

- TBD

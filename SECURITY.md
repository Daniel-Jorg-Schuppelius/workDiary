# Sicherheitsrichtlinie

Die Sicherheit von WorkDiary ist besonders wichtig, weil die Anwendung
personenbezogene Daten, Arbeitszeiten, Dokumente und betriebliche Nachweise
verarbeiten kann. Bitte melde mögliche Sicherheitslücken vertraulich und gib
den Maintainern Gelegenheit, sie zu untersuchen und zu beheben.

## Unterstützte Versionen

WorkDiary befindet sich vor dem ersten stabilen Release. Derzeit wird nur der
aktuelle Stand des Hauptbranches mit Sicherheitskorrekturen versorgt.

| Version                  | Sicherheitsupdates |
| ------------------------ | ------------------ |
| `main`                   | Ja                 |
| Ältere Commits und Forks | Nein               |

Nach der Veröffentlichung stabiler Releases wird diese Tabelle um die konkret
unterstützten Versionslinien ergänzt. Betreiber eigener oder veränderter
Installationen sind dafür verantwortlich, Sicherheitskorrekturen zeitnah zu
übernehmen.

## Sicherheitslücke vertraulich melden

Veröffentliche Sicherheitslücken, Exploit-Code, Zugangsdaten oder
personenbezogene Daten nicht in öffentlichen Issues, Discussions oder Pull
Requests.

Bevorzugter Meldeweg ist GitHubs private Sicherheitsberichterstattung:

[Sicherheitslücke privat melden](https://github.com/Daniel-Jorg-Schuppelius/workDiary/security/advisories/new)

Falls dieser Meldeweg nicht verfügbar ist, eröffne ein öffentliches Issue, das
ausschließlich um einen vertraulichen Kontaktweg bittet. Nenne dort keine
technischen Details zur Schwachstelle.

## Benötigte Angaben

Eine Meldung sollte, soweit möglich, folgende Informationen enthalten:

- betroffene Version, Commit-ID und Betriebsumgebung,
- betroffene Komponente, Route, Funktion oder Konfiguration,
- Art und mögliche Auswirkungen der Schwachstelle,
- nachvollziehbare Schritte zur Reproduktion,
- notwendige Voraussetzungen und erforderliche Berechtigungen,
- vorhandenen Proof of Concept in möglichst ungefährlicher Form,
- Vorschläge zur Behebung oder Risikominderung, sofern vorhanden,
- gewünschte Namensnennung bei einer späteren Veröffentlichung.

Entferne echte Zugangsdaten, personenbezogene Daten und vertrauliche
Kundeninformationen. Verwende synthetische Testdaten und teile Geheimnisse nur,
wenn sie für die Untersuchung zwingend erforderlich sind.

## Umgang mit Meldungen

Die Maintainer versuchen:

- den Eingang innerhalb von drei Werktagen zu bestätigen,
- innerhalb von zehn Werktagen eine erste Bewertung abzugeben,
- mindestens alle 14 Tage über den Bearbeitungsstand zu informieren,
- bestätigte Schwachstellen nach Risiko und Ausnutzbarkeit zu priorisieren.

Diese Zeiträume sind Ziele der Community-Pflege und keine garantierten
Service-Level. Vereinbarte Reaktionszeiten können Bestandteil kommerzieller
Support- und Wartungsleistungen sein.

Bei einer bestätigten Schwachstelle koordinieren die Maintainer nach Möglichkeit
eine Korrektur, Tests, ein Security Advisory und die Veröffentlichung der
betroffenen sowie behobenen Versionen. Eine CVE kann beantragt werden, wenn
Schweregrad und Verbreitung dies rechtfertigen.

## Verantwortungsvolle Offenlegung

Bitte:

- greife nur auf Systeme und Daten zu, für deren Prüfung du berechtigt bist,
- nutze keine Schwachstelle über das für den Nachweis notwendige Maß hinaus,
- verändere oder lösche keine fremden Daten,
- führe keine Denial-of-Service-, Social-Engineering- oder Phishing-Tests durch,
- veröffentliche Details erst nach Abstimmung oder nachdem eine angemessene
  Frist zur Behebung verstrichen ist.

Diese Richtlinie erteilt keine Erlaubnis, fremde oder produktive Installationen
zu testen. Sie ersetzt keine gesetzlichen Vorgaben und begründet kein
Bug-Bounty-Programm oder einen Anspruch auf Vergütung.

## Nicht sicherheitsrelevante Fehler

Normale Fehler ohne Sicherheitsauswirkung können über die öffentliche
[Bug-Report-Vorlage](https://github.com/Daniel-Jorg-Schuppelius/workDiary/issues/new?template=bug_report.md)
gemeldet werden. Hinweise zur Mitarbeit stehen in
[CONTRIBUTING.md](CONTRIBUTING.md).

# Qualität, Sicherheit und Arbeitsschutz

## Status

In Progress — MVP teilweise umgesetzt (Sicherheitsereignis-Register,
Qualifikations-/Unterweisungs-Ablaufwarnung, Sicherheits-Auswertung;
Pflichtchecklisten je Auftragstyp über das bestehende Prozedursystem).

## Umsetzungsstand (Feature 013)

- **Sicherheitsereignis-Register** (umgesetzt): `safety_events` mit laufender
  `event_no` je Organisation, Art (Unfall/Beinaheunfall/Gefährdung/Mangel),
  Schweregrad, Sofortmaßnahme, Ursachenanalyse und Statusmaschine
  (gemeldet → in Untersuchung → Maßnahmen definiert → geschlossen; Abschluss
  erfordert `root_cause`). Modell `App\Models\SafetyEvent`,
  `App\Services\Safety\SafetyEventService`, `SafetyEventController` + Routen
  `safety-events.*`, Liste/Detail/Modale, `SafetyEventPolicy`, Foto-Nachweise
  über `HasAttachments`. Kritische Ereignisse (Unfall ODER Schweregrad
  kritisch) feuern synchron `NotificationEvent::SafetyCriticalEvent` an die
  Leitung. Beim Schließen kann ein `OpenIssue` als Folgemaßnahme angelegt
  werden (Wiederverwendung des Offene-Punkte-Systems).
- **PSA-/Unterweisungs- & Qualifikationsstatus** (umgesetzt, additiv): Das
  bestehende Qualifikationsmodell (Pivot `user_qualifications` mit
  `valid_from`/`valid_until`) wird genutzt. Neuer Scanner-Pfad in
  `ScanDeadlinesCommand` (`NotificationEvent::QualificationExpiring`) warnt
  Person + Teamleitung vor ablaufenden Qualifikationen/Unterweisungen
  (Vorlauf `--expiring-days`). Pivot-Modell `App\Models\UserQualification`
  für stabile Dedup-Subjekte. „Unterweisungen" werden als Qualifikationsart
  abgebildet (keine Parallelmechanik).
- **Sicherheits-/Qualitätscheckliste je Auftragstyp** (über Bestand,
  Feature 026): Pflichtchecklisten/Vier-Augen-Prüfungen laufen über die
  Prozedurvorlagen. `ProcedureApplicabilityResolver` ordnet Vorlagen über
  `applicability.diary_entry_type` (EntryType-Slug) zu; `SecondPersonGate`
  erzwingt die zweite Person. Es wird KEINE Parallelmechanik gebaut — das
  Hilfe-Topic verlinkt den Prozedur-Designer.
- **Auswertung** (umgesetzt): `Reporting\SafetyReportController` +
  View `reports.safety` (Ereignisse je Art/Schweregrad im Zeitraum, offen vs.
  geschlossen), Menüeintrag unter Auswertungen → Team (gegated auf
  `safety.viewAny`).

Plan-Gating: bewusst ungated (Core-Arbeitsschutz, wie OpenIssue); Steuerung
ausschließlich über Permissions `safety.viewAny` / `safety.report` /
`safety.manage`.

## Ziel

WorkDiary soll Qualitäts-, Sicherheits- und Arbeitsschutzanforderungen in
Aufträge, Protokolle und Dienstplanung integrieren. Dazu gehören Checklisten,
Unterweisungen, Prüfpflichten, PSA, Gefährdungen, Sicherheitsfreigaben und
Ereignisdokumentation. Verbindliche Prozeduren sollen sicherstellen, dass
kritische Arbeiten nur nach festgelegten Schritten, mit passenden Nachweisen und
ggf. mit zweiter Person durchgeführt werden.

## Warum

In vielen Einsatz- und Serviceumgebungen geht es nicht nur um Leistung und
Zeit. Firmen müssen nachweisen, dass Mitarbeitende qualifiziert, unterwiesen
und mit passenden Schutzmaßnahmen unterwegs waren. Sicherheits- und
Qualitätsdaten sind außerdem wichtig für Audit, Haftung und kontinuierliche
Verbesserung.

## MVP

- Sicherheits- und Qualitätschecklisten pro Auftragstyp.
- Pflichtprozeduren für kritische Tätigkeiten.
- Pflichtbestätigung für PSA, Einweisung oder Gefährdung.
- Unterweisungs- und Qualifikationsstatus am Mitarbeitenden.
- Sperrhinweis, wenn Pflichtqualifikation fehlt.
- Ereignisprotokoll für Unfall, Beinaheunfall, Mangel oder Eskalation.
- Auswertung von Sicherheits- und Qualitätsauffälligkeiten.

## Akzeptanzkriterien

- Kritische Arbeiten können Pflichtchecks erzwingen.
- Kritische Tätigkeiten können Schrittfolgen, Nachweise und Vier-Augen-Prüfung
  erzwingen.
- Fehlende Qualifikationen oder Unterweisungen sind vor Einsatz sichtbar.
- Sicherheitsereignisse sind nachvollziehbar dokumentiert.
- Qualitäts- und Sicherheitsdaten fließen in Auswertungen ein.

## Abhängigkeiten

- Dokumentation und Abnahmeprotokolle
- Prozeduren, Arbeitsanweisungen und Checklisten
- Qualifikationen
- Dienstplan-Intelligenz
- Auswertungen und Entscheidungsgrundlagen
- Audit

## GitHub Issues

- TBD

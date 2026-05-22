# Prozedur-Abweichungen

Status: Aktiv (MVP-029, Issue #29) • Quelle:
[Feature 026 — Prozeduren](features/026-prozeduren-arbeitsanweisungen-checklisten.md).
• Aufbauend auf: [Prozedurvorlagen](prozedurvorlagen.md) (MVP-025),
[Pflicht/Reihenfolge](prozedur-pflicht.md) (MVP-026),
[Backup-Nachweis](prozedur-backup.md) (MVP-027),
[Vier-Augen](prozedur-vier-augen.md) (MVP-028).

## 1. Zweck

Wenn ein Schritt nicht wie vorgesehen ausgeführt werden kann (z. B.
„Backup nicht möglich", „Material nicht verfügbar", „Bauteil weicht
ab"), muss die Abweichung **strukturiert** dokumentiert und —
optional — in eine **Folgeaktion** überführt werden. Abweichungen
bleiben revisionssicher, fließen in Analysen ein und sind kein
„Häkchen-Vergessen".

## 2. Tabelle `procedure_deviations`

```sql
CREATE TABLE procedure_deviations (
    id                          BIGINT PRIMARY KEY AUTO_INCREMENT,
    organization_id             BIGINT NOT NULL,
    procedure_step_run_id       BIGINT NOT NULL UNIQUE,
    deviation_type              VARCHAR(40) NOT NULL,    -- siehe §3
    severity                    VARCHAR(20) NOT NULL,    -- low|medium|high|critical
    reason_text                 TEXT NOT NULL,           -- Pflicht, >= 20 Zeichen
    proposed_action             VARCHAR(40) NULL,        -- siehe §4
    open_issue_id               BIGINT NULL,             -- bei Folgeaktion: Verweis
    follow_up_diary_entry_id    BIGINT NULL,             -- optional
    risk_accepted_by_user_id    BIGINT NULL,
    risk_accepted_at            TIMESTAMP NULL,
    created_by_user_id          BIGINT NOT NULL,
    created_at                  TIMESTAMP NOT NULL,
    updated_at                  TIMESTAMP NOT NULL,
    INDEX idx_org_type (organization_id, deviation_type, severity)
);
```

Der `procedure_step_runs.deviation_id` (aus MVP-025 §3.5) zeigt zurück
auf diese Tabelle.

## 3. Abweichungstypen (`deviation_type`)

| Code                  | Bedeutung                                            |
| --------------------- | ---------------------------------------------------- |
| `not_applicable`      | Schritt im konkreten Fall nicht anwendbar.           |
| `not_possible`        | Schritt war technisch / organisatorisch unmöglich.   |
| `partial`             | Schritt nur teilweise erfüllbar.                     |
| `alternative_method`  | Alternativer Weg gewählt, Ziel erreicht.             |
| `failed_check`        | Prüfwert außerhalb Toleranz (z. B. Messung NOK).     |
| `material_substitute` | Anderes Material / Bauteil verwendet als vorgesehen. |
| `safety_block`        | Schritt wegen Sicherheitsbedenken abgebrochen.       |
| `customer_decline`    | Kunde lehnt Schritt ab.                              |

## 4. Vorgeschlagene Folgeaktion (`proposed_action`)

| Code                  | Wirkung                                              |
| --------------------- | ---------------------------------------------------- |
| `none`                | Keine Folgeaktion nötig (Default bei `not_applicable`). |
| `open_issue`          | [Offener Punkt](offene-punkte.md) wird angelegt.    |
| `new_diary_entry`     | Neuer Auftrag wird angelegt.                         |
| `requalify`           | Run erneut durchlaufen.                              |
| `escalate`            | Eskalation an Org-Admin / Sicherheitsverantwortlichen. |

## 5. Service `DeviationRecorder`

`record(stepRun, payload)`:

1. Validiert: `reason_text` ≥ 20 Zeichen, `severity` setzbar je nach
   `deviation_type` (Standard-Mapping siehe §6).
2. Schreibt `procedure_deviations`.
3. Setzt `procedure_step_runs.status = 'deviated'`,
   `deviation_id = <neu>`.
4. Führt `proposed_action` aus:
   - `open_issue` → ruft
     [`OpenIssueService::create()`](offene-punkte.md) mit
     `source_type = procedureDeviation`,
     `source_id = procedure_deviations.id`.
   - `new_diary_entry` → erzeugt Folgeauftrag, verlinkt
     `follow_up_diary_entry_id`.
   - `escalate` → Notification an Org-Admin.
5. Wenn `severity = critical` und keine `proposed_action != none`:
   blockiert Run-Complete (`error.code = "procedure.criticalDeviationOpen"`),
   bis `risk_accepted_at` durch berechtigte Rolle gesetzt ist.

## 6. Default-Severity

| `deviation_type`        | Default `severity` |
| ----------------------- | ------------------ |
| `not_applicable`        | low                |
| `alternative_method`    | low                |
| `partial`               | medium             |
| `material_substitute`   | medium             |
| `not_possible`          | high               |
| `failed_check`          | high               |
| `safety_block`          | critical           |
| `customer_decline`      | medium             |

Default ist überschreibbar (mit Audit).

## 7. Run-Complete-Regeln

Ergänzend zu [Pflicht/Reihenfolge §4](prozedur-pflicht.md):

- `deviated`-Schritte zählen als finaler Status, **wenn** sie eine
  abgeschlossene Abweichung haben.
- Kritische Abweichungen ohne `proposed_action` blocken Abschluss
  (s. §5 Punkt 5).
- `risk_accepted_at` schaltet Abschluss frei, erzeugt aber ein
  prominentes `procedure.criticalRiskAccepted` Audit-Event.

## 8. UI

- Im Schritt: Button „Abweichung erfassen" öffnet Dialog mit
  `deviation_type`-Auswahl, Pflicht-Begründung, optionalem Vorschlag
  Folgeaktion.
- Im Run: Abweichungs-Panel listet alle Abweichungen mit
  Severity-Pill und Verweisen auf erzeugte Offene-Punkte / Folge-
  Aufträge.
- Auf Auftragsdetailseite: Badge „X Abweichungen" wenn > 0.

## 9. Permissions

| Permission                            | Wer                              |
| ------------------------------------- | -------------------------------- |
| `procedure.deviation.record`          | Ausführender Mitarbeiter.        |
| `procedure.deviation.acceptRisk`      | Org-Admin / QM.                  |
| `procedure.deviation.update`          | Recorder + Org-Admin.            |
| `procedure.deviation.view`            | Wer Run sehen darf.              |

Customer-Portal: Abweichungen mit `customer_decline` und `severity`
≤ `medium` sind sichtbar; alles weitere nur intern.

## 10. Audit-Events

`procedure.deviationRecorded`,
`procedure.deviationUpdated`,
`procedure.deviationActionTriggered` (mit `action`-Detail),
`procedure.criticalRiskAccepted`,
`procedure.runCompletionRejected` (mit `reason = "criticalDeviationOpen"`).

## 11. Akzeptanzkriterien

1. Tabelle + Service implementiert; `reason_text` < 20 Zeichen wird
   abgelehnt (`error.code = "procedure.deviationReasonTooShort"`).
2. Folgeaktionen erzeugen entsprechende Entitäten und verknüpfen sie
   per FK.
3. Kritische Abweichung ohne Risk-Accept blockt Run-Complete; mit
   Risk-Accept ist Abschluss möglich und im Audit sichtbar.
4. Reports listen Top-N Abweichungstypen pro Vorlage (Vorbereitung
   für spätere Analyse-MVPs).
5. Customer-Portal-Sichtbarkeit gemäß §9 getestet.

## 12. Out-of-scope (MVP-029)

- Automatische Mustererkennung wiederkehrender Abweichungen.
- KPI-Dashboard für Abweichungs-Trends — Folge.
- Lessons-learned-Workflow (zurück zur Vorlage) — Folge.

## 13. Folge

Damit ist der Prozedur-Cluster (MVP-025..029) im Konzept
abgeschlossen.

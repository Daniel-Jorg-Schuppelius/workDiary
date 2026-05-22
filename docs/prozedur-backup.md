# Nachweistyp Backup

Status: Aktiv (MVP-027, Issue #27) • Quelle:
[Feature 026 — Prozeduren](features/026-prozeduren-arbeitsanweisungen-checklisten.md).
• Aufbauend auf: [Prozedurvorlagen](prozedurvorlagen.md) (MVP-025),
[Pflicht/Reihenfolge](prozedur-pflicht.md) (MVP-026).

## 1. Zweck

Für Update- und Change-Prozeduren muss bewiesen werden, dass **vor**
der Änderung ein **funktionsfähiges Backup** vorlag — nicht nur als
Freitext, sondern als verifizierbares Artefakt.

## 2. Schritttyp `backup`

In `procedure_step_defs.step_type = 'backup'` mit `config`:

```json
{
  "backup_scope": "config|database|fullSystem|customScript",
  "min_size_kb": 1,
  "max_age_minutes": 60,
  "verify_method": "checksum|restoreCheck|managerConfirmation",
  "checksum_algo": "sha256",
  "storage_target": "attachment|external"
}
```

## 3. Tabelle `procedure_backup_proofs`

```sql
CREATE TABLE procedure_backup_proofs (
    id                          BIGINT PRIMARY KEY AUTO_INCREMENT,
    procedure_step_run_id       BIGINT NOT NULL UNIQUE,
    backup_scope                VARCHAR(40) NOT NULL,
    source_label                VARCHAR(180) NOT NULL,    -- "Router-CFG", "DB schuppeliusd"
    taken_at                    TIMESTAMP NOT NULL,
    size_bytes                  BIGINT NOT NULL,
    checksum_algo               VARCHAR(20) NULL,
    checksum_value              VARCHAR(128) NULL,
    storage_target              VARCHAR(20) NOT NULL,     -- attachment|external
    attachment_id               BIGINT NULL,              -- wenn target=attachment
    external_ref                VARCHAR(255) NULL,        -- z. B. URI/Pfad
    verified                    BOOLEAN NOT NULL DEFAULT 0,
    verified_at                 TIMESTAMP NULL,
    verified_by_user_id         BIGINT NULL,
    verify_method               VARCHAR(40) NOT NULL,
    verify_note                 TEXT NULL,
    created_at                  TIMESTAMP NOT NULL
);
```

## 4. Service `BackupProofService`

- `register(stepRun, payload)` legt den Nachweis an, validiert
  `min_size_kb`, `max_age_minutes` und (bei `verify_method = checksum`)
  die Prüfsumme.
- `verify(proof, method, note)` setzt `verified = true`. `checksum`
  ist automatisch verifizierbar; `restoreCheck` und
  `managerConfirmation` erfordern eine zweite Person mit
  `procedure.backup.verify`-Permission.
- `release(stepRun)` markiert den `backup`-Schritt als `done`,
  **nur wenn** `verified = true` ist.

## 5. Pflicht-Verkettung

- Vor jedem Schritt mit `config.requires_prior_backup = true` wird im
  `ProcedureExecutionService::canExecute` (siehe MVP-026 §3) zusätzlich
  geprüft, dass ein `backup`-Schritt im gleichen Run mit Status `done`
  existiert und `taken_at` innerhalb `max_age_minutes` liegt.
- Bei Verstoß → HTTP 409 `error.code = "procedure.backupMissingOrExpired"`.

## 6. Storage-Regeln

- `storage_target = attachment`: Datei landet im polymorphen
  `attachments`-Bucket des Subjects (DiaryEntry / Asset). Antiviren-
  Scan und MIME-Whitelist gemäß
  [Anhänge-Konzept](features/004-anhaenge-und-storage.md).
- `storage_target = external`: nur Metadaten (URI, Größe, Hash). Der
  externe Pfad wird im Audit referenziert, das Artefakt selbst liegt
  außerhalb (z. B. Backup-Server, S3).

## 7. Permissions

| Permission                        | Wer                                    |
| --------------------------------- | -------------------------------------- |
| `procedure.backup.register`       | Mitarbeitende, die Run ausführen.      |
| `procedure.backup.verify`         | Zweite Person mit Rolle / Qualifikation. |
| `procedure.backup.viewExternal`   | Org-Admin (URI sichtbar).              |

## 8. Audit-Events

`procedure.backupRegistered`, `procedure.backupVerified`,
`procedure.backupRejected` (mit Begründung).

## 9. Akzeptanzkriterien

1. Tabelle §3 + Service §4 implementiert.
2. `backup`-Schritt kann nur `done` werden, wenn `verified = true`.
3. Folge-Schritte mit `requires_prior_backup` werden bei fehlendem /
   veraltetem Backup geblockt.
4. Checksum-Verifizierung gegen hochgeladene Datei (bei
   `storage_target = attachment`) ist automatisch.
5. UI zeigt Backup-Karte mit Größe, Zeitstempel, Verifikations-Status
   und Verify-Button (für Berechtigte).
6. Tests: fehlender Nachweis, abgelaufener Nachweis, falsche Checksum,
   externer Nachweis ohne URI.

## 10. Out-of-scope (MVP-027)

- Automatische Backups durch Integration in externe Backup-Tools.
- Restore-Test-Automatisierung.
- Verschlüsselungs-Nachweis.

## 11. Folge

- MVP-028 Vier-Augen für `verify`-Schritte.
- MVP-029 Abweichung „Backup nicht möglich, Begründung X".

# Pflichtschritte und Reihenfolge

Status: Aktiv (MVP-026, Issue #26) • Quelle:
[Feature 026 — Prozeduren](features/026-prozeduren-arbeitsanweisungen-checklisten.md).
• Aufbauend auf: [Prozedurvorlagen](prozedurvorlagen.md) (MVP-025).

## 1. Zweck

Sicherstellen, dass kritische Schritte einer Prozedur **nicht
übersprungen** und in der vorgesehenen **Reihenfolge** ausgeführt
werden. Soft-Hinweise reichen nicht: die Service-Schicht blockiert
unzulässige Aktionen.

## 2. Begriffe

| Begriff        | Bedeutung                                                                    |
| -------------- | ---------------------------------------------------------------------------- |
| `required`     | Schritt MUSS einen finalen Status haben, bevor der Run als `completed` gilt. |
| `blocking`     | Folgeschritte sind gesperrt, bis dieser Schritt einen finalen Status hat.    |
| Finaler Status | `done`, `n_a`, `failed`, `deviated` (mit Vorbedingungen).                    |
| Offener Status | `pending`, `blocked`.                                                        |

`required` ist unabhängig von `blocking`: Ein optionaler Schritt kann
sehr wohl `blocking` sein (z. B. „Optionalen Funktionstest
durchführen, wenn ja, dann mit Ergebnis"), aber das ist die Ausnahme.

## 3. Sperr-Logik

`ProcedureExecutionService::canExecute(step_run)` prüft pro Schritt:

1. Run-Status ist `inProgress` oder `open`.
2. Alle vorherigen `blocking`-Schritte (`sort_order < current`) haben
   finalen Status.
3. Rollen-/Qualifikationsanforderung des Schritts ist beim ausführenden
   User erfüllt (siehe [Prozedurvorlagen](prozedurvorlagen.md) §3.3).
4. Wenn `requires_second_person = true`, ist Zweite Person zugewiesen
   (siehe MVP-028) — Ausführung bleibt sonst `pending`.

Verstöße führen zu:

- API → HTTP 409 mit `error.code = "procedure.stepBlocked"` und
  `error.reason = "previousStepIncomplete"|"missingQualification"|…`.
- UI → Schritt-Button `disabled` mit Tooltip aus i18n
  `procedure.blocked.<reason>`.

## 4. Pflicht-Check für Run-Abschluss

`procedure.runComplete` setzt voraus:

- Alle `required = true`-Schritte haben einen der finalen Status
  `done|n_a|failed|deviated`.
- Bei `failed`/`deviated`-Schritten muss eine Folgeaktion oder
  Abweichungsbegründung dokumentiert sein (MVP-029).
- `risk_level = critical` Vorlagen: zusätzlich mindestens **einer**
  Vier-Augen-Freigabe-Schritt im Status `done` (MVP-028).

Wenn nicht erfüllt: `error.code = "procedure.runIncomplete"` mit Liste
fehlender Schritte.

## 5. Reihenfolge

- `sort_order` aus `procedure_step_defs` ist die kanonische
  Reihenfolge.
- Mehrere Schritte mit gleichem `sort_order` (selten genutzt) gelten
  als „parallel zulässig" — `blocking` wirkt dann gruppenweise.
- Reihenfolge-Änderungen erfordern eine neue Vorlagen-Version.

## 6. UI

### 6.1 Schritt-Liste

- Vertikale Liste, gesperrte Schritte mit Schloss-Icon
  (`lock` Material Symbol).
- Tooltip „Vorheriger Pflichtschritt offen: '{Label}'" .
- Aktuelle Schritt-Karte hervorgehoben.

### 6.2 Tastatur

- `n` → nächster offener Schritt fokussieren.
- `Enter` → primäre Aktion (z. B. „Bestätigen").
- `s` → Schritt als `n_a` markieren (nur bei `required = false`).

## 7. Audit-Events

`procedure.stepUnlocked` (wenn `blocking`-Vorgänger erledigt wurde),
`procedure.stepBlocked` (System; bei fehlgeschlagenem Execute-Versuch),
`procedure.runCompletionRejected` (mit Liste).

## 8. Akzeptanzkriterien

1. `ProcedureExecutionService::canExecute` implementiert die vier
   Prüfungen aus §3 mit Tests.
2. Run kann nur abgeschlossen werden, wenn alle Pflichtschritte
   finalisiert sind und kritische Runs eine Vier-Augen-Freigabe haben.
3. UI sperrt unzulässige Schritte mit `disabled` + Tooltip.
4. API liefert strukturierten Fehler mit `error.reason`.
5. Reihenfolge-Änderung erzeugt neue Version (kein In-Place-Update).
6. Tests decken: blockierte Folge, fehlende Qualifikation, fehlende
   Zweite Person, Run-Complete-Block.

## 9. Out-of-scope (MVP-026)

- Bedingte Verzweigungen (if/then) — Folge.
- Zeit-basierte Sperren (z. B. „mindestens 10 min nach Schritt X") —
  Folge.

## 10. Folge

- MVP-027 Backup-Nachweistyp.
- MVP-028 Zweite Person / Freigeber.
- MVP-029 Abweichungen.

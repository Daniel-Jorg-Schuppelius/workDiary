# Vier-Augen-Prinzip: Zweite Person / Freigeber

Status: Aktiv (MVP-028, Issue #28) • Quellen:
[Feature 026 — Prozeduren](features/026-prozeduren-arbeitsanweisungen-checklisten.md),
[Feature 013 — Qualität / Sicherheit](features/013-qualitaet-sicherheit-arbeitsschutz.md).
• Aufbauend auf: [Prozedurvorlagen](prozedurvorlagen.md) (MVP-025),
[Pflicht/Reihenfolge](prozedur-pflicht.md) (MVP-026).

## 1. Zweck

Schritte, die laut Vorlage `requires_second_person = true` (oder
`step_type = 'freigabe'`) tragen, dürfen **nur dann** als `done`
markiert werden, wenn eine **zweite Person** mit passender Rolle den
Schritt vor Ort/digital gegenzeichnet.

## 2. Datenmodell

`procedure_step_runs` (aus MVP-025 §3.5) enthält bereits:

- `second_person_user_id`
- `second_person_signed_at`

Ergänzung pro Vorlage in `procedure_step_defs.config`:

```json
{
  "second_person_role": "qm|teamlead|electrician|customer|any",
  "second_person_min_qualification": "swk_400v|...",
  "second_person_self_exclusion": true,
  "signature_required": true,
  "evidence_required": false
}
```

`second_person_self_exclusion = true` (Default) verhindert, dass die
ausführende Person identisch zur zweiten Person ist.

## 3. Zuweisung und Signatur

Ablauf:

1. Ausführender klickt „Zweite Person anfordern" → `step_run.status`
   bleibt `pending`, System legt `procedure.secondPersonRequested`
   Audit-Event an und (optional) sendet In-App-Notification an
   passende Rollen.
2. Berechtigter Empfänger öffnet den Run, sieht Karte „Freigabe
   nötig", drückt „Übernehmen" → `second_person_user_id` wird gesetzt
   (`procedure.secondPersonAssigned`).
3. Empfänger prüft und signiert (`procedure.secondPersonSigned` mit
   `second_person_signed_at`).
4. Erst jetzt darf der Ausführende den Schritt auf `done` setzen
   (`procedure.stepCompleted`).

## 4. Service `SecondPersonGate`

`SecondPersonGate::ensure(stepRun)` blockt `done`-Transition, wenn:

- `def.requires_second_person = true` UND
- (`second_person_user_id IS NULL` ODER
  `second_person_signed_at IS NULL`).

Verstoß → HTTP 409 `error.code = "procedure.secondPersonMissing"`.

Selbst-Ausschluss → HTTP 409 `error.code =
"procedure.secondPersonSelfNotAllowed"`.

## 5. Rollen und Qualifikationen

Validierung beim Übernehmen-Klick:

- `second_person_role`: User hat die Rolle (Plattform-/Org-Rolle laut
  Spatie).
- `second_person_min_qualification`: User hat eine gültige
  Qualifikation mit Code = oder >= geforderter Stufe (siehe
  [Feature 013 — Qualifikationen](features/013-qualitaet-sicherheit-arbeitsschutz.md)).

## 6. UI

- Auf der Schritt-Karte: Banner „Freigabe durch zweite Person nötig"
  mit Status-Pill `wartet|übernommen|signiert`.
- Avatar + Name der zweiten Person nach Übernahme.
- Signatur per Knopfdruck (passwortfreie Re-Auth via aktueller
  Session-Token, optional E-Mail-Link für externe Freigeber wie
  Kunden).
- Sichtbarkeit: Vier-Augen-Schritte sind in der Schritt-Liste mit
  `groups`-Icon markiert.

## 7. Permissions

| Permission                              | Wer                              |
| --------------------------------------- | -------------------------------- |
| `procedure.secondPerson.request`        | Ausführender Mitarbeiter.        |
| `procedure.secondPerson.take`           | Inhaber der geforderten Rolle.   |
| `procedure.secondPerson.sign`           | Wer übernommen hat.              |
| `procedure.secondPerson.revoke`         | Org-Admin (mit Begründung).      |

## 8. Audit-Events

`procedure.secondPersonRequested`,
`procedure.secondPersonAssigned`,
`procedure.secondPersonSigned`,
`procedure.secondPersonRevoked` (changes.reason).

## 9. Akzeptanzkriterien

1. `SecondPersonGate` blockt `done`-Transition zuverlässig (Tests:
   ohne Person, ohne Signatur, Selbst-Ausschluss, fehlende Rolle).
2. Audit-Kette §8 vollständig und in `audit_logs` sichtbar.
3. UI zeigt Banner, Status-Pill und Übernahme-Button rollenabhängig.
4. Externe Freigeber (Kunden) erhalten optional E-Mail-Link
   (passwortfrei, 7 Tage gültig, einmalig — analog
   [Abnahme & Signatur](abnahme-signatur.md) §4 emailLink).
5. Revoke einer Signatur setzt `second_person_signed_at = NULL`,
   Schritt fällt auf `pending` zurück, Folge-Schritte werden
   geblockt.

## 10. Out-of-scope (MVP-028)

- Mehr-Augen-Prinzip (n > 2).
- Eskalations-Workflow bei nicht-übernommenen Freigaben.
- Asynchrone Freigaben mit SLA-Timer.

## 11. Folge

- MVP-029 Abweichungen / Folgeaktion.

# Defekt-/Sperrstatus

Status: Aktiv (MVP-038, Issue #38) • Quelle:
[Feature 009 — Inventar / Dienstmittel / Assets](features/009-inventar-dienstmittel-assets.md).
• Aufbauend auf: [Asset-Stammdaten](asset-stammdaten.md) (MVP-035),
[Asset-Verknüpfungen](asset-verknuepfungen.md) (MVP-036).

## 1. Zweck

Defekte oder gesperrte Assets dürfen nicht unbemerkt weiterverwendet
oder eingeplant werden. Der Status muss überall sofort sichtbar sein
und Aktionen blocken, die mit einem nicht-einsatzfähigen Asset nicht
zulässig sind.

## 2. Modell

`assets.status` (aus MVP-035 §4) enthält bereits `blocked`,
`inRepair`, `inMaintenance`. Ergänzung:

```sql
ALTER TABLE assets
  ADD COLUMN blocked_reason VARCHAR(40) NULL AFTER status,
  ADD COLUMN blocked_note TEXT NULL,
  ADD COLUMN blocked_at TIMESTAMP NULL,
  ADD COLUMN blocked_by_user_id BIGINT NULL,
  ADD COLUMN blocked_until DATE NULL;
```

`blocked_reason` Werte: `defect`, `safety`, `recall`,
`inspectionOverdue`, `lost`, `policyHold`, `other`.

## 3. Service `AssetBlockingService`

- `block(asset, reason, note, until = null)` setzt Status `blocked`
  und füllt `blocked_*`-Felder, Audit `asset.blocked`.
- `unblock(asset, note)` setzt Status zurück auf vorherigen Status
  (gemerkt in `audit_logs.changes.previousStatus`), Audit
  `asset.unblocked`.
- `flagDefect(asset, severity, note, autoBlock = false)` setzt
  `health = critical` und (optional) ruft `block(reason="defect")`.

## 4. Sperr-Verhalten

| Aktion                                                | bei `status = blocked` / `inRepair`                                                                                                  |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| Asset als `tool` einem Auftrag verlinken              | Hard-Block (HTTP 409 `asset.notUsable`).                                                                                             |
| Asset als `subject` verlinken                         | Erlaubt (Reparatur braucht das Asset).                                                                                               |
| Material auf Asset buchen                             | Erlaubt nur bei `inRepair`.                                                                                                          |
| Prozedur-Run starten                                  | Hard-Block, außer Prozedur ist als „darf an gesperrten Assets laufen" markiert (`procedure_template_versions.allow_blocked = true`). |
| Ausgabe / Ausleihe                                    | Hard-Block.                                                                                                                          |
| Eintrag im Customer-Portal als „aktiv"-Gerät anzeigen | Nein — Pill „in Wartung".                                                                                                            |

Service: `AssetUsageGuard::ensureUsable(asset, action)`.

## 5. Hinweis auf Fälligkeit

Wenn `next_inspection_on <= heute` oder `next_maintenance_on <=
heute`, wird `health = degraded` und Asset bekommt UI-Warnung „Prüfung
überfällig" (kein Hard-Block, außer Branchenprofil setzt explizit
`block_on_overdue = true` → dann `block(reason="inspectionOverdue")`
durch Scheduled Job `AssetOverdueBlockJob` täglich).

## 6. UI

- Asset-Karten und Listen: rote Pill „Gesperrt: {reason}" oder
  gelbe Pill „Wartung überfällig".
- Asset-Detailseite: Banner oben mit `blocked_reason`, Note,
  `blocked_until` und Button „Sperre aufheben" (rolle abhängig).
- Suchergebnisse: gesperrte Assets ans Ende, Filter „Auch gesperrte
  zeigen" Default off.
- Verknüpfungs-Dialog (`asset.link.create`): gesperrte Assets in der
  Suche sichtbar, aber Auswahl löst Inline-Warnung mit Begründung
  aus.

## 7. Permissions

| Permission              | Wer                                                                                 |
| ----------------------- | ----------------------------------------------------------------------------------- |
| `asset.block`           | Org-Admin, Teamleitung.                                                             |
| `asset.unblock`         | Org-Admin, Teamleitung.                                                             |
| `asset.flagDefect`      | Mitarbeitende.                                                                      |
| `asset.useDespiteBlock` | nur Org-Admin (Override mit Pflicht-Begründung; auditiert als `asset.useOverride`). |

## 8. Audit-Events

`asset.blocked`, `asset.unblocked`, `asset.defectFlagged`,
`asset.useBlockedByGuard` (System), `asset.useOverride` (Admin).

## 9. Akzeptanzkriterien

1. Spalten §2, Service §3 implementiert.
2. `AssetUsageGuard::ensureUsable` blockt die Aktionen aus §4 mit
   strukturierten Fehlern.
3. UI zeigt Sperrstatus überall (Listen, Detail, Suchdialog).
4. Fälligkeits-Job degraded/blockt korrekt; Tests mit Fixtures.
5. Override durch Admin auditiert mit Pflicht-Begründung
   (`reason_text` ≥ 20 Zeichen).
6. Customer-Portal zeigt gesperrte Kundenobjekte mit Status-Pill.

## 10. Out-of-scope (MVP-038)

- Automatische Eskalation an Eigentümer (Mail-Versand).
- Asset-Tausch-Workflow.
- Reservierungskonflikt-Auflösung.

## 11. Folge

Damit ist der Asset-Cluster (MVP-035..038) im Konzept abgeschlossen.
Nächster Cluster: Analyse / Reports (MVP-039..043).

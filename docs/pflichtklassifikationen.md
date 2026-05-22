# Pflichtklassifikationen pro Auftragstyp

Status: Aktiv (MVP-032, Issue #32) • Quellen:
[Feature 024 — Klassifikationen / Tags / Datenqualität](features/024-klassifikationen-tags-datenqualitaet.md),
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).
• Aufbauend auf: [Kernklassifikationen](kernklassifikationen.md) (MVP-030),
[Kategorien pro Org](kategorien-org.md) (MVP-031).

## 1. Zweck

Pro Auftragstyp (`entry_type`) festlegen, **welche** anderen
Klassifikations-Domänen verpflichtend zu setzen sind, **wann**
(Erstellung, Abschluss) und **mit welchem Geltungsbereich**.

## 2. Tabelle `classification_requirements`

```sql
CREATE TABLE classification_requirements (
    id                  BIGINT PRIMARY KEY AUTO_INCREMENT,
    organization_id     BIGINT NOT NULL,
    entry_type_code     VARCHAR(60) NOT NULL,        -- Code aus classifications(domain=entry_type)
    required_domain     VARCHAR(40) NOT NULL,        -- z. B. defect_type, root_cause
    enforce_phase       VARCHAR(20) NOT NULL,        -- onCreate|beforeComplete|beforeSign
    severity            VARCHAR(20) NOT NULL,        -- hard|soft
    allow_multi         BOOLEAN NOT NULL DEFAULT 0,  -- mehrere Werte zulassen
    min_count           INT NOT NULL DEFAULT 1,
    max_count           INT NULL,
    only_if_json        JSON NULL,                   -- optionale Bedingung
    note                VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL,
    updated_at          TIMESTAMP NOT NULL,
    UNIQUE KEY uniq_req (organization_id, entry_type_code, required_domain, enforce_phase)
);
```

`only_if_json` Beispiel: `{"priority":["high","critical"]}` —
Anforderung greift nur bei Hoch-/Kritisch-Priorität.

## 3. Phasen

| `enforce_phase` | Bedeutung                                            |
| --------------- | ---------------------------------------------------- |
| `onCreate`      | Wert muss beim Anlegen gesetzt sein.                 |
| `beforeComplete`| Wert muss vor Statusübergang nach „erledigt" gesetzt sein. |
| `beforeSign`    | Wert muss vor Protokoll-Signatur gesetzt sein.       |

## 4. Schweregrad

| `severity` | Verhalten                                            |
| ---------- | ---------------------------------------------------- |
| `hard`     | Aktion wird verweigert (HTTP 409 `classification.requirementMissing`). |
| `soft`     | Aktion wird zugelassen, aber Datenqualitäts-Hinweis im UI + Eintrag in `data_quality_warnings` (siehe später Feature 024). |

## 5. Service `ClassificationRequirementValidator`

`validate(DiaryEntry $entry, string $phase): array<RequirementResult>`

Implementierung:

1. Lade alle Requirements für `(org, entry.type_code, phase)`.
2. Filter mit `only_if_json` gegen aktuelle Entry-Werte.
3. Zähle vorhandene Werte für `required_domain` am Entry.
4. Werte < `min_count` oder > `max_count`: Result mit
   `severity = hard|soft`.

Aufrufer:

- `DiaryEntryService::create()` → Phase `onCreate`.
- `DiaryEntryService::complete()` → Phase `beforeComplete`.
- `ProtocolService::sign()` → Phase `beforeSign`.

## 6. UI

- Im Auftrag-Formular: rote Sternchen an Pflicht-Klassifikations-
  Feldern, Tooltip „Pflicht, weil Auftragstyp = {label}".
- Im Statuswechsel-Dialog: Liste fehlender Pflichtwerte; bei `hard`
  ist Button deaktiviert.
- Datenqualität-Panel im Auftrag listet offene `soft`-Warnungen.

## 7. Branchenprofil-Integration

Branchenprofile (siehe [IT-Service](branchenprofil-it.md) MVP-033 und
[Handwerk/Service](branchenprofil-handwerk.md) MVP-034) bringen ihre
Default-Pflichtregeln als Seed mit. Org-Admin kann sie nach Import
anpassen (UI in MVP-031 erweitert um Tab „Pflichtregeln").

## 8. Permissions

| Permission                                    | Wer        |
| --------------------------------------------- | ---------- |
| `classification.requirement.view`             | Mitglieder |
| `classification.requirement.manage`           | Org-Admin  |

## 9. Audit-Events

`classification.requirement.created/updated/deleted`,
`classification.requirementMissing` (System-Audit bei `hard`-Block).

## 10. Akzeptanzkriterien

1. Tabelle §2 + Validator §5 mit Tests pro Phase und Severity.
2. `only_if_json` korrekt ausgewertet (Bedingungsmatch / Mismatch).
3. UI markiert Pflichtfelder und blockt Statuswechsel bei `hard`.
4. Branchenprofile seed-bar (vorbereitet für MVP-033/034).
5. `soft`-Verstöße erzeugen sichtbare Warnungen, blocken aber nicht.

## 11. Out-of-scope (MVP-032)

- Rule-Engine mit Skript-Ausdrücken (nur `only_if_json` AND-Matrix).
- Auto-Fill aus Vorlagen.

## 12. Folge

- MVP-033 Branchenprofil IT-Service.
- MVP-034 Branchenprofil Handwerk/Service allgemein.

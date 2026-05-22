# Protokollpunkt-Typen

Status: Aktiv (MVP-021, Issue #21) • Quellen:
[Feature 003 — Abnahmeprotokolle](features/003-dokumentation-abnahmeprotokolle.md),
[Feature 032 — Vorlagen-/Formularsystem](features/032-vorlagen-formularsystem.md).
• Aufbauend auf:
[Protokoll-Datenmodell](protokoll-datenmodell.md) (MVP-020).

## 1. Zweck

Definition der zulässigen **Punkt-Typen** für ein `protocol_items.item_type`.
Jeder Typ legt fest:

- Welcher Wert (`value_json`) erlaubt ist (Schema).
- Welche Validierung greift (`required`, Wertebereich).
- Welches `result` (`ok|notok|n_a|open`) sinnvoll ist.
- Welche Rendering-Komponente in der UI verwendet wird.

## 2. Globale Felder pro Punkt

Wiederholung aus MVP-020 §3.2, jetzt mit Typ-spezifischer Semantik:

| Feld         | Beschreibung                                                |
| ------------ | ----------------------------------------------------------- |
| `item_type`  | siehe §3.                                                   |
| `label`      | Bezeichnung („Druckmessung Vorlauf").                       |
| `description`| Erklärung/Hilfetext.                                        |
| `required`   | Punkt MUSS gefüllt sein, sonst kein `requestReview/sign`.   |
| `value_json` | typisierter Wert (siehe §3).                                |
| `result`     | OK-Status, abgeleitet ODER explizit pro Typ.                |
| `note`       | Freie Anmerkung.                                            |

## 3. Punkt-Typen

### 3.1 `group` — Gruppen-/Abschnittsheader

Strukturiert die Punkteliste. Keine `value_json`, kein `result`. Kinder
via `parent_item_id`. Beispiel: Abschnitt „Heizung".

### 3.2 `text` — Freitext

```json
{ "text": "…" }
```

`required` und Mindest-/Maximallänge konfigurierbar. `result` =
`ok|notok|n_a` optional.

### 3.3 `boolean` — Ja/Nein-Punkt

```json
{ "value": true|false }
```

Mapping: `true→ok`, `false→notok` (überschreibbar). Klassische Checkliste.

### 3.4 `choice` — Auswahl aus Liste

```json
{ "selected": "value-a", "options": [{"key":"value-a","label":"…"}] }
```

`result` ergibt sich aus konfigurierbarem Mapping `selected → result`.

### 3.5 `multichoice` — Mehrfachauswahl

```json
{ "selected": ["a","b"], "options": [...] }
```

`result`: `ok` wenn alle erforderlichen ausgewählt, sonst `notok`.

### 3.6 `number` — Messwert / Zähler

```json
{ "value": 12.5, "unit": "bar" }
```

Konfigurierbar: `min`, `max`, `tolerance_min`, `tolerance_max`, `unit`.
`result = ok` wenn im Toleranzbereich, sonst `notok`.

### 3.7 `range` — Soll-Bereich mit Ist-Wert

Wie `number`, zusätzlich `nominal` und Anzeige als Skala.

### 3.8 `date` / `datetime`

```json
{ "value": "2025-01-15T14:30:00Z" }
```

Validierung: Bereich (`min_date`/`max_date`).

### 3.9 `signature` — In-line Unterschrift

Verweist auf `protocol_signatures` (MVP-022). `value_json` enthält
`signature_id`. Punkt wird `ok` nach erfolgter Unterschrift.

### 3.10 `photo` — Pflichtfoto

```json
{ "attachment_ids": [123, 124] }
```

Validierung: `min_count`, `max_count`. Vorher-/Nachher-Semantik in
MVP-023 §3.

### 3.11 `file` — Pflichtdokument

Wie `photo`, aber MIME-Whitelist konfigurierbar (z. B. nur PDF).

### 3.12 `defect` — Mangel

```json
{
  "severity": "low|medium|high|critical",
  "category": "leak|electric|cosmetic|…",
  "description": "…",
  "open_issue_id": 4711
}
```

Erzeugt automatisch einen Eintrag in [`open_issues`](offene-punkte.md)
(MVP-024). `result = notok`. `severity` und `category` aus
organisationsweiter Klassifikationsliste (MVP-030 ff.).

### 3.13 `measurement.timestamped` — Messreihe

```json
{ "samples": [{"at": "…", "value": 12.5}, ...], "unit": "°C" }
```

Für Wartungs-/Prüfprotokolle. Bewertung wie `number` über Aggregat
(min/max/avg).

### 3.14 `procedure_step` — Verbindlicher Schritt

Verknüpft mit Prozedurvorlage (MVP-025 ff.). Pflichtreihenfolge,
Pflicht-Nachweis (z. B. Backup-Datei). Im MVP-021 nur als Typ-Stub
vorbereitet; volle Logik in MVP-026.

### 3.15 `signoff_internal` — interne Zwischen-Freigabe

Pflicht: vier-Augen-Bestätigung durch zweiten internen User. Wird
in MVP-028 ausgebaut.

## 4. Validierungs-Service

`ProtocolItemValidator::validate(item)` führt:

1. Typ-spezifische Schema-Prüfung auf `value_json`.
2. `required`-Prüfung.
3. Wert-/Toleranzbereich.
4. `result`-Ableitung (sofern automatisch).
5. Aggregierte Protocol-Validation: Alle `required`-Items gefüllt?
   Kein `defect` mit `severity=critical` ohne open_issue?

Diese Validierung blockiert die Übergänge
`requestReview` und `sign`.

## 5. Vorlagen-Hinweis

Vorlagen (Feature 032) bilden Typ-Wahl, Reihenfolge und
Konfigurationsparameter (z. B. `tolerance_min`) ab. MVP-021 spezifiziert
nur die Typen — Vorlagen-MVP folgt.

## 6. Akzeptanzkriterien

1. Enum `ProtocolItemType` listet alle 15 Typen (3.1 – 3.15).
2. `ProtocolItemValidator` validiert pro Typ; Tests pro Typ
   (positiv + negativ).
3. `defect`-Punkt erzeugt automatisch einen Open-Issue-Datensatz
   (MVP-024-Integration vorbereitet, Stub mit TODO ok).
4. `result`-Ableitung wo automatisch (`boolean`, `choice`, `number`,
   `range`, `defect`).
5. UI-Komponenten je Typ folgen UX-Pattern §3.4 (Pflichtfelder oben).
6. Übergänge `requestReview` und `sign` blockieren, wenn Validierung
   fehlschlägt.

## 7. Out-of-scope (MVP-021)

- Vorlagen-System (separates Feature 032 / späteres MVP).
- Volle Prozedur-Engine (MVP-025…028).
- Multi-language Item-Labels (folgt aus i18n-Konvention im Glossar).

## 8. Folge

- MVP-022 Signatur.
- MVP-023 Vorher/Nachher.
- MVP-024 Offene Punkte.
- MVP-025 Prozedurvorlagen.
- Folge: Wiederverwendbare Vorlagen / Wizard-Anlage.

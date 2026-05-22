# Vorher-/Nachher-Fotos am Protokollpunkt

Status: Aktiv (MVP-023, Issue #23) • Quelle:
[Feature 003 — Abnahmeprotokolle](features/003-dokumentation-abnahmeprotokolle.md).
• Aufbauend auf:
[Protokoll-Datenmodell](protokoll-datenmodell.md) (MVP-020),
[Protokollpunkt-Typen](protokollpunkt-typen.md) (MVP-021),
[Abnahme & Signatur](abnahme-signatur.md) (MVP-022).

## 1. Zweck

Strukturierte **Vorher-/Nachher-Dokumentation** pro Protokollpunkt
(MVP-021 §3.10 `photo`), damit Beweisfotos nicht lose am Auftrag
hängen, sondern eindeutig „vor Arbeit" / „nach Arbeit" / „Detail" /
„Schaden" zugeordnet sind.

## 2. Datenmodell

### 2.1 Tabelle `attachments` (vorhanden) — erweiterte Pivot-Spalten

Da Fotos polymorphe `Attachment`-Datensätze sind, erfolgt die fachliche
Zuordnung über eine **Pivot-Tabelle** zwischen Protokollpunkt und
Attachment:

```sql
CREATE TABLE protocol_item_photos (
    id                BIGINT PRIMARY KEY AUTO_INCREMENT,
    protocol_item_id  BIGINT NOT NULL,
    attachment_id     BIGINT NOT NULL,
    phase             VARCHAR(20) NOT NULL,   -- before|after|detail|defect|reference
    caption           VARCHAR(180) NULL,
    sort_order        INT NOT NULL DEFAULT 0,
    taken_at          TIMESTAMP NULL,         -- aus EXIF, falls vorhanden
    geo_lat           DECIMAL(9,6) NULL,
    geo_lng           DECIMAL(9,6) NULL,
    captured_by_user_id BIGINT NULL,
    UNIQUE KEY uniq_pair (protocol_item_id, attachment_id),
    INDEX idx_item_phase (protocol_item_id, phase, sort_order)
);
```

Damit kann ein Foto theoretisch (selten) auch in zwei Punkten
referenziert sein — die Datei liegt trotzdem nur einmal.

### 2.2 Phasen

| Phase       | Bedeutung                                                        |
| ----------- | ---------------------------------------------------------------- |
| `before`    | Zustand **vor** der Arbeit (Pflicht bei `maintenance`/`defect`). |
| `after`     | Zustand **nach** der Arbeit.                                     |
| `detail`    | Nahaufnahme eines Aspekts (Seriennummer, Schaden).               |
| `defect`    | Konkreter Mangel.                                                |
| `reference` | Vergleichsbild (z. B. Soll-Zustand laut Hersteller).             |

Pro `phase` ist `min_count` / `max_count` in der Vorlage konfigurierbar
(Folge-MVP-Vorlagen). MVP-023 hält die Spalte `phase` als Enum bereit.

## 3. UI

### 3.1 Erfassung am Punkt

Komponente `<x-photo-strip>` pro `protocol_item` rendert je Phase eine
horizontale Strip mit Plus-Button. Beim Hochladen / Auswählen wird ein
Dialog mit Phase + Caption geöffnet. Mehrfach-Upload möglich.

Mobile: Direkt-Kamera-Capture via `<input type="file" capture>` plus
Web-Share-Target (Folge).

### 3.2 Anzeige im PDF (MVP-022)

In der Items-Tabelle erscheint pro Foto eine kleine Vorschau (max 4
pro Punkt; Rest als „+n weitere"). Caption + Phase werden ausgegeben:

```text
[ ▢ ] vor: "Sicherungskasten geöffnet"
[ ▢ ] nach: "Sicherungskasten geschlossen"
```

### 3.3 Vorher/Nachher-Vergleich

Sektion „Vergleich" am Punkt: nebeneinander stehende
Vorher/Nachher-Galerie mit gleicher Reihenfolge (`sort_order`) — hilft
in Streitfällen.

## 4. EXIF / Metadaten

Beim Upload werden via `intervention/image` (oder vergleichbar)
extrahiert:

- `taken_at` aus EXIF `DateTimeOriginal`.
- `geo_lat`/`geo_lng` aus GPS-Tags (falls in Org-Setting aktiviert;
  Default **aus** wegen DSGVO).

EXIF-Stripping vor Speicherung (Sensitive Geo-Daten standardmäßig
entfernt; nur bei aktivem Setting beibehalten).

## 5. Pflicht-Logik

`ProtocolItemValidator` (MVP-021 §4) erweitert sich für `photo`-Items:

- Wenn `value_json.min_per_phase = {"before": 1, "after": 1}`, dann
  müssen ≥ 1 Foto in `before` UND `after` existieren.
- Sonst Validierung gibt `validation.photo.missingPhase` zurück.
- `defect`-Items setzen automatisch `min_per_phase.defect = 1`.

## 6. Audit

- `protocol.item.photoAdded` (mit attachment_id, phase, caption)
- `protocol.item.photoRemoved`
- `protocol.item.photoReordered`
- `protocol.item.photoUpdatedCaption`

Diese Events erscheinen im Auftrags-Timeline-Aggregat unter
`protocol.updated`.

## 7. Permissions

| Permission                    | Wer                                |
| ----------------------------- | ---------------------------------- |
| `protocol.item.photo.add`     | Wer das Protokoll bearbeiten darf. |
| `protocol.item.photo.remove`  | Wer das Protokoll bearbeiten darf. |
| `protocol.item.photo.viewGeo` | Org-Admin (DSGVO).                 |

## 8. Akzeptanzkriterien

1. `protocol_item_photos` Tabelle mit Phase-Enum und Constraints.
2. Mehrere Fotos pro Phase pro Punkt möglich; Reihenfolge per
   `sort_order`.
3. EXIF-Auswertung extrahiert `taken_at`; GPS nur bei Org-Setting.
4. EXIF-Stripping vor Persistenz Default-on.
5. Pflicht-Phasen blockieren Übergang `requestReview`/`sign`.
6. PDF-Ausgabe zeigt Phase + Caption + max-4-Vorschau pro Punkt.
7. Audit-Events §6 vollständig.
8. Performance: Upload 5 MB Foto inkl. Resize/EXIF < 1.5 s P95.

## 9. Out-of-scope (MVP-023)

- Annotations-Tool (Pfeile/Boxen einzeichnen) — Folge.
- KI-Vergleich Vorher/Nachher — Folge.
- 360°-Fotos / Video — Folge.

## 10. Folge

- MVP-024 Offene Punkte (Defect-Fotos verlinken).
- MVP-035 Asset (Fotos aus Inventar wiederverwenden).
- Folge: Annotations, Mobile-Capture-Optimierung.

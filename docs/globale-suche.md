# Globale Suche

Status: Aktiv (MVP-014, Issue #14) • Quelle:
[Feature 023 — Suche, Timeline und Fallakte](features/023-suche-timeline-fallakte.md).
• Verbindet:
[Fallakte](fallakte.md),
[Auftrags-Timeline](auftrags-timeline.md),
[Kommunikationsnotizen](kommunikationsnotizen.md),
[Status- und Aktionsglossar](status-aktionsglossar.md).

## 1. Zweck

Eine **eine** Suchleiste, die alle relevanten Geschäftsobjekte einer
Organisation auffindbar macht — schnell, mandantenrein,
rechte-respektierend.

Domänen im MVP-014-Skript:

1. **Auftrag** (`DiaryEntry`) — Titel, Inhalt, externe Referenz,
   Auftragsnummer.
2. **Kunde** (`Customer`) — Name, Firma, Kundennummer.
3. **Projekt** (`Project`) — Name, Code.
4. **Kommentar** (`Comment`) — Body, mit Bezug zum Aggregat.
5. **Anhang-Metadaten** (`Attachment`) — Dateiname, Beschreibung,
   Tags (NICHT der Dateiinhalt im MVP).

Späteres Folge-MVP: Volltext über Anhang-Inhalte (OCR/PDF-Extract),
Kommunikationsnotizen (siehe §11), Protokolle, Offene Punkte, Assets.

## 2. Such-UI

### 2.1 Globale Suchleiste

- Persistent in der App-Topbar gemäß
  [UX-Pattern-Katalog](ux-pattern-katalog.md) §1.
- Material Symbol `search` als Leading-Icon.
- Tastatur-Shortcut **`/`** fokussiert (wie GitHub), **`Esc`** schließt
  Dropdown.
- Placeholder: „Auftrag, Kunde, Projekt, Kommentar oder Anhang suchen …"

### 2.2 Live-Vorschau (Type-ahead)

- Debounce 250 ms, Mindestlänge 2 Zeichen.
- Dropdown gruppiert nach Domäne (max. 3 Treffer pro Gruppe + „alle
  Treffer →").
- Highlight des Matches mit `<mark>`.
- Tastatur-Navigation: `↑/↓`, `Enter` öffnet, `Ctrl+Enter` neuer Tab.

### 2.3 Vollergebnisseite

- Route: `/search?q=<term>&type=<domain>&filters…`.
- Linke Spalte: Filter (Domäne, Zeitraum, Person, Status, Tag, Sichtbarkeit).
- Hauptspalte: paginierte Trefferliste, je Treffer
    - Domänen-Icon
    - Titel (Link zum Original)
    - Snippet (Body, gekürzt + Highlight)
    - Kontextpfad (Kunde > Projekt > Auftrag)
    - Status-Pille, Datum, Sichtbarkeit
- Rechte Spalte: Vorschau-Panel (Detailauszug ohne Seitenwechsel).
- Pagination 25 pro Seite (Default), wahlweise 50/100.

## 3. Datenmodell und Indizierung

### 3.1 Index-Tabelle

Eine zentrale Index-Tabelle ermöglicht einheitliches Ranking und
Permission-Filter:

```sql
CREATE TABLE search_index (
    id                BIGINT PRIMARY KEY AUTO_INCREMENT,
    organization_id   BIGINT NOT NULL,
    searchable_type   VARCHAR(64) NOT NULL,   -- DiaryEntry|Customer|Project|Comment|Attachment
    searchable_id     BIGINT NOT NULL,
    title             VARCHAR(255) NOT NULL,
    body              MEDIUMTEXT NOT NULL,    -- konkateniert: Titel + Beschreibung + ref-Nrn
    context_path      VARCHAR(512) NULL,      -- "Kunde > Projekt > Auftrag"
    visibility        VARCHAR(12) NOT NULL,   -- internal|customer (für Permission-Filter)
    tags              TEXT NULL,
    updated_at        TIMESTAMP NOT NULL,
    FULLTEXT KEY ft_search (title, body, tags),
    UNIQUE KEY uniq_target (searchable_type, searchable_id),
    INDEX idx_org_type (organization_id, searchable_type)
);
```

MySQL InnoDB **FULLTEXT** (`MATCH … AGAINST`) für MVP. Bei PostgreSQL
`tsvector` mit GIN-Index. Bei späteren Skalierungsbedarfen Migration zu
Meilisearch / Typesense möglich (Adapter-Interface vorbereiten).

### 3.2 Reindex-Strategie

| Auslöser                                                       | Aktion                                       |
| -------------------------------------------------------------- | -------------------------------------------- |
| `created`/`updated`/`deleted` Eloquent-Events der Quell-Models | Observer schreibt `search_index` synchron.   |
| Bulk-Import / Migration                                        | Artisan-Command `search:rebuild [--type=…]`. |
| Schema-Änderung                                                | Versionierte Reindex-Migration.              |
| Permission-/Sichtbarkeitsänderung                              | Update von `visibility` im Index-Eintrag.    |

Asynchrone Queue (Laravel Horizon / queue:work) für große Volumina —
synchrone Updates für Einzelaktionen.

### 3.3 Was wird in `body` aufgenommen?

| Domäne       | Felder                                                      |
| ------------ | ----------------------------------------------------------- |
| `DiaryEntry` | title, description, external_reference, order_number, tags  |
| `Customer`   | name, company_name, customer_number, billing_email, address |
| `Project`    | name, code, description                                     |
| `Comment`    | body, parent-Objekt-Titel (für Kontext)                     |
| `Attachment` | original_filename, description, tags, MIME, parent-Titel    |

## 4. Permission-Filter

Jede Query an `search_index` MUSS folgende WHERE-Bedingungen erzwingen
(z. B. via Scope `SearchablesOfUser`):

1. `organization_id` = aktive Organisation des Nutzers.
2. Nur Treffer, an deren Original-Aggregat der Nutzer
   `…view.own|team|organization` hat (Policy-Vorprüfung).
3. Kunden-Portal-Rolle (`kunde`): - Nur `visibility = customer`. - Nur Aggregate des eigenen Kundenkontos (`Customer.id =
user.customer_id`). - Domänen `Comment` ausgeschlossen (intern).

Diese Filter sind **nicht** UI-only: ein Treffer ohne Berechtigung
darf nie zurückgeliefert werden (Tests).

## 5. Ranking

Sortier-Reihenfolge (Default):

1. Exakter Match Auftragsnummer / Kundennummer / Projektcode (Score 1.0).
2. Exakter Titel-Match.
3. FULLTEXT-Score `MATCH(title, body, tags) AGAINST(:q IN BOOLEAN MODE)`.
4. Neuheit-Faktor `0.1 * recencyDays^-1`.

Reihenfolge per Filter „Relevanz" (Default) oder „Datum (neueste zuerst)"
wählbar.

## 6. Filter

| Filter       | Wirkung                                                    |
| ------------ | ---------------------------------------------------------- |
| Domäne       | Beschränkt `searchable_type`.                              |
| Zeitraum     | `updated_at` Range.                                        |
| Person       | Aktor (`created_by` / `assigned_to`) am Original-Aggregat. |
| Status       | Domain-Status aus Glossar (z. B. Auftragsstatus).          |
| Tag          | Tags-Spalte.                                               |
| Sichtbarkeit | internal/customer (nur intern aus Mitarbeitersicht).       |
| Kunde        | Kontextpfad enthält Kunden-ID.                             |

## 7. URL-Schema

- `/search?q=…` — Volltext-Ergebnis.
- `/search?q=&customer=42` — Filter ohne Volltext erlaubt (≙ „alles
  zum Kunden 42").
- Persistente, teilbare Links. Nutzer-spezifische Permission greift
  trotzdem.

## 8. Performance

- Type-ahead: Antwortzeit P95 < 150 ms bei 100 000 Index-Einträgen.
- Vollergebnis: P95 < 400 ms bei 1 000 Treffern (gefiltert).
- Index-Cache pro `(org_id, q, filter_hash)` 30 s.
- Reindex eines Aggregats < 50 ms (synchroner Pfad).

## 9. Audit / Datenschutz

- Globale Suche selbst loggt **keine** Audit-Events (zu hoch frequent).
- Bei Treffer-Klick auf vertrauliche Notiz → Event
  `confidential.viewed` (siehe Kommunikationsnotizen §8).
- Such-Logs (`q`, Filter, Trefferanzahl, Antwortzeit) werden in
  `search_query_logs` 30 Tage rotierend gehalten, pseudonymisiert
  (User-Hash), zur Index-Optimierung. Opt-out per Org-Setting.

## 10. Akzeptanzkriterien

1. Globale Suchleiste in der Topbar mit `/`-Shortcut, 250 ms Debounce,
   Mindestlänge 2.
2. Type-ahead liefert max. 3 Treffer pro Domäne + Link auf
   Vollergebnisseite.
3. Vollergebnisseite mit Filtern (Domäne, Zeitraum, Person, Status,
   Tag, Sichtbarkeit, Kunde).
4. Treffer respektieren **strikt** Mandant + Permissions + Kunden-Portal-
   Sichtbarkeit; automatisierte Tests pro Domäne (Negativ + Positiv).
5. Index aktualisiert sich synchron bei Single-Updates, asynchron bei
   Bulk; `search:rebuild` verfügbar.
6. P95 Type-ahead < 150 ms bei Test-Dataset.
7. Ranking-Reihenfolge laut §5; Sortier-Umschaltung „Datum" funktioniert.
8. URL-Filter sind teilbar und ohne `q` benutzbar (z. B. „alles zum
   Kunden X").

## 11. Out-of-scope (MVP-014)

- Volltext über Anhang-Inhalte (OCR/PDF-Extract) — Folge-MVP.
- Indexierung von **Kommunikationsnotizen** Body — kommt mit MVP-015
  (Filter/Sortierung), da Kommunikation oft vertraulich ist und
  zusätzliche Permission-Logik braucht.
- Fuzzy-Search (Levenshtein), Phonetische Suche — Folge.
- Globale Suche über Audit-Logs.
- Saved Searches / Smart Filters.

## 12. Folge-MVPs

- **MVP-015** Filter/Sortierung in Listen, inkl. Kommunikation in Index.
- **MVP-016** Saved Searches.
- Folge: Volltext über Anhang-Inhalte (OCR-Pipeline).
- Folge: Externer Suchindex (Meilisearch / Typesense) hinter Adapter.

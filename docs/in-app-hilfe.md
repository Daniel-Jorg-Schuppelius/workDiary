# In-App-Hilfe für Zeiterfassung, Auftrag, Protokoll, Prozedur und Auswertung

Status: Aktiv (MVP-051, Issue #50) • Quelle:
[Feature 039 — Hilfe / Dokumentation In-App](features/039-hilfe-dokumentation-in-app.md).

## 1. Zweck

Jede Hauptfunktion (Zeiterfassung, Auftrag, Protokoll, Prozedur,
Auswertung) erhält **direkt im UI** Zugriff auf eine kurze,
versionierte, lokalisierte Erklärung. Die Hilfe soll kontextbezogen,
suchbar und ohne Verlassen des Workflows nutzbar sein.

## 2. Kontextpunkte (MVP)

| Bereich        | Aufrufpunkt im UI                                |
| -------------- | ------------------------------------------------ |
| Zeiterfassung  | Topbar in `/time-entries`, Drawer im Eintrags-Editor |
| Auftrag        | Topbar in `/diary-entries`, Drawer im Auftrags-Editor |
| Protokoll      | Topbar in `/protocols`, Drawer im Protokoll-Editor + Signatur-Dialog |
| Prozedur       | Topbar in `/procedures`, Drawer im Run-Editor    |
| Auswertung     | Topbar in `/reports/*`, Drawer in jedem Drilldown |

Aufruf via Material-Symbol `help_outline` (Tooltip „Hilfe"). Tastatur-
Shortcut `?` öffnet den passenden Drawer.

## 3. Inhalts-Format

Hilfe-Inhalte liegen als **Markdown-Dateien** in
`resources/help/{locale}/{topic}.md`. Beispiel-Topics:

- `time-entries.start.md`
- `time-entries.edit.md`
- `diary-entries.create.md`
- `protocols.create.md`
- `protocols.sign.md`
- `procedures.run.md`
- `reports.customer-analysis.md`
- `reports.drilldown.md`

Front-Matter:

```yaml
---
title: "Zeiterfassung starten"
topic: time-entries.start
version: 1
audience: [operator, admin]
related:
  - time-entries.edit
  - reports.customer-analysis
---
```

Markdown-Inhalt: kurz (≤ 250 Wörter), mit Screenshot-Platzhalter
(`![…](image://help/time-entries-start.png)`).

## 4. Datenmodell

### 4.1 `help_topics` (denormalisierter Index)

| Feld         | Typ      | Notizen                              |
| ------------ | -------- | ------------------------------------ |
| id           | uuid     |                                      |
| topic        | string   | unique                               |
| locale       | string   | de, en                               |
| title        | string   |                                      |
| audience     | jsonb    | array of role-codes                  |
| version      | int      |                                      |
| body_md      | text     | Vorgehalten für Suche                |
| body_html    | text     | Pre-rendered                         |
| search_vector| tsvector / FULLTEXT |                          |
| updated_at   | datetime |                                      |

Eintrag wird beim Deployment via `help:reindex` aus den Markdown-
Dateien aufgebaut. Es gibt **keine** UI-Bearbeitung im MVP.

### 4.2 `help_views` (Telemetrie, anonym)

| Feld         | Typ      | Notizen                              |
| ------------ | -------- | ------------------------------------ |
| id           | uuid     |                                      |
| organization_id | uuid null |                                  |
| topic        | string   |                                      |
| locale       | string   |                                      |
| was_helpful  | bool null| Nutzer-Feedback (Daumen hoch/runter) |
| created_at   | datetime |                                      |

Keine User-ID gespeichert.

## 5. UI-Komponenten

- `<x-help-button :topic="…"/>` → Material-Symbol, öffnet Drawer.
- `<x-help-drawer/>` → Rechts-Drawer mit Titel, Inhalt, „War das
  hilfreich?"-Feedback, Link „Verwandte Themen".
- `<x-help-search/>` → Globaler Such-Dialog (`Strg+/`), Volltextsuche
  auf `help_topics.search_vector`, gefiltert nach Rolle.

## 6. Lokalisierung

- MVP: de (vollständig), en (mindestens Titel + erster Absatz).
- Fallback-Reihenfolge: Locale des Users → `de` → `en`.
- Fehlende Topics werden als „Kein Hilfetext verfügbar" angezeigt
  (kein Crash).

## 7. Pflege-Workflow

- Inhalte liegen im Repository, werden via PR gepflegt.
- CI-Check: alle in `<x-help-button :topic="…">` referenzierten
  Topics müssen in mindestens einer Locale existieren.
- `php artisan help:reindex` läuft im Deployment-Skript.

## 8. Permissions

Kein eigener Permission-Code; Hilfe ist immer sichtbar wenn der
zugehörige Bereich sichtbar ist. `help_topics.audience` filtert
zusätzlich: Operator sieht keinen Admin-spezifischen Hilfetext.

## 9. Audit / Telemetrie

- Keine Audit-Events (Hilfe ist Lese-Funktion).
- `help_views` rein anonym, Daten-Retention 90 Tage, Org-Admin sieht
  Aggregat „Top-Themen" als Hinweis für Schulungsbedarf.

## 10. Akzeptanzkriterien

1. Hilfe-Button + Drawer in 5 Bereichen §2.
2. Mindestens 10 Topics §3 in de vollständig vorhanden, en mit
   Titel + erstem Absatz.
3. Volltextsuche funktioniert über `Strg+/`.
4. CI-Check für fehlende Topics.
5. `help:reindex` idempotent.
6. Feedback-Button schreibt `was_helpful` ohne User-ID.
7. Tests: Topic-Loader, Suche, Audience-Filter.

## 11. Out-of-scope (MVP-051)

- Interaktive Tour mit Highlight-Overlays.
- Video-Inhalte.
- AI-gestützte Frage-Antwort-Suche.
- Inline-Editor für Org-spezifische Hilfetexte.

## 12. Folge

Abschluss MVP-Roadmap des Onboarding-/Hilfe-Clusters.

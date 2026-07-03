# Copilot / Agent Instructions — workDiary

> **Verbindliche Standards stehen in [AGENTS.md](../AGENTS.md) (Repo-Root).**
> Dieses Dokument ist nur der Einstiegspunkt für GitHub Copilot. Die
> herstellerneutrale `AGENTS.md` ist die Single Source of Truth und wird von
> VS Code automatisch zusätzlich geladen — **immer zuerst dort nachschlagen.**

## Schnellüberblick (Details in AGENTS.md)

- **Stack:** Laravel 13 / PHP 8.4 / CarbonImmutable · Tailwind v4 + DaisyUI v5 + Material Symbols Outlined · Spatie Permissions · Sqid-IDs in URLs · Sprache **nur Deutsch** (`lang/de/`).
- **Gates:** `composer test` · `vendor/bin/phpstan analyse` · `vendor/bin/pint`.

## Verbindliche Quellen (immer zuerst lesen)

| Thema                             | Quelle                                                                  |
| --------------------------------- | ----------------------------------------------------------------------- |
| **Arbeitsanweisung (kanonisch)**  | [AGENTS.md](../AGENTS.md)                                               |
| UX-Pattern-Katalog (Leitdokument) | [ux-pattern-katalog.md](../../WorkDiary-Architecture/ux-pattern-katalog.md)             |
| Status- & Aktionssemantik         | [status-aktionsglossar.md](../../WorkDiary-Architecture/status-aktionsglossar.md)       |
| Barrierefreiheit                  | [accessibility-checkliste.md](../../WorkDiary-Architecture/accessibility-checkliste.md) |
| UI-Vereinheitlichung / Ausnahmen  | [ui-unification-audit.md](../../WorkDiary-Architecture/ui-unification-audit.md)         |

## Wichtigste Regeln (Kurzfassung)

1. **Index-/Listenseiten** folgen dem `<x-index-page>`-Skeleton (AGENTS.md §3).
2. **Eingaben dialog-first als `<x-modal>`** — standalone Create-/Edit-Seiten nur als begründete Ausnahme bei sehr vielen Eingaben; keine Browser-Dialoge (AGENTS.md §4).
3. **Tailwind v4-Klassennamen** (keine v3-Aliase), Farb-Tones nur über die definierte Semantik (AGENTS.md §5).
4. **Komponenten wiederverwenden, nicht neu erfinden**; eigene Toolkits (`StringHelper`, `DateHelper`, … aus `php-common-toolkit` u. a.) vor Eigenbau nutzen (AGENTS.md §9). Abweichungen im PR begründen.
5. **Globaler Header-Zeitraum** (`DateRangeContext`) ist die maßgebliche Zeitraum-Instanz — keine konkurrierenden Datumsfilter pro Seite (AGENTS.md §8).
6. **Gründlicher Weg vor schnellem Weg:** robuster, defensiver Code (Eingaben validieren, Zugriffe prüfen, Schwachstellenmuster vermeiden); bei Unklarheit nachfragen statt raten, keine Gate-Abkürzungen (AGENTS.md §10).
7. `&` (nicht `&amp;`) in `__()`-Strings; keine neuen Markdown-Dokumente ohne ausdrückliche Aufforderung.

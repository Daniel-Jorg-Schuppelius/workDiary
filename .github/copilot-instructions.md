# Copilot / Agent Instructions — workDiary

> **Verbindliche Standards stehen in [AGENTS.md](../AGENTS.md) (Repo-Root).**
> Dieses Dokument ist nur der Einstiegspunkt für GitHub Copilot. Die
> herstellerneutrale `AGENTS.md` ist die Single Source of Truth und wird von
> VS Code automatisch zusätzlich geladen — **immer zuerst dort nachschlagen.**

## Schnellüberblick (Details in AGENTS.md)

- **Stack:** Laravel 13 / PHP 8.4 / CarbonImmutable · Tailwind v4 + DaisyUI v5 + Material Symbols Outlined · Spatie Permissions · Sqid-IDs in URLs · UI **nur Deutsch**: inline `__('Deutsch')` (= Übersetzungsschlüssel) **plus** Spiegelung in `lang/{en,fr,it,es}.json` mit Übersetzung (AGENTS.md §12).
- **Gates:** `composer test` · `vendor/bin/phpstan analyse` · `vendor/bin/pint`.

## Qualitätsvertrag — Definition of Done (VOR jedem Vorschlag/Commit erfüllen)

Ziel ist **dauerhaft hohe Produktqualität** und **strikte Einhaltung der
Projektvorgaben** (AGENTS.md, CLAUDE.md, `../WorkDiary-Architecture/`). Jede
Änderung erfüllt **alle** folgenden Punkte — sonst nicht vorschlagen/committen:

1. **Alle Gates grün** (`composer test`, PHPStan Level 8, Pint). Keine Ausnahme,
   kein `--no-verify`, keine deaktivierten/übersprungenen Tests.
2. **AGENTS.md zuerst lesen und befolgen** — insb. UX-Standards (§3 Index-Seiten,
   §4 Dialoge), Toolkit-first (§9), i18n (§12), Sicherheit/Robustheit (§10).
   Abweichung nur mit expliziter Begründung im PR.
3. **Vorhandene Muster/Komponenten/Toolkits wiederverwenden**, nicht neu erfinden
   oder duplizieren. Für ähnliche Aufgaben das bestehende Vorbild kopieren.
4. **Diff minimal und fokussiert:** nur ändern, was die Aufgabe braucht. Nichts
   Unverstandenes umbauen, keine Massen-Reformatierung, keine unrelated Dateien.
5. **Nichts löschen/aufweichen, um „grün"/„fertig" zu wirken:** keine Tests,
   Übersetzungen, Guards, Validierungen, Typen oder Funktionalität entfernen.
   Neue Logik bekommt neue Tests.
6. **Robustheit vor Tempo:** Eingaben validieren, Rechte/Mandanten-Scope prüfen
   (`BelongsToOrganization`), Fehlerfälle behandeln, deutsch lokalisieren (§12).
7. **Bei Unklarheit oder breiter Wirkung nachfragen** statt raten.
8. **Leitsatz:** Reduziert eine „Verbesserung" die Qualität — Gate rot, Feature
   schwächer, Projektvorgabe verletzt — dann **ablehnen**, nicht abschwächen.

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
8. **Übersetzungs-Keys NIEMALS löschen (HART):** Deutscher Text in `lang/en|fr|it|es.json` ist der **Schlüssel** und Pflicht — **nicht** „aufräumen", weil es eine „non-German locale" ist. Jeder `__('…')`-String muss in allen JSON-Locales stehen; jeder `config/scheduler.php`-Job braucht `scheduler.job.<key>` in allen 5 `lang/*/scheduler.php` (AGENTS.md §12).
9. **Findings nie durch Löschen „lösen":** Keys/Tests/Guards/Funktionalität nicht entfernen, um ein CodeQL-/PR-Finding stillzulegen. **Macht ein „Fix" ein Gate (`composer test`/PHPStan/Pint) rot, ist der Fix falsch** — zurücknehmen, nicht das Gate.

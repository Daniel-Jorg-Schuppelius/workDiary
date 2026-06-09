# GoBD-Gap-Analyse & Roadmap

> Stand: 2026-06-08. Bewertet die GoBD-Konformität der Änderungs-/Konfigurations­versionierung
> und priorisiert die offenen Punkte. GoBD = Grundsätze zur ordnungsmäßigen Führung und
> Aufbewahrung von Büchern, Aufzeichnungen und Unterlagen in elektronischer Form.

## Kurzfassung

Das System hat bereits ein **breit ausgerolltes Änderungsprotokoll** (`Auditable`-Trait →
`audit_logs`, ~60 Modelle) und eine **solide Festschreibungs-Semantik** (Invoice-Storno,
MonthClosure-Lock, NumberSequence). Die GoBD-relevanten Lücken sind nicht „eine fehlende
Versionierungstabelle", sondern:

1. **Unveränderbarkeit des Protokolls** — `audit_logs` war eine normale Tabelle ohne
   Manipulationsschutz. → **Behoben** (Hash-Kette + append-only Guard, siehe unten).
2. **Abdeckungslücken** bei steuerrelevanten Stammdaten/Belegen (Invoice, TimeEntry,
   Customer, Project, User). → **Behoben** (Auditable nachgezogen).
3. Festschreibung/Storno: vorhanden, Konsistenz fortlaufend prüfen.
4. Aufbewahrung & maschinelle Auswertbarkeit: offen (Retention 6/10 J., Export).

## Bestand

| Baustein                                                                   | Datei                                     | Bewertung                                 |
| -------------------------------------------------------------------------- | ----------------------------------------- | ----------------------------------------- |
| Änderungsprotokoll (create/update/delete, before/after, user, ip, ua, org) | `app/Models/Concerns/Auditable.php`       | ✅ GoBD-tauglich im Inhalt                |
| Audit-Tabelle (polymorph)                                                  | `audit_logs`                              | ✅ Inhalt · ⚠️ war ohne Unveränderbarkeit |
| Festschreibung Rechnung (Entwurf→ausgestellt, Storno mit Grund)            | `app/Models/Invoice.php`                  | ✅                                        |
| Periodenabschluss (locked_at/by)                                           | `app/Models/MonthClosure.php`             | ✅                                        |
| Lückenlose Nummern                                                         | `app/Models/NumberSequence.php`           | ✅                                        |
| Settings-Muster (pro Org, JSON, verschlüsselt)                             | `app/Models/PluginSetting.php`            | ✅                                        |
| Versionierungs-Muster                                                      | `app/Models/ProcedureTemplateVersion.php` | ✅                                        |

## Grundsatz: Konfiguration ≠ steuerrelevante Daten

GoBD verlangt Versionierung für **steuerrelevante** Aufzeichnungen, nicht für UI-Präferenzen.

- UI-/Betriebs-Konfiguration (z. B. der Arbeitsmodus) braucht **keine** revisionssichere
  Versionierung. Sie liegt in den bestehenden Ablage-Ebenen (siehe
  `docs/einstellungen-ablage.md`): Per-User in `users.preferences`, global/pro-Org über
  `App\Support\Setting` + `organizations.settings`.
- Wo Konfiguration _doch_ historisiert werden soll, fällt das ohnehin an: `organizations.settings`
  ist über `Organization::Auditable` bereits Teil des Änderungsprotokolls.

## Umgesetzte Maßnahmen

### 1. Revisionssichere Audit-Ketten (Unveränderbarkeit)

Mechanik gebündelt im `HashChained`-Trait (`app/Models/Concerns/HashChained.php`),
genutzt von `AuditLog` **und** `OrganizationAuditLog`:

- **Hash-Kette**: jede Zeile speichert `prev_hash` und `hash = SHA-256(prev_hash | kanonische Nutzdaten)`.
  Verkettung im überschriebenen `performInsert()` → deckt `Auditable`-Trait und direkte
  `create(...)`-Aufrufe ab (kein roher Insert).
- **Append-only Guard**: `updating`/`deleting` werfen eine Exception.
- **Nebenläufigkeit**: Der Kettenkopf liegt in `audit_chain_heads` (eine Zeile pro Kette) und
  wird beim Insert per `lockForUpdate` gesperrt → keine zwei Zeilen erhalten denselben
  `prev_hash` (kein Fork), auch unter paralleler Last.
- **Integritätsprüfung**: `php artisan audit:verify` (optional `--chain=`) rechnet beide Ketten
  treiberunabhängig über `Model::hashPayload()` nach.
- Bestandsdaten werden in den Migrationen rückwirkend verkettet.

### 2. Abdeckung steuerrelevanter Modelle

`Auditable` nachgezogen für: **Invoice, TimeEntry, Customer, Project, User**.

### 3. Konfigurations-Ablage konsolidiert

Keine neue Settings-Tabelle: Konfiguration nutzt die bereits bestehenden Ebenen
(`users.preferences`, `App\Support\Setting` + `organizations.settings`). Details und
Entscheidungsregel in `docs/einstellungen-ablage.md`. Der Arbeitsmodus (vormals eigene Spalte
`users.preferred_work_mode`) liegt jetzt konsolidiert in `users.preferences['work_mode']`.

### 4. Aufbewahrung & maschinelle Auswertbarkeit

- Retention konfigurierbar (`config/audit.php`, `AUDIT_RETENTION_YEARS`, Default 10 J.). Da die
  Ketten append-only sind, gibt es **bewusst kein automatisches Löschen** — die Frist ist eine
  Mindest-Aufbewahrung (dokumentarisch, im Export-Manifest).
- `php artisan audit:export` → ZIP mit CSV je Kette + `manifest.json` (Datensatzbeschreibung,
  Zeilenzahl, `head_hash`, `integrity_ok`). Der `head_hash` bindet den Inhalt kryptografisch.

### 5. DB-seitige Härtung (Ops)

`php artisan audit:db-hardening-sql` druckt das treiberpassende REVOKE-SQL, um dem App-User
`UPDATE`/`DELETE` auf den Audit-Tabellen zu entziehen (Defense-in-Depth; vom DBA anzuwenden).
`audit_chain_heads` bleibt beschreibbar.

## Offen (Folge-Roadmap)

1. DB-Härtungs-SQL produktiv durch DBA anwenden (`audit:db-hardening-sql` ausführen lassen).
2. GoBD-/GDPdU-Index (`INDEX.XML`-Datensatzbeschreibung) zusätzlich zum CSV/JSON-Manifest,
   falls vom Prüfer verlangt.
3. Festschreibungs-Pfade fortlaufend prüfen (keine stillen Updates nach Lock/Storno).
4. `audit:verify` in CI/Cron einhängen (regelmäßiger Integritätsnachweis).

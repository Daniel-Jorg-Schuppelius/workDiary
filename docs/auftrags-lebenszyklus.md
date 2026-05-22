# Auftrags-Lebenszyklus: Annahme, Bearbeitung, Abschluss

Status: Aktiv (MVP-011, Issue #11) • Quelle:
[Feature 001 — Aufzeichnung und Zeiterfassung als Kernprodukt](features/001-zeiterfassung-kernprodukt.md).
• Begleitend:
[Status- und Aktionsglossar](status-aktionsglossar.md) §3.1 / §4.1,
[Auftrags-Timeline](auftrags-timeline.md),
[UX-Pattern-Katalog](ux-pattern-katalog.md).

## 1. Zweck

Der heutige `DiaryEntry`-Status (`Open` / `InProgress` / `Done` / `Problem`)
reicht für eine Nachweisführung nicht aus. WorkDiary soll **strukturierte
Lebenszyklus-Ereignisse** dokumentieren: **Annahme**, **Bearbeitung** mit
Wartezuständen und **Abschluss** (Fertigstellung + Abnahme + Berechnung).

Ziele:

- Jeder Auftrag hat eindeutige **Zeitstempel pro Lebenszyklus-Schritt**.
- Jede Statusänderung ist **vom richtigen Aktor** ausgelöst und im
  `audit_logs` mit dem in [Status-/Aktionsglossar §4.1](status-aktionsglossar.md)
  definierten Verb gespeichert.
- Die [Auftrags-Timeline](auftrags-timeline.md) zeigt diese Ereignisse als
  `order.*`-Einträge.
- Berechnungen (Reaktionszeit, Bearbeitungsdauer netto, Wartezeit, Plan/Ist)
  werden aus diesen Feldern abgeleitet — nicht heuristisch.

## 2. Neuer Status-Lebenszyklus

Verbindlich gemäß
[Status-/Aktionsglossar §3.1](status-aktionsglossar.md). Migration vom alten
Enum (`Open`/`InProgress`/`Done`/`Problem`) siehe §7.

```text
                            ┌──cancel─────────────────────────► Cancelled
                            │
Planned ──accept──► Accepted ──start──► InProgress ──complete──► Completed ──handover──► Accepted_Final ──markInvoiced──► Invoiced
                              │             ▲   │
                              │             │   ├── waitCustomer ──► WaitingCustomer ──resume──┐
                              │             │   └── waitMaterial ──► WaitingMaterial ──resume──┤
                              │             └──────────────────────────────────────────────────┘
                              │
                              └──reject──► (zurück zu Planned mit Begründung)
```

Übergänge sind **Service-Methoden** auf `OrderService` (= `DiaryService`)
und **niemals** direkte `update(['status' => …])`-Aufrufe.

## 3. Neue Felder am `diary_entries`

Migration `add_lifecycle_columns_to_diary_entries`. Alle Datumsfelder sind
**UTC-Timestamps**.

| Spalte                    | Typ                                      | Pflicht | Bedeutung                                                            |
| ------------------------- | ---------------------------------------- | :-----: | -------------------------------------------------------------------- |
| `planned_start_at`        | timestamp NULL                           |    —    | Geplanter Termin (für Plan/Ist, MVP-018).                            |
| `planned_end_at`          | timestamp NULL                           |    —    | Geplantes Ende.                                                      |
| `planned_duration_min`    | int unsigned                             |    —    | Geplante Dauer in Minuten (für Plan/Ist).                            |
| `accepted_at`             | timestamp NULL                           |    —    | Zeitpunkt der Annahme.                                               |
| `accepted_by_user_id`     | FK→users                                 |    —    | Wer hat angenommen.                                                  |
| `started_at`              | timestamp NULL                           |    —    | Erste Arbeitsaufnahme (`order.start` oder erste `time.entry_added`). |
| `paused_at`               | timestamp NULL                           |    —    | Letzter Übergang in `Waiting*`.                                      |
| `pause_reason`            | enum(`customer`,`material`,`other`) NULL |    —    | Warum pausiert.                                                      |
| `pause_note`              | text NULL                                |    —    | Freitext bei `other`.                                                |
| `resumed_at`              | timestamp NULL                           |    —    | Letzte Wiederaufnahme.                                               |
| `wait_seconds_total`      | int unsigned                             |   ✅    | Aufsummierte Wartezeit (über alle `Waiting*`-Intervalle).            |
| `completed_at`            | timestamp NULL                           |    —    | Fertigstellung der Arbeit.                                           |
| `completed_by_user_id`    | FK→users                                 |    —    | Wer hat abgeschlossen.                                               |
| `completion_summary`      | text NULL                                |    —    | Abschlusszusammenfassung (Pflicht für Status `Completed`).           |
| `accepted_final_at`       | timestamp NULL                           |    —    | Kundenabnahme (Protokoll abgeschlossen).                             |
| `accepted_final_by`       | string(120) NULL                         |    —    | Name der unterzeichnenden Person beim Kunden (Freitext).             |
| `signature_attachment_id` | FK→attachments                           |    —    | Unterschrift (Bild/PDF) — Verweis, nicht Bytes.                      |
| `protocol_id`             | FK→protocols                             |    —    | Verknüpftes Abnahmeprotokoll (1:1, MVP-020 ff.).                     |
| `invoiced_at`             | timestamp NULL                           |    —    | Markierung „Berechnet" (manuell oder durch Rechnung).                |
| `invoice_reference`       | string(64) NULL                          |    —    | Externe Rechnungsnummer.                                             |
| `cancelled_at`            | timestamp NULL                           |    —    | Storno-Zeitpunkt.                                                    |
| `cancelled_by_user_id`    | FK→users                                 |    —    | Wer hat storniert.                                                   |
| `cancellation_reason`     | string(200) NULL                         |    —    | Begründung Pflicht.                                                  |

Pflichtfelder pro Statusübergang siehe §5.

## 4. Eigenständige Lifecycle-Events (`diary_entry_events`)

Zusätzlich zu den Zeitstempel-Spalten gibt es eine **append-only**-Tabelle,
die jeden Übergang als eigenständigen Datensatz festhält. Sie dient als
Quelle für Reports (z. B. „wie oft wurde pausiert?") und als Backup gegen
verlorene Audit-Einträge.

```sql
CREATE TABLE diary_entry_events (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    diary_entry_id  BIGINT NOT NULL,
    organization_id BIGINT NOT NULL,
    event           VARCHAR(64)  NOT NULL,   -- order.accept, order.start, …
    from_status     VARCHAR(32)  NULL,
    to_status       VARCHAR(32)  NULL,
    actor_user_id   BIGINT NULL,             -- NULL = System
    actor_kind      VARCHAR(16)  NOT NULL,   -- user|system|customer|support
    note            TEXT NULL,
    payload         JSON NULL,               -- z. B. {pause_reason: "material"}
    occurred_at     TIMESTAMP    NOT NULL,
    created_at      TIMESTAMP    NOT NULL,
    INDEX idx_diary_event_time (diary_entry_id, occurred_at DESC),
    INDEX idx_event_org (organization_id, event, occurred_at)
);
```

Eigenschaften:

- **append-only**: Eloquent-Observer verbietet `updating` und `deleting`.
- `event` benutzt die Verben aus
  [Status-/Aktionsglossar §4.1](status-aktionsglossar.md).
- `actor_kind = system` für automatische Übergänge (z. B. `resume` bei
  erster neuer Zeitbuchung nach `WaitingMaterial`).
- `payload` enthält das, was nicht in einer eigenen Spalte am
  `diary_entries` lebt (z. B. Pause-Grund).

`audit_logs` bleibt die generische Audit-Quelle; `diary_entry_events` ist
das fachliche, schmale Read-Model speziell für Auftrags-Lebenszyklus.

## 5. Übergänge im Detail

Jeder Übergang ist eine **Methode** auf `OrderService` mit den
gleichnamigen Verben aus dem Glossar.

### 5.1 `accept(DiaryEntry, User): void` — `Planned → Accepted`

- Setzt `accepted_at = now()`, `accepted_by_user_id = $user->id`.
- Voraussetzung: Status `Planned`, Permission `order.accept`.
- Schreibt `diary_entry_events` (`order.accept`) + `audit_logs`.

### 5.2 `start(DiaryEntry, User): void` — `Accepted → InProgress`

- Setzt `started_at = COALESCE(started_at, now())`.
- Erlaubt nur aus `Accepted`. Implizit auch durch erste Zeitbuchung
  (`TimesheetService::start`) ausgelöst, dann `actor_kind = system`.

### 5.3 `pause(DiaryEntry, reason, note?, User): void` — `InProgress → Waiting*`

- `reason ∈ {customer, material, other}` Pflicht.
- Setzt `paused_at = now()`, `pause_reason`, `pause_note`.
- Eine laufende Stoppuhr **muss** gestoppt werden (TimesheetService).
- Mehrfache Pausen sind erlaubt; `wait_seconds_total` wird beim `resume`
  fortgeschrieben.

### 5.4 `resume(DiaryEntry, User): void` — `Waiting* → InProgress`

- Setzt `resumed_at = now()`.
- Addiert `resumed_at - paused_at` zu `wait_seconds_total`.
- Leert `paused_at`, `pause_reason`, `pause_note`.
- Auto-Auslöser: erste neue Zeitbuchung nach einer Pause → `resume` mit
  `actor_kind = system`.

### 5.5 `complete(DiaryEntry, summary, User): void` — `InProgress → Completed`

- `summary` Pflicht (mindestens 5 Zeichen).
- Setzt `completed_at = now()`, `completed_by_user_id`, `completion_summary`.
- Voraussetzung: alle offenen Zeiten sind beendet (keine laufende Stoppuhr).
- Optional: alle Pflichtklassifikationen gesetzt (MVP-032).

### 5.6 `handover(DiaryEntry, protocol, User): void` — `Completed → Accepted_Final`

- Erwartet ein **abgenommenes Protokoll** (`ProtocolStatus::AcceptedClean`
  oder `AcceptedDefects`).
- Setzt `accepted_final_at = protocol.accepted_at`,
  `accepted_final_by = protocol.signed_by_name`,
  `signature_attachment_id = protocol.signature_attachment_id`,
  `protocol_id = protocol.id`.
- Bei `AcceptedDefects` wird zusätzlich ein `open_issue.opened`-Bündel
  übernommen.

### 5.7 `markInvoiced(DiaryEntry, reference?, User): void` — `Accepted_Final → Invoiced`

- Setzt `invoiced_at`, optional `invoice_reference`.
- Auch idempotent bei externer Rechnungsstellung (kein erneuter Übergang
  bei gleicher `invoice_reference`).

### 5.8 `cancel(DiaryEntry, reason, User): void` — alle aktiven → `Cancelled`

- Erlaubt aus `Planned`, `Accepted`, `InProgress`, `Waiting*`.
- `reason` Pflicht.
- Setzt `cancelled_at`, `cancelled_by_user_id`, `cancellation_reason`.
- Beendet alle offenen Zeiten und schließt offene Protokolle/Prozeduren
  als `Aborted`.

## 6. Abgeleitete Kennzahlen (Read-only)

Aus den Lifecycle-Feldern lassen sich ohne Heuristik berechnen:

| Kennzahl                 | Formel                                                  |
| ------------------------ | ------------------------------------------------------- |
| Reaktionszeit            | `accepted_at − created_at`                              |
| Wartezeit Annahme→Start  | `started_at − accepted_at`                              |
| Bearbeitungsdauer brutto | `completed_at − started_at`                             |
| Bearbeitungsdauer netto  | `(completed_at − started_at) − wait_seconds_total`      |
| Wartezeit Anteil         | `wait_seconds_total / (completed_at − started_at)`      |
| Zeit bis Abnahme         | `accepted_final_at − completed_at`                      |
| Zeit bis Berechnung      | `invoiced_at − accepted_final_at`                       |
| Plan/Ist-Abweichung      | `(completed_at − started_at) − planned_duration_min*60` |

Diese Kennzahlen liefern die Grundlage für
[Reports](features/039-reports-customer.md ff.), Drill-down (MVP-042) und
SLA-Auswertungen (Feature 010, später).

## 7. Migration vom heutigen Status

Heutige Cases (`Status`-Enum):

| Heute            | Neu                                                                                     |
| ---------------- | --------------------------------------------------------------------------------------- |
| `Open` (2)       | `Planned` (default) **oder** `Accepted`, je nach Vorhandensein einer Zuweisung.         |
| `InProgress` (1) | `InProgress`.                                                                           |
| `Done` (-1)      | `Completed` (falls kein Protokoll/Abnahme) **oder** `Accepted_Final` (falls vorhanden). |
| `Problem` (3)    | `WaitingCustomer` mit `pause_reason = other`, `pause_note = "Aus Migration: Problem"`.  |

Migration:

1. Migrations-Script setzt für jeden bestehenden `DiaryEntry` den neuen
   Status nach obiger Tabelle.
2. `started_at`, `completed_at` werden aus den ersten/letzten Buchungen
   befüllt.
3. Ein Lifecycle-Event `order.migrated` mit `actor_kind = system` wird
   geschrieben.
4. Alter Status-Wert bleibt für 1 Release in einer Schattenspalte
   `status_legacy` erhalten (Rollback).

## 8. Sichtbarkeit, Rechte, Audit

| Übergang           | Permission                                 | Audit-Event            | Timeline `visibility` |
| ------------------ | ------------------------------------------ | ---------------------- | --------------------- |
| `accept`           | `order.accept` (Bearbeitende, Teamleitung) | `order.accept`         | `customer`            |
| `start`            | `order.work`                               | `order.start`          | `internal`            |
| `pause` / `resume` | `order.work`                               | `order.pause`/`resume` | `customer`            |
| `complete`         | `order.complete`                           | `order.complete`       | `customer`            |
| `handover`         | `protocol.accept` (Teamleitung/Admin)      | `order.handover`       | `customer`            |
| `markInvoiced`     | `order.invoice.mark`                       | `order.invoiced`       | `customer`            |
| `cancel`           | `order.cancel`                             | `order.cancel`         | `customer`            |

Jede Methode erzeugt zwingend einen `diary_entry_events`- **und** einen
`audit_logs`-Eintrag. Verstöße sind durch Tests gegen den `OrderService` zu
absichern (siehe §10).

## 9. UI-Auswirkungen

- **Auftrags-Detailseite**: Im Header eine **Lifecycle-Leiste** (siehe
  [UX-Pattern-Katalog](ux-pattern-katalog.md) §3.6) mit Status-Pille,
  primärer nächster Aktion (z. B. „Annehmen", „Beginnen", „Abschließen")
  und einem aufklappbaren „Verlauf"-Panel, das die letzten 5
  `diary_entry_events` zeigt.
- **Modale** für `pause` (Grund-Auswahl + Notiz) und `complete`
  (`summary` Pflichtfeld) gemäß §3.3.
- **Abnahme** und **Berechnen** sind über das Protokoll-/Rechnungs-Modul
  erreichbar, nicht direkt am Auftrag, schreiben aber dieselben Felder.
- Alle Buttons folgen den Labels aus
  [Status-/Aktionsglossar §4.1](status-aktionsglossar.md).

## 10. Akzeptanzkriterien

1. `diary_entries` hat alle Spalten aus §3.
2. `diary_entry_events` existiert mit den genannten Indizes und ist
   append-only.
3. Jeder Übergang ist als Methode auf `OrderService` implementiert, mit
   Policy-Check + Audit-Event + Lifecycle-Event.
4. Tests decken pro Übergang: Erfolgspfad, Vorbedingung verletzt, fehlende
   Permission, Idempotenz (z. B. doppeltes `markInvoiced`).
5. Migration alter Status-Cases läuft ohne Datenverlust, `status_legacy`
   wird befüllt.
6. UI zeigt deutsche Labels aus dem Glossar, keine harten Strings.
7. Reports/Read-Models können die in §6 genannten Kennzahlen ohne
   Zusatzlogik berechnen.

## 11. Out-of-scope (MVP-011)

- Vollautomatischer Rechnungsworkflow (eigenes Modul).
- SLA-Berechnung gegen Vertragsdaten (Feature 010).
- Kalenderintegration für `planned_start_at` (Folge-MVP).
- KI-/Vorschlagslogik für Status-Übergänge.

## 12. Folge-MVPs

- **MVP-015** Tagesabschluss — verwendet `completed_at` /
  `wait_seconds_total`.
- **MVP-016** Monatsfreigabe — verriegelt `invoiced_at`.
- **MVP-017** Korrekturanträge — können einzelne Lifecycle-Zeitstempel
  korrigieren (mit Audit).
- **MVP-018** Plan/Ist — nutzt `planned_*` und reale Lifecycle-Zeitstempel.
- **MVP-020 ff.** Protokoll — füllt `handover`.

## 13. Änderungsverfahren

1. Neue Übergänge werden zuerst in
   [Status-/Aktionsglossar §3.1/§4.1](status-aktionsglossar.md) ergänzt.
2. Dann in diesem Dokument als Service-Methode + Pflichtfelder +
   Vorbedingungen beschreiben.
3. Erst danach Migration, Service-Methode, Tests, UI.

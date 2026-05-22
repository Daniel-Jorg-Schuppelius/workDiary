# Exportgrundlage für geprüfte Zeiten

Status: Aktiv (MVP-019, Issue #19) • Quelle:
[Feature 005 — Lohn/Zuschläge/DATEV/Lexware](features/005-lohn-zuschlaege-datev-lexware.md).
• Aufbauend auf:
[Monatsfreigabe](monatsfreigabe.md),
[Tagesabschluss](tagesabschluss.md),
[Zeit-Korrekturen](zeit-korrekturen.md).

## 1. Zweck

Genehmigte Monatszeiten in ein **stabiles, reproduzierbares,
audit-fähiges Exportpaket** überführen, das als Grundlage für
Lohnabrechnung (DATEV, Lexware, sonstige) und Rechnungsstellung dient.

Prinzipien:

- **Nur freigegebene Daten** werden exportiert (`month_closure.status =
  approved` oder `locked`).
- **Snapshot statt Live-Query** — Export friert die Daten in einem
  Paket ein.
- **Reproduzierbar** — gleicher Lauf, gleiches Paket (Hash-Vergleich).
- **Audit-Trail** — jeder Export wird protokolliert; Re-Exports
  möglich, aber als solche gekennzeichnet.

## 2. Datenmodell

### 2.1 `time_exports`

```sql
CREATE TABLE time_exports (
    id                  BIGINT PRIMARY KEY AUTO_INCREMENT,
    organization_id     BIGINT NOT NULL,
    profile             VARCHAR(40) NOT NULL,     -- generic|datev|lexware|… (siehe §4)
    period_year         SMALLINT NOT NULL,
    period_month        TINYINT  NOT NULL,
    scope               VARCHAR(20) NOT NULL,     -- all|user|team
    scope_user_id       BIGINT NULL,
    scope_team_id       BIGINT NULL,
    status              VARCHAR(20) NOT NULL,     -- preparing|ready|delivered|rejected|superseded
    rows_count          INT NOT NULL,
    totals              JSON NOT NULL,            -- Summen für UI-Prüfansicht
    payload_hash        CHAR(64) NOT NULL,        -- SHA-256 des Exportpakets
    file_path           VARCHAR(255) NOT NULL,    -- Storage-Pfad (siehe §6)
    file_format         VARCHAR(20) NOT NULL,     -- csv|xml|json|zip
    created_by_user_id  BIGINT NOT NULL,
    created_at          TIMESTAMP NOT NULL,
    delivered_at        TIMESTAMP NULL,
    delivered_by_user_id BIGINT NULL,
    delivery_note       TEXT NULL,
    superseded_by_id    BIGINT NULL,              -- bei Re-Export
    INDEX idx_period (organization_id, period_year, period_month, profile),
    INDEX idx_status (organization_id, status)
);
```

### 2.2 `time_export_lines`

Optional als materialisierte Zeilen für Prüfansicht/Drill-down (kann
auch nur aus Datei rekonstruiert werden — Entscheidung pro Profil):

```sql
CREATE TABLE time_export_lines (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    time_export_id  BIGINT NOT NULL,
    user_id         BIGINT NOT NULL,
    wage_type       VARCHAR(40) NOT NULL,    -- siehe §5
    cost_center     VARCHAR(40) NULL,
    quantity        DECIMAL(10,4) NOT NULL,  -- Stunden / Tage
    unit            VARCHAR(10) NOT NULL,    -- h|d|EUR
    period_start    DATE NOT NULL,
    period_end      DATE NOT NULL,
    note            TEXT NULL,
    source_refs     JSON NULL,               -- IDs der Quell-time_entries/attendance
    INDEX idx_export (time_export_id),
    INDEX idx_user (time_export_id, user_id)
);
```

### 2.3 `time_export_events` (Audit)

```sql
CREATE TABLE time_export_events (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    time_export_id  BIGINT NOT NULL,
    event           VARCHAR(40) NOT NULL,   -- siehe §7
    actor_user_id   BIGINT NOT NULL,
    note            TEXT NULL,
    payload         JSON NULL,
    created_at      TIMESTAMP NOT NULL
);
```

## 3. Abhängigkeit Monatsfreigabe

| Voraussetzung                                | Wirkung                          |
| -------------------------------------------- | -------------------------------- |
| `month_closure.status = approved` für alle Betroffenen | Export kann starten.   |
| Mind. eine Person noch `submitted` / `draft` | Export bricht ab mit Liste der Blocker. |
| Erfolgreicher Export                         | Setzt `month_closure.status = locked` + `month.locked` Audit. |
| Re-Export nach `applied`-Korrekturen         | Erzeugt neue `time_exports`-Zeile; alte wird `superseded`. |

Manueller Override „Export auch für nicht-freigegebene Monate" ist
**bewusst nicht** vorgesehen.

## 4. Export-Profile

Profile sind versioniert und kapseln **Format + Lohnarten-Mapping +
Validierungsregeln**. Im MVP-019:

| Profil    | Format     | Status     | Inhalt                                          |
| --------- | ---------- | ---------- | ----------------------------------------------- |
| `generic` | CSV (UTF-8, ;) | MVP    | Eine Zeile pro `(user, wage_type)` pro Monat.   |
| `datev`   | CSV (Lohn) | Vorbereitet | DATEV LODAS-/Lohn&Gehalt-naher Aufbau (späterer MVP). |
| `lexware` | CSV/XML    | Vorbereitet | Lexware-Format (späterer MVP).                  |

Profile sind in `config/exports.php` registriert (Klassenname →
Profil-Klasse), nicht hart in Service-Code.

## 5. Lohnarten und Kostenstellen

### 5.1 Lohnarten (Mapping)

| Lohnart           | Quelle                                                |
| ----------------- | ----------------------------------------------------- |
| `work.normal`     | Netto-Arbeitsstunden ohne Zuschläge.                  |
| `work.night`      | Nachtstunden (laut Zuschlagsregel, Folge-MVP).        |
| `work.sunday`     | Sonntagstunden.                                       |
| `work.holiday`    | Feiertagsstunden.                                     |
| `work.oncall`     | Bereitschaftsstunden.                                 |
| `absence.vacation`| Urlaubstage.                                          |
| `absence.sick`    | Krankheitstage.                                       |
| `travel.time`     | Reisezeit (sofern als billable/wagable konfiguriert). |

Mapping pro Organisation in `wage_type_mappings` (Tabelle in
Folge-MVP); MVP-019 nutzt Defaults.

### 5.2 Kostenstellen

`cost_center` pro `TimeEntry` oder per Auftrag → Projekt → Kunde-
Hierarchie (Default-Fallback). Konfigurierbar, **nicht** hart kodiert.

## 6. Datei-Ablage

- Pfad: `storage/exports/{org}/{year}-{month}/{profile}-{hash}.{ext}`.
- Sichere ADR-Regeln aus
  [security/adr-attachment-paths.md](security/adr-attachment-paths.md)
  gelten analog (kein User-Input im Pfad, signed URLs für Download,
  Retention konfigurierbar).
- Standard-Retention: 10 Jahre (steuerliche Aufbewahrung) — pro
  Organisation überschreibbar.

## 7. Audit-Events

- `export.preparing`
- `export.ready`
- `export.downloaded`
- `export.delivered`  (manuelle Bestätigung „an Lohnbüro übergeben")
- `export.rejected`   (z. B. vom Lohnbüro mit Kommentar)
- `export.superseded` (bei Re-Export)
- `export.deleted`    (nur Org-Admin, mit Begründung, vor Ablauf
  Retention).

## 8. Prüfansicht vor Übergabe

UI-Seite `/exports/{id}/preview` zeigt:

- Header: Profil, Zeitraum, Status, Hash, Zeilenanzahl, Summen pro
  Lohnart pro Mitarbeitendem.
- Tabelle der `time_export_lines` mit Filter (User, Lohnart) und
  Drill-down zu den Quell-Buchungen.
- Aktionen: Download, „Als geliefert markieren" (Pflicht-Notiz),
  „Ablehnen mit Kommentar", „Re-Export erzeugen" (bei korrigierten
  Daten).

## 9. Akzeptanzkriterien

1. Export läuft nur, wenn alle betroffenen Monatsfreigaben `approved`
   oder `locked` sind.
2. Erfolgreicher Export setzt `locked` + Audit auf den Monatsfreigaben.
3. `payload_hash` ist reproduzierbar (gleicher Input → gleicher Hash).
4. Re-Export nach `applied`-Korrektur erzeugt neuen Export, alter wird
   `superseded` (referenziert).
5. Prüfansicht zeigt Summen pro Mitarbeitendem pro Lohnart vor
   Übergabe.
6. Profil `generic` produziert valide CSV (Header dokumentiert, UTF-8
   BOM optional pro Org-Setting).
7. Audit-Events §7 vollständig; Download und Delivery getrennt
   protokolliert.
8. Retention konfigurierbar (Default 10 Jahre), Lösch-Job dokumentiert
   und auditiert.
9. Permissions: `export.create`, `export.deliver`, `export.delete`
   (Org-Admin); Mitarbeitender sieht nur eigene Daten in Preview.

## 10. Out-of-scope (MVP-019)

- Reales DATEV-Lohn&Gehalt-/LODAS-Format (Folge-MVP, eigenes Profil).
- Lexware/Lexoffice-API (Folge-MVP).
- Automatischer Versand per E-Mail/SFTP (Folge).
- Stunden-zu-€-Berechnung (Lohn-Engine) — separates Modul.

## 11. Folge-MVPs

- DATEV-Profil (eigenes Issue).
- Lexware-Profil (eigenes Issue).
- Lohnarten-Mapping-UI.
- Automatische Lieferung (E-Mail/SFTP).
- Rechnungs-Export (Projekt-/Auftragsrechnung).

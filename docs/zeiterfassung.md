# Zeiterfassung in workDiary

Diese Dokumentation beschreibt das vereinheitlichte Zeiterfassungsmodell in
workDiary, das in den PRs 1–7 aufgebaut wurde. Sie richtet sich an Entwickler
und Administratoren, die das System konfigurieren, erweitern oder warten.

## 1. Konzeptioneller Überblick

workDiary erfasst Arbeitszeit auf drei Ebenen, die in Berichten zu einem
einzigen Bild zusammengeführt werden:

| Ebene       | Modell                            | Bedeutung                                                                                       |
| ----------- | --------------------------------- | ----------------------------------------------------------------------------------------------- |
| Anwesenheit | `Attendance`                      | Wann hat der/die Beschäftigte das Büro/Homeoffice physisch betreten und verlassen? (Stempeluhr) |
| Erfassung   | `TimeEntry`                       | Worauf wurde die Anwesenheitszeit verteilt? (Projekte, Verwaltung, Reise, …)                    |
| Soll        | `WorkSchedule` + `FlexCalculator` | Was sieht der Arbeitsvertrag pro Tag vor?                                                       |
| Reise       | `TravelLog`                       | Dienstfahrten mit km-Erstattung und gepaartem `TimeEntry`                                       |

`Attendance` ist die _Quelle der Wahrheit_ für die geleistete physische
Arbeitszeit; `TimeEntry`-Datensätze ergeben sich daraus durch fachliche
Zuordnung (s. `WorkBalanceCalculator`).

## 2. TimeEntry — Tätigkeitsarten

`TimeEntry::activity_type` klassifiziert jeden Eintrag nach Tätigkeit:

| Wert       | Bedeutung                                                | `project_id` nötig? |
| ---------- | -------------------------------------------------------- | ------------------- |
| `project`  | Projektarbeit (Kunden- oder internes Projekt)            | ✔                   |
| `admin`    | Verwaltung (z. B. HR, Buchhaltung)                       | —                   |
| `training` | Weiterbildung, Schulung                                  | —                   |
| `meeting`  | Allgemeine Besprechung ohne Projektbezug                 | —                   |
| `internal` | Sonstige interne Tätigkeit                               | —                   |
| `travel`   | Reisezeit (i. d. R. von `TravelLog` automatisch erzeugt) | —                   |
| `break`    | Pause (informativ, zählt nicht zur Arbeitszeit)          | —                   |
| `absence`  | Abwesenheit (Krank, Urlaub, …)                           | —                   |
| `standby`  | Rufbereitschaft                                          | —                   |
| `other`    | Sonstiges                                                | —                   |

Zusätzlich klassifiziert `TimeEntry::kind` die _Art_ der Zeit (`work`,
`travel`, `standby`). Reports gruppieren standardmäßig nach `kind` und
`activity_type`.

Die Validierungsregel im Model-Hook erzwingt: `activity_type = project ⇒
project_id required`. Bei Verstoß wird eine `InvalidArgumentException`
geworfen.

## 3. Verwaltungszeiten

Über `routes/web.php → admin-time-entries.*` und den
[`AdminTimeEntryController`](../app/Http/Controllers/AdminTimeEntryController.php)
können Nutzer\*innen Verwaltungszeit ohne Projektbezug erfassen.

`ActivityCategory` (mit `key` + `label` + Admin-Flag) erlaubt eine feingranulare
Buchführung; pro Organisation eindeutig.

## 4. Reisezeiten und Fahrtenbuch

`TravelLog` speichert pro Dienstfahrt:

- `distance_km` (Einfachstrecke), `round_trip` (verdoppelt für Erstattung)
- `vehicle` (`private`, `company`, `public_transport`, `bicycle`, `foot`, `other`)
- `rate_per_km` (optional, sonst aus `config('timesheet.travel.rates')`)
- `started_at`/`ended_at` ⇒ automatisch gepaarter `TimeEntry` mit
  `kind=travel`, `activity_type=travel`, `billable=false`

Der gepaarte TimeEntry wird über
[`TravelLogService::syncTimeEntry`](../app/Services/Travel/TravelLogService.php)
synchron gehalten (anlegen / updaten / löschen). Per Config abschaltbar via
`timesheet.travel.auto_create_time_entry`.

Erstattung = `distance_km × (round_trip ? 2 : 1) × rate_per_km`.

CSV-Export: `GET /travel-logs/export?from=&to=` (Semikolon-Delimiter, deutsche
Zahlenformatierung).

## 5. Pausenregeln (ArbZG §4)

Konfiguriert über `config/timesheet.php → breaks`:

```php
'breaks' => [
    'rules' => [
        ['after_minutes' => 360, 'required_minutes' => 30],
        ['after_minutes' => 540, 'required_minutes' => 45],
    ],
    'auto_apply' => true,
],
```

Bedeutung: Bei mehr als 6 Stunden Bruttoarbeitszeit sind mindestens 30 min
Pause erforderlich, bei mehr als 9 Stunden 45 min. Die Regeln sind erweiterbar
(z. B. firmeninterne Vorgaben).

Die Logik kapselt
[`BreakRuleEvaluator`](../app/Services/Timekeeping/BreakRuleEvaluator.php):

- `requiredMinutes(int $grossMinutes): int` — gibt die geforderte Pausenzeit
- `missingMinutes(Attendance $a): int` — gibt die Lücke zur Anforderung
- `applyMissingBreak(Attendance $a): int` — füllt `break_minutes_auto` auf

Beim Schließen eines `Attendance`-Eintrags (`saving`-Hook) wird die Lücke
automatisch in `break_minutes_auto` eingetragen, sofern
`timesheet.breaks.auto_apply = true`. Manuelle Pausen (`break_minutes_manual`)
gehen vor — wer 45 min selbst gestempelt hat, bekommt nichts zusätzlich.

`Attendance::break_minutes_total` ist die Summe aus `_auto` + `_manual` und
fließt direkt in `duration_minutes = gross − breaks` ein.

## 6. Soll-Berechnung & Flex

[`FlexCalculator`](../app/Services/Flextime/FlexCalculator.php) liefert pro
Tag/Monat die Soll-Minuten basierend auf `WorkSchedule` (Tages-Sollen,
Wochentage) und bekannten Abwesenheiten/Feiertagen.

## 7. Vereinheitlichte Arbeitsbilanz

[`WorkBalanceCalculator`](../app/Services/Reporting/WorkBalanceCalculator.php)
kombiniert Soll + Anwesenheit + Erfassung zu einem konsistenten Bild:

| Feld                | Bedeutung                                                                                  |
| ------------------- | ------------------------------------------------------------------------------------------ |
| `targetMinutes`     | Vertragliches Soll (FlexCalculator)                                                        |
| `attendanceMinutes` | Netto-Anwesenheit (= gross − Pausen). Offene Attendances werden bis `now()` hochgerechnet. |
| `breakMinutes`      | Summe aller Pausen                                                                         |
| `trackedMinutes`    | Erfasste Arbeitszeit (`kind = work`)                                                       |
| `untrackedMinutes`  | `max(0, attendance − tracked)` — noch zu verteilende Zeit                                  |
| `balanceMinutes`    | `tracked − target`                                                                         |
| `byActivity[]`      | Erfasste Minuten gruppiert nach `activity_type`                                            |
| `byKind[]`          | Erfasste Minuten gruppiert nach `kind`                                                     |

Der Report ist unter `reports/work-balance` erreichbar (Sidebar
„Arbeitsbilanz") und unterstützt `?scope=year` sowie PDF-Export (DomPDF):
`reports/work-balance?export=pdf`.

## 7a. Krankheiten (Arbeitsunfähigkeit)

Krankmeldungen werden im eigenen Modell `App\Models\SickLeave` verwaltet —
unabhängig von `Vacation`. Das frühere `Vacation::TYPE_SICK` ist deprecated und
wird nicht mehr für neue Datensätze verwendet (Bestandsdaten wandern per
Migration in `sick_leaves`).

### Datenmodell

| Feld                                 | Bedeutung                                                                          |
| ------------------------------------ | ---------------------------------------------------------------------------------- |
| `kind`                               | `initial` (Erst-Bescheinigung) oder `follow_up` (Folge-AU, mit `follow_up_for_id`) |
| `start_date` / `end_date`            | Krankheitszeitraum (inkl.)                                                         |
| `au_number` / `doctor_name`          | Optionale Stammdaten der AU-Bescheinigung                                          |
| `kasse_notified_at`                  | Zeitpunkt der Krankenkassen-Meldung (optional, manuell)                            |
| `cancelled_at` / `cancel_reason`     | Stornierung — Datensatz bleibt für Audit erhalten                                  |
| Polymorphe `attachments`             | AU-Bescheinigungs-Scans (PDF/JPG/PNG/HEIC) via `HasAttachments`                    |

### AU-Pflicht & Upload

`config/sickness.php#attachment_required_from_day` (Default `4`) erzwingt in
`SaveSickLeaveRequest`, dass ab dem n. Kalendertag der Krankmeldung mindestens
eine AU-Bescheinigung hochgeladen sein muss. Erlaubte Dateitypen und Größe sind
ebenfalls dort konfiguriert. Downloads laufen ausschließlich über signierte
URLs (`sick-leaves.attachments.download`).

### Lohnfortzahlung (§ 3 EntgFG)

`App\Services\Sickness\ContinuedPaymentService` berechnet pro Mitarbeiter den
aktuellen Anspruch (`continued_pay_weeks` × 7 Kalendertage = i. d. R. 42 Tage).
Eine _Krankheits-Episode_ umfasst zusammenhängende SickLeave-Einträge — Folge-
Bescheinigungen (`follow_up_for_id`) verlängern die Episode, isolierte
Krankmeldungen ohne Lücke von `chain_reset_after_months` Monaten ebenfalls
(konservative Auslegung; Diagnose unbekannt). Der Status (`usedDays`,
`remainingDays`, `exhausted`, `exhaustionDate`) wird im Tab _Duties → Krank_
als Fortschrittsbalken angezeigt.

### Workflow

- Erfassen: Toolbar-Button „Krank melden" im Duties-Reiter _Krank_ öffnet das
  Modal (`?dialog=1`). Nutzer dürfen nur sich selbst erfassen, Admins jeden.
- Auto-Approve: Es gibt keinen Genehmigungsschritt — gemeldet = wirksam.
- Stornieren: PATCH `sick-leaves.cancel` mit optionalem Grund; setzt
  `cancelled_at`.
- Reporting:
  - `reports.absences` zählt _Werktage krank_ nun aus `sick_leaves` (nicht
    mehr aus `vacations`).
  - `reports.sickness` liefert detaillierte Episoden, AU-Quote und den
    Lohnfortzahlungs-Status pro Mitarbeiter.

## 8. Konfigurations-Cheat-Sheet

| Env-Variable                       | Default | Wirkung                                            |
| ---------------------------------- | ------- | -------------------------------------------------- |
| `TIMESHEET_DEFAULT_WEEKLY_MINUTES` | 2400    | Wochen-Soll (Default-WorkSchedule)                 |
| `TIMESHEET_DEFAULT_DAILY_MINUTES`  | 480     | Tages-Soll                                         |
| `TIMESHEET_BREAK_AUTO_APPLY`       | true    | ArbZG-Pausen automatisch füllen                    |
| `TRAVEL_RATE_PRIVATE_KM`           | 0.30    | Erstattungssatz Privat-PKW (€/km)                  |
| `TRAVEL_RATE_BICYCLE_KM`           | 0.05    | Erstattungssatz Fahrrad                            |
| `TRAVEL_AUTO_TIME_ENTRY`           | true    | Reise-Zeiten automatisch als `TimeEntry` einbuchen |
| `SICKNESS_AU_REQUIRED_FROM_DAY`    | 4       | Ab welchem Kalendertag eine AU-Bescheinigung Pflicht ist |
| `SICKNESS_CONTINUED_PAY_WEEKS`     | 6       | Lohnfortzahlung nach § 3 EntgFG (Wochen)           |
| `SICKNESS_CHAIN_RESET_MONTHS`      | 6       | Frist (Monate), nach der eine neue Krankheitsepisode den Anspruch zurücksetzt |

## 9. Wichtige Hinweise für Entwickler

- Tests laufen mit `php artisan test` (ohne `--parallel`); Setup verlangt
  `RolesSeeder` + `Tests\Concerns\WithOrganization::setUpOrganization()`.
- Das Trait `BelongsToOrganization` überschreibt `organization_id = null` im
  `creating`-Event mit der gerade gebundenen Organisation. Wenn ein Seeder
  bewusst eine globale (organisationslose) Ressource anlegen will:
  `Model::withoutEvents(fn () => …)`.
- Auf jeder neuen PHP-Datei sitzt der Lizenzheader `AGPL-3.0-or-later` mit
  Autor _Daniel Jörg Schuppelius_ (siehe bestehende Dateien als Vorlage).
- Code-Style: `pint.json` (Preset `laravel`).

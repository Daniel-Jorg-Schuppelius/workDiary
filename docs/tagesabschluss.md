# Tagesabschluss

Status: Aktiv (MVP-015, Issue #15) • Quelle:
[Feature 001 — Zeiterfassung als Kernprodukt](features/001-zeiterfassung-kernprodukt.md).
• Verbunden mit:
[Auftrags-Lebenszyklus](auftrags-lebenszyklus.md),
[Auftrags-Timeline](auftrags-timeline.md),
[Status- und Aktionsglossar](status-aktionsglossar.md),
[UX-Pattern-Katalog](ux-pattern-katalog.md),
[Zeiterfassung in workDiary](zeiterfassung.md).

## 1. Zweck

Eine **eine** Seite pro Mitarbeitendem, auf der ein Tag am Ende eindeutig
**als vollständig erfasst** abgehakt werden kann — oder fehlende Daten
sichtbar nachgetragen werden.

Ziele:

- Anwesenheit, Pausen, verbuchte Zeit und offene Restzeit auf einen Blick.
- Lücken, Inkonsistenzen und Warnungen sichtbar (nicht still korrigiert).
- Eine eindeutige **Abschluss-Aktion** mit Audit-Spur.
- Grundlage für spätere Monatsfreigabe (Folge-MVP).

## 2. Seitenaufbau

Route: `/tagesabschluss?date=YYYY-MM-DD` (Default `today`).

```
┌─────────────────────────────────────────────────────────────────────┐
│ Header: Datum-Picker (◄ heute ►) + Statuspille (siehe §6)           │
├─────────────────────────────────────────────────────────────────────┤
│ A) Anwesenheit (Bruttozeit)                                         │
│ B) Pausen-Übersicht                                                 │
│ C) Auftrags-/Projektzeiten                                          │
│ D) Lücken & Warnungen                                               │
│ E) Bilanz: Soll/Ist/Saldo                                           │
│ F) Aktionen: Speichern / Tag abschließen / Korrektur anfordern      │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.1 Anwesenheit (A)

Stempel-Ereignisse (`Attendance`) als Zeitstrahl:

| Zeit  | Aktion       | Quelle                      |
| ----- | ------------ | --------------------------- |
| 07:32 | Kommen       | Stempeluhr (Browser)        |
| 12:00 | Pause Beginn | Auto-Regel „>= 6h Arbeit"   |
| 12:30 | Pause Ende   | Stempeluhr                  |
| 16:45 | Gehen        | Stempeluhr                  |

- Bearbeitbar nur über Korrektur-Antrag (§5).
- Aktion „Jetzt stempeln" wenn Tag = heute und offen.

### 2.2 Pausen (B)

Aggregat: erfasste Pause vs. Pflichtpause laut Arbeitsrecht (z. B.
30 min ab 6h, 45 min ab 9h). Warnung bei Unterschreitung.

### 2.3 Auftrags-/Projektzeiten (C)

Liste der `TimeEntry`-Buchungen des Tages mit Dauer, Auftrag/Projekt
(Link → Fallakte), Aktivität, Kommentar, billable-Flag.
Aktion „Zeit buchen" öffnet Modal mit Suche (Auftrag, Projekt,
interne Tätigkeit).

Quick-Buchung: Drag-Strecken-Auswahl auf dem Tageszeitstrahl erstellt
eine Buchung mit Default-Auftrag (zuletzt genutzt).

### 2.4 Lücken & Warnungen (D)

Automatische Checks (siehe §4) mit Tone aus
[Status-Glossar](status-aktionsglossar.md):

- ⛔ Hart (Block für Abschluss): nicht verteilte Anwesenheit,
  Pflichtpause nicht erfüllt, offener Stempel.
- ⚠ Weich (Hinweis): Saldo > +/-2 h, Buchung ohne Kommentar an
  abrechnungsrelevantem Auftrag, mehr als 10h gearbeitet.

### 2.5 Bilanz (E)

| Kennzahl       | Wert                                            |
| -------------- | ----------------------------------------------- |
| Soll-Stunden   | aus Arbeitszeitmodell für dieses Datum          |
| Anwesenheit    | Brutto (A)                                      |
| Pause          | Summe (B)                                       |
| Netto-Arbeit   | Anwesenheit − Pause                             |
| Verbucht       | Summe `TimeEntry` (C)                           |
| Differenz      | Netto-Arbeit − Verbucht (sollte 0 sein)         |
| Saldo Tag      | Netto-Arbeit − Soll-Stunden                     |
| Saldo lfd. M.  | Aggregat (Gleitzeit-Konto)                      |

### 2.6 Aktionen (F)

| Aktion                    | Tone     | Vorbedingung                              |
| ------------------------- | -------- | ----------------------------------------- |
| `day.save`                | ghost    | jederzeit (Buchungen werden gespeichert)  |
| `day.close`               | primary  | keine ⛔-Warnung; offen, nicht zukünftig.  |
| `day.requestCorrection`   | warning  | bereits abgeschlossen, ändert Status zu „in Korrektur". |
| `day.reopen` (Admin)      | warning  | nur Org-Admin, mit Audit-Grund.            |

## 3. Status des Tages

| Status     | Wert | Bedeutung                                                  |
| ---------- | ---- | ---------------------------------------------------------- |
| `open`     |   0  | Default; Buchungen frei änderbar.                          |
| `closed`   |   1  | Mitarbeitender hat abgeschlossen; gesperrt für Selbst.     |
| `correction` | 2  | Korrektur beantragt; Admin-Review.                         |
| `locked`   |   3  | Teil einer freigegebenen Monatsabrechnung (Folge-MVP).     |

Aufgenommen ins
[Status-/Aktionsglossar](status-aktionsglossar.md) als Domäne `dayClose`.

## 4. Konsistenzprüfungen

Implementiert als `DayClosureValidator` (testbar):

| Check                        | Schweregrad | Beschreibung                                 |
| ---------------------------- | ----------- | -------------------------------------------- |
| `attendance.missing_close`   | ⛔          | Stempeluhr noch offen.                       |
| `time.unallocated_minutes`   | ⛔          | Anwesenheit minus Buchungen > 5 min.         |
| `break.required`             | ⛔          | Pflichtpause nicht erreicht (Schwellen §2.2).|
| `time.gap_in_attendance`     | ⚠          | Anwesenheits-Lücke > 15 min ohne Pausen-Marker. |
| `balance.threshold`          | ⚠          | Tages-Saldo > ±2 h.                          |
| `entry.missing_comment`      | ⚠          | Abrechnungsrelevante Buchung ohne Kommentar. |
| `worktime.overrun`           | ⚠          | Netto-Arbeit > 10 h (ArbZG).                 |

Diese Checks erscheinen in Sektion D, sortiert nach Schweregrad.

## 5. Korrektur-Workflow

1. Tag ist `closed` oder `locked`.
2. Mitarbeiter klickt „Korrektur anfordern" → Modal mit Begründung
   (Pflichtfeld, mind. 20 Zeichen) und vorgeschlagenen Änderungen.
3. Antrag erzeugt Datensatz `day_correction_requests` und Audit
   `dayClose.correctionRequested`.
4. Org-Admin sieht Antrag im Genehmigungs-Inbox (Folge-MVP), kann
   freigeben (`dayClose.reopen` + Audit) oder ablehnen.
5. Freigabe setzt Status zurück auf `open`, sperrt jedoch Anwesenheits-
   Stempel (nur Buchungen änderbar) bis erneutem `close`.

## 6. Audit

| Event                              | Trigger                                  |
| ---------------------------------- | ---------------------------------------- |
| `dayClose.opened`                  | Mitarbeitender öffnet Tag (1×/Tag).      |
| `dayClose.entrySaved`              | Speichern Buchung (kein Status-Wechsel). |
| `dayClose.closed`                  | Aktion `day.close`.                      |
| `dayClose.correctionRequested`     | §5 Schritt 3.                            |
| `dayClose.correctionApproved`      | Admin-Freigabe.                          |
| `dayClose.correctionRejected`      | Admin-Ablehnung.                         |
| `dayClose.reopened`                | Admin-Reopen ohne Antrag (Pflicht-Begründung). |

## 7. Permissions

| Permission                        | Wer                                |
| --------------------------------- | ---------------------------------- |
| `dayClose.view.own`               | Mitarbeitender, eigene Tage.       |
| `dayClose.view.team`              | Teamleitung, Teammitglieder.       |
| `dayClose.view.organization`      | Org-Admin.                         |
| `dayClose.close.own`              | Mitarbeitender, eigener Tag.       |
| `dayClose.requestCorrection.own`  | Mitarbeitender.                    |
| `dayClose.approveCorrection`      | Org-Admin / Teamleitung.           |
| `dayClose.reopen`                 | Nur Org-Admin (Audit-Pflicht).     |

## 8. UI-Pattern

- `<x-card>` pro Sektion A-E, Header mit Icon (`schedule`, `pause`,
  `assignment`, `report`, `analytics`).
- Warnungen als `<x-banner tone="…">` direkt unter dem jeweiligen
  Aggregat plus zusammengefasst in Sektion D.
- Hauptaktion „Tag abschließen" als sticky CTA am unteren Rand auf
  Mobile.
- Tastatur-Shortcut `Ctrl+Enter` = Tag abschließen (nur wenn keine
  ⛔-Warnung).

## 9. Akzeptanzkriterien

1. Tagesabschluss-Seite bündelt Anwesenheit, Pausen, Buchungen, Bilanz,
   Warnungen.
2. „Tag abschließen" ist nur bei keiner ⛔-Warnung verfügbar; sonst
   `disabled` + Begründungstooltip.
3. `DayClosureValidator` führt die 7 Checks aus §4 mit Tests aus.
4. Status-Übergänge gemäß §3 + Audit-Events §6.
5. Korrektur-Workflow erzeugt `day_correction_requests` und alle Audits.
6. Permissions §7 sind serverseitig in Policy + Test.
7. Mobile-Layout funktioniert (sticky CTA).
8. Lighthouse Accessibility ≥ 95 für die Seite.

## 10. Out-of-scope (MVP-015)

- Monatsfreigabe / Monatsabrechnung (Folge-MVP).
- Vorgesetzten-Inbox für Korrekturanträge (Folge).
- Mobile-Offline-Modus (Folge).
- Teamübersicht „offene Tagesabschlüsse" (Folge).

## 11. Folge-MVPs

- **MVP-016** Monatsfreigabe — bündelt geschlossene Tage zu einem
  prüfbaren Monat.
- **MVP-017** Korrektur-Inbox.
- **MVP-018** Plan/Ist-Abgleich gegen Schichtplan.

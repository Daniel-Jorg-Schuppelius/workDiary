# Onboarding-Checkliste für neue Organisationen

Status: Aktiv (MVP-048, Issue #47) • Quellen:
[Feature 020 — Import / Migration / Onboarding](features/020-import-migration-onboarding.md),
[Feature 039 — Hilfe / Dokumentation In-App](features/039-hilfe-dokumentation-in-app.md).

## 1. Zweck

Eine neu angelegte Organisation soll in **klar definierten
Schritten** zu einem funktionierenden Mandanten geführt werden.
Org-Admin sieht ein Dashboard-Widget mit Fortschrittsbalken; jeder
Schritt verlinkt direkt in die jeweilige Verwaltung.

## 2. Checklisten-Schritte (Reihenfolge)

| #   | Schritt                                                       | Pflicht   | Akzeptanzbedingung                                                                       |
| --- | ------------------------------------------------------------- | --------- | ---------------------------------------------------------------------------------------- |
| 1   | Organisationsdaten vervollständigen (Name, Anschrift, USt-ID) | ja        | `organizations` Pflichtfelder gefüllt                                                    |
| 2   | Branchenprofil wählen (IT-Service / Handwerk)                 | ja        | `organizations.branch_profile_code` gesetzt; [Branchenprofil-Doku](branchenprofil-it.md) |
| 3   | Erste Nutzer einladen (≥ 2 Personen)                          | empfohlen | ≥ 2 aktive Memberships                                                                   |
| 4   | Rollen prüfen (mind. 1 Org-Admin + 1 Operator)                | ja        | Spatie-Rollen vorhanden                                                                  |
| 5   | Kategorien/Klassifikationen prüfen oder überschreiben         | empfohlen | mind. 1 Klassifikationsdomain bestätigt; [Kategorien-Org](kategorien-org.md)             |
| 6   | Ersten Kunden anlegen oder per CSV importieren                | ja        | `customers` ≥ 1; [CSV-Import](csv-import.md) (MVP-049)                                   |
| 7   | Erstes Projekt / ersten Auftrag anlegen                       | ja        | `projects` ≥ 1 ODER `diary_entries` ≥ 1                                                  |
| 8   | Erste Zeiterfassung erstellen                                 | empfohlen | `time_entries` ≥ 1                                                                       |
| 9   | Erstes Protokoll erzeugen und unterschreiben                  | empfohlen | `protocols.status = signed` ≥ 1                                                          |
| 10  | Backup-Heartbeat konfigurieren (nur On-Premise)               | empfohlen | `backup_heartbeats` letzter < 26 h                                                       |

## 3. Datenmodell

### 3.1 `onboarding_progress`

| Feld                   | Typ           | Notizen                             |
| ---------------------- | ------------- | ----------------------------------- |
| id                     | uuid          |                                     |
| organization_id        | uuid          | unique                              |
| step_code              | string        | z. B. `org.profile`, `users.invite` |
| state                  | enum          | open, done, skipped                 |
| done_at                | datetime null |                                     |
| done_by_user_id        | uuid null     |                                     |
| skipped_reason         | string null   |                                     |
| created_at, updated_at | datetime      |                                     |

Eine Zeile pro (Organisation × Schritt).

### 3.2 Auflösung

`OnboardingChecklistResolver::for(org)` prüft Bedingungen aus §2
**live** (nicht aus `state`-Feld) und aktualisiert
`onboarding_progress` opportunistisch (Hintergrund-Job oder bei
Aufruf der Seite).

## 4. UI

### 4.1 Dashboard-Widget

- Fortschrittsbalken X von Y (nur Pflichtschritte gezählt).
- Klappbare Schritt-Liste, je Schritt: Icon (`task_alt` / `radio_button_unchecked`), Titel, Direkt-Link, „Überspringen"-Aktion (mit Begründungs-Eingabe).
- Wenn alle Pflichtschritte erledigt: Widget zeigt
  Erfolgskarte und kann manuell ausgeblendet werden
  (`onboarding_widget_dismissed_at`).

### 4.2 Detailseite

`GET /onboarding` mit Beschreibung + Hilfetext je Schritt
(Anbindung [In-App-Hilfe](in-app-hilfe.md) MVP-051).

## 5. Permissions

| Permission                     | Wer       |
| ------------------------------ | --------- |
| `org.onboarding.view`          | Org-Admin |
| `org.onboarding.skipStep`      | Org-Admin |
| `org.onboarding.dismissWidget` | Org-Admin |

## 6. Audit

`onboarding.stepCompleted`, `onboarding.stepSkipped`,
`onboarding.completed`, `onboarding.widgetDismissed`.

## 7. Akzeptanzkriterien

1. Migration `onboarding_progress`.
2. `OnboardingChecklistResolver` mit Tests pro Bedingung §2.
3. Dashboard-Widget + Detailseite.
4. „Überspringen" erfordert Begründung; nicht möglich für Pflichtschritte mit Hard-Bedingung (Schritt 1, 4, 6, 7).
5. Audit-Events §6.
6. Lokalisierung de + en für Schritt-Titel und Beschreibungen.

## 8. Out-of-scope (MVP-048)

- Interaktive Onboarding-Tour (Tooltips über UI-Elemente).
- E-Mail-Erinnerungen bei stagnierendem Fortschritt.
- Org-übergreifende Vorlagen.

## 9. Folge

- MVP-049 CSV-Import deckt Schritte 3/6 inhaltlich ab.
- MVP-050 Demo-Mandant nutzt dieselben Schritte vollständig
  „durchspielt".
- MVP-051 In-App-Hilfe liefert Schritt-Texte.

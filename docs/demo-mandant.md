# Demo-Mandant mit vollständigem Beispielauftrag

Status: Aktiv (MVP-050, Issue #49) • Quelle:
[Feature 040 — Demo / Testdaten / Musterbranchen](features/040-demo-testdaten-musterbranchen.md).

## 1. Zweck

Ein neuer Interessent / neue Org-Admin soll WorkDiary in **zwei
Klicks** vollständig „in Aktion" sehen: einen Mandanten mit
realistischen Daten, der jeden Hauptprozess (Auftrag → Zeiterfassung
→ Protokoll → Prozedur → Auswertung) **mindestens einmal** vollständig
durchläuft.

## 2. Bereitstellungs-Modi

| Modus             | Wer kann auslösen                       | Wirkung                                                                       |
| ----------------- | --------------------------------------- | ----------------------------------------------------------------------------- |
| `freshDemoOrg`    | Plattform-Admin                         | Erzeugt neue Org mit Suffix `(Demo)`, fügt aktuellen User als Org-Admin hinzu |
| `seedExistingOrg` | Org-Admin (nur leere Org)               | Befüllt vorhandene leere Org mit Demo-Daten                                   |
| `resetDemoOrg`    | Plattform-Admin auf bestehende Demo-Org | Löscht alle Demo-Daten der Org und seedt neu                                  |

Demo-Mandant trägt Marker `organizations.is_demo = true`.

## 3. Inhalt (Vollständiger Beispielauftrag „Server-Migration ACME")

Der Demo-Mandant erhält:

- **Branchenprofil**: IT-Service ([Doku](branchenprofil-it.md)).
- **3 Kunden** (ACME GmbH, Beispiel-Apotheke, Mustermann KG).
- **5 Projekte** (verteilt auf die Kunden).
- **6 Nutzer**: 1 Org-Admin (= ausführender Tester), 2 Operator,
  1 Disponent, 1 Kunde-Rolle (für Customer-Portal-Demo), 1
  Read-Only-Auditor.
- **15 Materialien** (Switch, Patchkabel, USV, etc.).
- **2 Assets** (Demo-Server „ACME-SRV-01", Demo-USV
  „ACME-USV-01").

Hauptauftrag „Server-Migration ACME" zeigt:

1. **Auftrag** mit Plan-Dauer 480 min, Klassifikation
   `entryType=migration`, Asset-Verknüpfung
   ([Asset-Verknüpfungen](asset-verknuepfungen.md)).
2. **3 Zeiterfassungen** (Mitarbeiter A 180 min, Mitarbeiter B
   120 min, Mitarbeiter A 220 min) → Ist-Dauer 520 min →
   Overrun 8 % → in Reports sichtbar.
3. **1 Prozedur-Run** „Server-Inbetriebnahme-Checkliste" mit
   Backup-Schritt (bewiesen via `procedure_backup_proofs`) und
   Vier-Augen-Schritt ([Prozedur-Pflicht](prozedur-pflicht.md)).
4. **1 Protokoll** Typ `abnahme`, signiert mit Methode `drawing`,
   PDF gerendert ([Abnahme-Signatur](abnahme-signatur.md)).
5. **1 offener Punkt** (`open_issues`) Severity `medium` mit Bezug
   auf den Auftrag ([Offene Punkte](offene-punkte.md)).
6. **2 Anhänge** (Beispiel-Foto JPEG aus
   `database/seeders/demo/assets/`, Beispiel-PDF).
7. **1 Audit-Log-Eintrag** je relevanter Aktion (automatisch durch
   normale Listener).

Daneben „Hintergrund-Rauschen": 25 weitere kleinere Aufträge der
letzten 60 Tage, gleichmäßig verteilt, damit Reports/Trends sinnvoll
aussehen.

## 4. Erzeugung

`DemoSeederService::seed(org, options)` ruft eine **fixierte Folge
deterministischer Seeder** auf (`database/seeders/demo/*.php`):

1. `DemoBranchProfileSeeder` → IT-Profil installieren.
2. `DemoMastersSeeder` → Kunden, Projekte, Materialien, Assets.
3. `DemoUsersSeeder` → Demo-Nutzer + Spatie-Rollen.
4. `DemoMainCaseSeeder` → Server-Migration ACME.
5. `DemoBackgroundSeeder` → 25 historische Aufträge.

Determinismus: Fester Faker-Seed `42`, fixe IDs (UUIDs aus
`Str::uuid5()` mit Namespace-UUID). Damit ist
„Reset" reproduzierbar und Tests stabil.

## 5. Datenmodell

Keine eigene Tabelle nötig. Ergänzungen:

- `organizations.is_demo` boolean default false.
- `organizations.demo_seeded_at` datetime null.

Demo-Daten tragen kein gesondertes Flag pro Datensatz; bei
`resetDemoOrg` werden alle Datensätze der Org gelöscht (außer
Org selbst und Memberships).

## 6. Lösch-Sicherheit

- Demo-Org darf nicht in Reports anderer Org-Listen einfließen.
- Bei `is_demo = true` zeigt das UI in der Topbar einen gelben
  Banner „Dies ist ein Demo-Mandant".
- In Auswertungen wird Demo-Daten nicht in Plattform-übergreifende
  Statistiken aufgenommen.

## 7. Permissions

| Permission             | Wer                       |
| ---------------------- | ------------------------- |
| `platform.demo.create` | Plattform-Admin           |
| `platform.demo.reset`  | Plattform-Admin           |
| `org.demo.seed`        | Org-Admin (nur leere Org) |

## 8. Audit

`demo.orgCreated`, `demo.seeded`, `demo.reset` mit Counts der
erzeugten Datensätze.

## 9. Akzeptanzkriterien

1. `DemoSeederService` deterministisch (gleicher Aufruf → gleiche
   IDs/Inhalte).
2. Hauptauftrag „Server-Migration ACME" deckt alle in §3 genannten
   Bausteine ab.
3. Reports zeigen verwendbare Zahlen (mindestens 25
   Hintergrund-Aufträge).
4. Reset-Modus löscht ausschließlich Demo-Org-Daten.
5. Demo-Banner sichtbar bei `is_demo = true`.
6. Audit-Events §8.
7. Seeder läuft < 30 s.

## 10. Out-of-scope (MVP-050)

- Mehrere Branchenprofile parallel im Demo-Mandanten.
- Sandbox-Tour mit geführter Walkthrough-UI (Feature 039).
- Anonymisierung echter Kundendaten in Demo-Daten umwandeln.

## 11. Folge

- MVP-051 In-App-Hilfe nutzt Demo-Mandanten als „Sandbox".

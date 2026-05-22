# Branchenprofil Handwerk/Service allgemein

Status: Aktiv (MVP-034, Issue #34) • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).
• Aufbauend auf:
[Branchenprofil IT-Service](branchenprofil-it.md) (MVP-033, gleiche
Mechanik).

## 1. Zweck

Zweites Referenzprofil: deckt **allgemeine Handwerks- und
Serviceeinsätze** ab (Service-Techniker beim Kunden, Wartung,
Reparatur, Aufmaß, Material), branchenneutral genug, dass Elektro,
SHK, Facility, Maler, Trockenbau usw. damit starten und es bei Bedarf
spezialisieren können.

## 2. Strukturparallele

Aufbau identisch zu MVP-033:
`database/data/branchprofiles/handwerk.php` → Installer
`BranchProfileInstaller::install(org, "handwerk")`. Idempotent,
auditiert (`branch_profile.installed`).

## 3. Klassifikationen (Auszug)

| Domain              | Codes                                                                   |
| ------------------- | ----------------------------------------------------------------------- |
| `entry_type`        | service, maintenance, repair, installation, inspection, advice, aufmass |
| `activity`          | install, dismantle, repair, measure, document, cleanUp                  |
| `defect_type`       | mechanical, electrical, plumbing, surface, wear, accidental             |
| `root_cause`        | wear, misuse, defect, installation, externalDamage                      |
| `result`            | resolved, partialResolved, materialMissing, customerDecided, escalated  |
| `product_group`     | heater, boiler, sanitary, lighting, switchgear, surface, door, window   |
| `dienstmittel_type` | tool, ladder, vehicle, lift, instrument                                 |

## 4. Pflichtklassifikationen

- `entry_type=repair`: Pflicht `defect_type` `onCreate`; Pflicht
  `root_cause`, `result` `beforeComplete`.
- `entry_type=installation`: Pflicht `product_group` `onCreate`;
  Pflicht `result` `beforeComplete`.
- `entry_type=maintenance`: Pflicht `product_group` `onCreate`;
  Pflicht `result` `beforeComplete`.
- `entry_type=aufmass`: Pflicht `result` `beforeComplete` (Werte z. B.
  `resolved`, `customerDecided`).

Alle als `severity = hard`, außer `defect_type` bei `service` =
`soft`.

## 5. Prozedurvorlagen

Mit Code-Präfix `HW_`:

| Code                   | Anwendung                                  |
| ---------------------- | ------------------------------------------ |
| `HW_SERVICE_CALL`      | Service-Einsatz beim Kunden                |
| `HW_MAINTENANCE`       | Wartung mit Prüfschritten und Foto-Pflicht |
| `HW_REPAIR`            | Reparatur (Diagnose, Material, Test)       |
| `HW_INSTALL_DEVICE`    | Installation eines Geräts                  |
| `HW_INSPECTION`        | Wiederkehrende Prüfung mit Messwerten      |
| `HW_AUFMASS`           | Aufmaß / Bestandsaufnahme                  |
| `HW_HANDOVER_CUSTOMER` | Übergabe an Kunden mit Foto + Unterschrift |

Jede Vorlage:

- Pflichtfotos (vor / nach, ggf. Detail),
- Materialerfassung,
- Funktions-/Sichtprüfung,
- optionale Kundenunterschrift (Abnahme).

## 6. Protokollvorlagen

| Code                   | Zweck                                  |
| ---------------------- | -------------------------------------- |
| `HW_SERVICEBERICHT`    | Klassischer Servicebericht             |
| `HW_WARTUNGSPROTOKOLL` | Wartung mit Prüfpunkten                |
| `HW_ABNAHMEPROTOKOLL`  | Abnahme inkl. Mängelliste + Restpunkte |
| `HW_INSPEKTION`        | Inspektion mit Messwerten              |
| `HW_AUFMASS`           | Aufmaß-Protokoll                       |
| `HW_GERAETEUEBERGABE`  | Geräteübergabe                         |

## 7. Asset-Kategorien (Vorbereitung MVP-035)

`heater`, `boiler`, `pump`, `hvacUnit`, `sanitaryFixture`,
`switchgear`, `lightFixture`, `door`, `window`, `vehicle`,
`largeTool`, `meteringDevice`.

## 8. Tags-Seed

`#emergency`, `#follow-up-required`, `#waiting-for-material`,
`#customer-vip`, `#new-build`, `#renovation`, `#warranty`.

## 9. Kompatibilität mit IT-Profil

Beide Profile teilen keine `code`-Kollisionen — `entry_type=service`
ist hier neutral und kann mit dem IT-Profil koexistieren. Bei
Mischbetrieben kann Org-Admin beide Profile installieren.

## 10. Akzeptanzkriterien

1. `database/data/branchprofiles/handwerk.php` enthält Inhalte aus
   §3–§8 vollständig.
2. Installer idempotent + auditiert.
3. Tests: install + reinstall ohne Duplikate; gemeinsame Installation
   mit `it`-Profil führt zu erwarteter Vereinigung ohne Konflikte.
4. Onboarding-Wizard (MVP-048) listet das Profil mit Beschreibung
   „Allgemeines Service- und Handwerksprofil".

## 11. Out-of-scope (MVP-034)

- Branchenfeine Spezialprofile (Elektro, SHK, Maler…) — Folge.
- Versions-Updates eines Profils — Folge.

## 12. Folge

Damit ist der Klassifikations-/Branchen-Cluster (MVP-030..034)
abgeschlossen. Nächster Cluster: Asset-Stammdaten (MVP-035..038).

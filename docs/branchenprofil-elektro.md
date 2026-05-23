# Branchenprofil Elektro

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).
• Aufbauend auf:
[Branchenprofil Handwerk/Service](branchenprofil-handwerk.md) (MVP-034,
gleiche Mechanik).

## 1. Zweck

Referenzprofil für **Elektroinstallations- und Elektrotechnikbetriebe**.
Bildet Installation, Wartung, Prüfung, Störung, Messung, Verteilerarbeiten,
E-Mobilität, PV-nahe Arbeiten und Kundenabnahmen ab. Fokus:
prüffähige Dokumentation mit Messwerten, Fotos, Stromkreis-/Anlagenbezug,
Sicherheitsfreigaben und nachvollziehbaren Abnahmen.

## 2. Strukturparallele

`database/data/branchprofiles/elektro.php` → Installer
`BranchProfileInstaller::install(org, "elektro")`.
Idempotent, auditiert (`branch_profile.installed`).

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                                                       |
| --------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `entry_type`    | installation, wartung, stoerung, pruefung, messung, verteilerarbeit, eCheck, wallbox, pvAnschluss, abnahme, nacharbeit      |
| `activity`      | freischalten, messen, anschliessen, verdrahten, beschriften, pruefen, dokumentieren, fehlersuche, inbetriebnehmen           |
| `defect_type`   | kurzschluss, erdschluss, ueberlast, defekteSicherung, loseKlemme, isolationsfehler, falscheBeschriftung, messwertAbweichung |
| `root_cause`    | verschleiss, feuchtigkeit, installation, bedienfehler, fremdgewerk, materialfehler, planungsfehler                          |
| `result`        | behoben, teilBehoben, freigegeben, nichtFreigegeben, nacharbeitNoetig, materialFehlt, eskaliert                             |
| `product_group` | verteiler, sicherung, leitung, steckdose, beleuchtung, wallbox, pvWechselrichter, zaehlerplatz, netzwerk, smartHome         |

## 4. Pflichtklassifikationen

- `entry_type=pruefung` / `eCheck`: Pflicht Messwerte, Prüfgrundlage,
  Prüfer und `result` `beforeComplete`.
- `entry_type=installation`: Pflicht `product_group`, Fotodokumentation
  und Kundenabnahme `beforeComplete`.
- `entry_type=stoerung`: Pflicht `defect_type` `onCreate`; Pflicht
  `root_cause` und `result` `beforeComplete`.
- `entry_type=verteilerarbeit`: Pflicht Freischalt-/Sicherheitsvermerk
  und Beschriftungsfoto.
- `entry_type=wallbox` / `pvAnschluss`: Pflicht Inbetriebnahmeprotokoll
  und Kunden-/Anlagenbezug.

## 5. Prozedurvorlagen

| Code                | Anwendung                                          |
| ------------------- | -------------------------------------------------- |
| `EL_STOERUNG`       | Fehlersuche, Messung, Behebung, Funktionstest      |
| `EL_INSTALLATION`   | Installation mit Foto, Prüfung und Kundenabnahme   |
| `EL_VERTEILER`      | Verteilerarbeit mit Freischaltung und Beschriftung |
| `EL_ECHECK`         | Wiederkehrende Prüfung mit Messwerten              |
| `EL_WALLBOX`        | Wallbox-Montage, Anschluss, Test und Einweisung    |
| `EL_INBETRIEBNAHME` | Inbetriebnahme elektrischer Anlage                 |

## 6. Protokollvorlagen

`EL_PRUEFPROTOKOLL`, `EL_MESSPROTOKOLL`, `EL_SERVICEBERICHT`,
`EL_ABNAHME`, `EL_VERTEILER_DOKU`, `EL_WALLBOX_INBETRIEBNAHME`.

## 7. Asset-Kategorien

`multimeter`, `installationstester`, `leiter`, `servicefahrzeug`,
`crimpzange`, `waermebildkamera`, `labeldrucker`, `bohrmaschine`,
`messadapter`, `psaElektro`.

## 8. Tags-Seed

`#vde`, `#freischaltung`, `#messwerte`, `#verteiler`, `#wallbox`,
`#stoerung`, `#nacharbeit`, `#kundenabnahme`.

## 9. Akzeptanzkriterien

1. `database/data/branchprofiles/elektro.php` enthält Inhalte aus §3-§8.
2. Installer idempotent + auditiert.
3. Pflichtfelder für Prüfung, Störung, Installation und Abnahme werden geprüft.
4. Onboarding-Wizard listet „Elektro".

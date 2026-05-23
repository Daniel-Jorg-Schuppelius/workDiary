# Branchenprofil Facility Management und Hausmeisterdienste

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).

## 1. Zweck

Referenzprofil für **Facility Management, Objektbetreuung und
Hausmeisterdienste**. Bildet Objektkontrollen, Kleinreparaturen,
Mängelmanagement, Schlüsselverwaltung, Winterdienst, Zählerstände,
Dienstleisterkoordination und wiederkehrende Betreiberpflichten ab.

## 2. Strukturparallele

`database/data/branchprofiles/facility.php` → Installer
`BranchProfileInstaller::install(org, "facility")`.

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                                             |
| --------------- | ----------------------------------------------------------------------------------------------------------------- |
| `entry_type`    | objektkontrolle, maengelmeldung, kleinreparatur, wartungsrunde, winterdienst, zaehlerstand, schluessel, notfall   |
| `activity`      | kontrollieren, reinigen, reparieren, dokumentieren, abschliessen, streuen, ablesen, beauftragen, nachhalten       |
| `defect_type`   | defekteBeleuchtung, wasserschaden, vandalismus, schliessproblem, brandschutzmangel, stolperstelle, verunreinigung |
| `root_cause`    | verschleiss, nutzung, wetter, fremdeinwirkung, mangelndeWartung, unbekannt                                        |
| `result`        | erledigt, offen, weitergeleitet, nacharbeit, materialFehlt, kundeInformiert, eskaliert                            |
| `product_group` | gebaeude, tuer, tor, beleuchtung, heizung, aufzug, aussenanlage, brandschutz, schluessel, zaehler                 |

## 4. Pflichtklassifikationen

- `entry_type=objektkontrolle`: Pflicht Objekt, Checkliste und Ergebnis.
- `entry_type=maengelmeldung`: Pflicht Foto, Ort, `defect_type` und
  Priorität.
- `entry_type=schluessel`: Pflicht Ausgabe/Rückgabe, Person und
  Schlüsselnummer.
- `entry_type=winterdienst`: Pflicht Zeitraum, Wetterlage und erledigte
  Flächen.

## 5. Prozedurvorlagen

`FM_OBJEKTKONTROLLE`, `FM_MAENGEL`, `FM_KLEINREPARATUR`,
`FM_WINTERDIENST`, `FM_SCHLUESSEL`, `FM_ZAEHLERSTAND`.

## 6. Protokollvorlagen

`FM_OBJEKTBERICHT`, `FM_MAENGELPROTOKOLL`, `FM_SCHLUESSELNACHWEIS`,
`FM_WINTERDIENSTNACHWEIS`, `FM_ZAEHLERABLESUNG`, `FM_NOTFALLBERICHT`.

## 7. Asset-Kategorien

`servicefahrzeug`, `werkzeugkoffer`, `leiter`, `schneefraese`,
`streuwagen`, `schluesselkasten`, `zaehlerkamera`, `funkgeraet`.

## 8. Tags-Seed

`#objektkontrolle`, `#winterdienst`, `#schluessel`, `#mangel`,
`#notfall`, `#brandschutz`, `#weitergeleitet`.

## 9. Akzeptanzkriterien

1. `database/data/branchprofiles/facility.php` enthält Inhalte aus §3-§8.
2. Pflichtfelder für Objektkontrollen, Mängel und Schlüssel greifen.
3. Onboarding-Wizard listet „Facility Management und Hausmeisterdienste".

# Branchenprofil Facility Management und Hausmeisterdienste

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).

## 1. Zweck

Referenzprofil für **Facility Management, Objektbetreuung und
Hausmeisterdienste**. Bildet Objektkontrollen, Kleinreparaturen,
Mängelmanagement, Schlüsselverwaltung, Winterdienst, Zählerstände,
Dienstleisterkoordination und wiederkehrende Betreiberpflichten ab.
Gebäude, Etagen, Räume und Außenbereiche dienen dabei als fachlicher Kontext
für Aufträge, Protokolle, Mängel und Betreiberpflichten.

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

## 4. Raum- und Objektanforderungen

- Gebäude, Etage, Raum oder Außenbereich am Auftrag erfassen.
- Raumbezogene Anforderungen: Brandschutz, Zugang, Schlüssel, Zähler,
  gesperrter Bereich, Betreiberpflicht oder besondere Kontrolle.
- Mängel können direkt an Raum, Anlage oder Bauteil hängen.
- Folgeaufträge übernehmen den Objekt-/Raumbezug, damit Historie und
  Auswertung erhalten bleiben.

## 5. Pflichtklassifikationen

- `entry_type=objektkontrolle`: Pflicht Objekt, Checkliste und Ergebnis.
- `entry_type=maengelmeldung`: Pflicht Foto, Ort, `defect_type` und
  Priorität.
- `entry_type=schluessel`: Pflicht Ausgabe/Rückgabe, Person und
  Schlüsselnummer.
- `entry_type=winterdienst`: Pflicht Zeitraum, Wetterlage und erledigte
  Flächen.

## 6. Prozedurvorlagen

`FM_OBJEKTKONTROLLE`, `FM_MAENGEL`, `FM_KLEINREPARATUR`,
`FM_WINTERDIENST`, `FM_SCHLUESSEL`, `FM_ZAEHLERSTAND`.

## 7. Protokollvorlagen

`FM_OBJEKTBERICHT`, `FM_MAENGELPROTOKOLL`, `FM_SCHLUESSELNACHWEIS`,
`FM_WINTERDIENSTNACHWEIS`, `FM_ZAEHLERABLESUNG`, `FM_NOTFALLBERICHT`.

## 8. Asset-Kategorien

`servicefahrzeug`, `werkzeugkoffer`, `leiter`, `schneefraese`,
`streuwagen`, `schluesselkasten`, `zaehlerkamera`, `funkgeraet`.

## 9. Tags-Seed

`#objektkontrolle`, `#winterdienst`, `#schluessel`, `#mangel`,
`#notfall`, `#brandschutz`, `#weitergeleitet`.

## 10. Akzeptanzkriterien

1. `database/data/branchprofiles/facility.php` enthält Inhalte aus §3-§9.
2. Pflichtfelder für Objektkontrollen, Mängel und Schlüssel greifen.
3. Onboarding-Wizard listet „Facility Management und Hausmeisterdienste".
4. Aufträge und Protokolle können Gebäude, Bereich oder Raum referenzieren.

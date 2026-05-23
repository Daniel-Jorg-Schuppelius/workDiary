# Branchenprofil SHK

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).
• Aufbauend auf:
[Branchenprofil Handwerk/Service](branchenprofil-handwerk.md) (MVP-034).

## 1. Zweck

Referenzprofil für **Sanitär-, Heizungs- und Klimatechnikbetriebe**.
Bildet Wartung, Reparatur, Druckprüfung, Dichtheitsprüfung,
Inbetriebnahme, Störung, Anlagenakte, Ersatzteile und Kundenabnahme ab.

## 2. Strukturparallele

`database/data/branchprofiles/shk.php` → Installer
`BranchProfileInstaller::install(org, "shk")`.

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                                             |
| --------------- | ----------------------------------------------------------------------------------------------------------------- |
| `entry_type`    | wartung, stoerung, reparatur, installation, inbetriebnahme, druckpruefung, dichtheitspruefung, abnahme, notdienst |
| `activity`      | entlueften, reinigen, tauschen, messen, abdichten, einstellen, spuelen, pruefen, dokumentieren                    |
| `defect_type`   | leckage, druckverlust, heizungsstoerung, verstopfung, korrosion, sensorDefekt, brennerStoerung, geraeusch         |
| `root_cause`    | verschleiss, verkalkung, frost, montagefehler, nutzung, materialfehler, fremdeinwirkung                           |
| `result`        | behoben, teilBehoben, dicht, nichtDicht, ersatzteilNoetig, kundenentscheidung, eskaliert                          |
| `product_group` | heizung, therme, boiler, pumpe, ventil, rohrleitung, heizkoerper, sanitaerObjekt, klimaGeraet, lueftung           |

## 4. Pflichtklassifikationen

- `entry_type=wartung`: Pflicht Anlagenbezug, Prüfpunkte und `result`.
- `entry_type=druckpruefung` / `dichtheitspruefung`: Pflicht Messwerte,
  Dauer, Medium und Ergebnis.
- `entry_type=stoerung`: Pflicht `defect_type`, `root_cause` und
  Behebungsmaßnahme.
- `entry_type=inbetriebnahme`: Pflicht Seriennummer, Hersteller,
  Einweisung und Kundenabnahme.

## 5. Prozedurvorlagen

`SHK_WARTUNG`, `SHK_STOERUNG`, `SHK_DRUCKPRUEFUNG`,
`SHK_DICHTHEIT`, `SHK_INBETRIEBNAHME`, `SHK_NOTDIENST`.

## 6. Protokollvorlagen

`SHK_WARTUNGSPROTOKOLL`, `SHK_DRUCKPROTOKOLL`,
`SHK_DICHTHEITSPROTOKOLL`, `SHK_ABNAHME`, `SHK_ANLAGENAKTE`,
`SHK_SERVICEBERICHT`.

## 7. Asset-Kategorien

`servicefahrzeug`, `pressmaschine`, `rohrkamera`, `lecksuchgeraet`,
`druckpruefpumpe`, `messgeraetAbgas`, `leiter`, `werkzeugkoffer`.

## 8. Tags-Seed

`#notdienst`, `#leckage`, `#wartung`, `#druckpruefung`,
`#ersatzteil`, `#kundenabnahme`, `#anlage`.

## 9. Akzeptanzkriterien

1. `database/data/branchprofiles/shk.php` enthält Inhalte aus §3-§8.
2. Pflichtfelder für Wartung, Prüfung, Störung und Inbetriebnahme greifen.
3. Onboarding-Wizard listet „SHK".

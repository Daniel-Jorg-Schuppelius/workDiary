# Branchenprofil Veranstaltungstechnik

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).

## 1. Zweck

Referenzprofil für **Veranstaltungstechnik, Bühnen-, Ton-, Licht- und
Medientechnik**. Bildet Planung, Aufbau, Strom, Rigging, Soundcheck,
Showbetreuung, Abbau, Safety-Checks, Übergaben und Schadensdokumentation ab.

## 2. Strukturparallele

`database/data/branchprofiles/veranstaltungstechnik.php` → Installer
`BranchProfileInstaller::install(org, "veranstaltungstechnik")`.

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                                          |
| --------------- | -------------------------------------------------------------------------------------------------------------- |
| `entry_type`    | angebot, vorbereitung, anlieferung, aufbau, safetyCheck, soundcheck, showbetreuung, abbau, ruecknahme, schaden |
| `activity`      | planen, laden, verkabeln, riggen, messen, programmieren, testen, betreuen, abbauen, inventarisieren            |
| `defect_type`   | equipmentFehlt, kabelDefekt, stromproblem, riggingMangel, transportschaden, kundenAenderung, zeitverzug        |
| `root_cause`    | planung, material, fremdgewerk, location, wetter, bedienfehler, verschleiss                                    |
| `result`        | erledigt, teilErledigt, freigegeben, nichtFreigegeben, nacharbeit, ersatzEquipment, eskaliert                  |
| `product_group` | ton, licht, video, buehne, rigging, strom, truss, mikrofon, lautsprecher, mischpult                            |

## 4. Pflichtklassifikationen

- `entry_type=aufbau`: Pflicht Equipmentliste, Aufbauort und Foto.
- `entry_type=safetyCheck`: Pflicht Checkliste, verantwortliche Person
  und Freigabe.
- `entry_type=soundcheck`: Pflicht Signaltest und Ergebnis.
- `entry_type=abbau` / `ruecknahme`: Pflicht Equipment-Soll/Ist-Abgleich.
- `entry_type=schaden`: Pflicht Fotos, `defect_type` und Maßnahme.

## 5. Prozedurvorlagen

`VT_EVENT_PLANUNG`, `VT_AUFBAU`, `VT_STROM_CHECK`,
`VT_RIGGING_CHECK`, `VT_SOUNDCHECK`, `VT_SHOWBETREUUNG`,
`VT_ABBAU`, `VT_SCHADEN`.

## 6. Protokollvorlagen

`VT_EVENTBRIEFING`, `VT_EQUIPMENTLISTE`, `VT_SAFETY_CHECK`,
`VT_SOUNDCHECK`, `VT_ABNAHME`, `VT_RUECKNAHME`, `VT_SCHADEN`.

## 7. Asset-Kategorien

`lautsprecher`, `mischpult`, `mikrofon`, `scheinwerfer`, `dimmer`,
`truss`, `stativ`, `stromverteiler`, `kabelcase`, `videoprojektor`.

## 8. Tags-Seed

`#ton`, `#licht`, `#rigging`, `#strom`, `#safety`, `#show`,
`#equipment`, `#schaden`.

## 9. Akzeptanzkriterien

1. `database/data/branchprofiles/veranstaltungstechnik.php` enthält Inhalte aus §3-§8.
2. Pflichtfelder für Aufbau, Safety-Check, Abbau und Schaden greifen.
3. Onboarding-Wizard listet „Veranstaltungstechnik".

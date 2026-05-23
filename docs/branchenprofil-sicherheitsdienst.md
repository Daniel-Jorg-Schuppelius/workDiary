# Branchenprofil Sicherheitsdienst

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).

## 1. Zweck

Referenzprofil für **Sicherheitsdienste, Revierdienste und Objektschutz**.
Bildet Wachbuch, Revierfahrt, Kontrollpunkte, Schlüssel, Alarmverfolgung,
Vorfallmeldungen, Übergaben, Zutrittskontrollen und Eskalationen ab.

## 2. Strukturparallele

`database/data/branchprofiles/sicherheitsdienst.php` → Installer
`BranchProfileInstaller::install(org, "sicherheitsdienst")`.

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                                      |
| --------------- | ---------------------------------------------------------------------------------------------------------- |
| `entry_type`    | wachbuch, revierfahrt, kontrollgang, alarm, zutritt, schluessel, vorfall, uebergabe, sonderdienst          |
| `activity`      | kontrollieren, anmelden, absichern, melden, eskortieren, sperren, aufschliessen, dokumentieren, eskalieren |
| `defect_type`   | einbruchVerdacht, vandalismus, tueroffen, alarmAusgeloest, schluesselFehlt, personenkonflikt, brandschutz  |
| `root_cause`    | unbekannt, technischerFehler, fremdeinwirkung, bedienfehler, wetter, organisatorisch                       |
| `result`        | erledigt, lageGeklaert, polizeiInformiert, kundeInformiert, offen, eskaliert, fehlalarm                    |
| `product_group` | objekt, kontrollpunkt, tor, tuer, alarmanlage, kamera, schluessel, ausweis, fahrzeug                       |

## 4. Pflichtklassifikationen

- `entry_type=revierfahrt`: Pflicht Tour, Kontrollpunkte und Zeiten.
- `entry_type=alarm`: Pflicht Objekt, Alarmzeit, Maßnahme und Ergebnis.
- `entry_type=vorfall`: Pflicht Beschreibung, Foto falls möglich,
  Eskalationsstatus und Ergebnis.
- `entry_type=schluessel`: Pflicht Ausgabe/Rückgabe, Person und Nummer.
- `entry_type=uebergabe`: Pflicht Schicht, offene Punkte und Unterschrift.

## 5. Prozedurvorlagen

`SD_WACHBUCH`, `SD_REVIERFAHRT`, `SD_KONTROLLGANG`,
`SD_ALARMVERFOLGUNG`, `SD_VORFALL`, `SD_SCHLUESSEL`,
`SD_UEBERGABE`.

## 6. Protokollvorlagen

`SD_WACHBUCH_EINTRAG`, `SD_REVIERBERICHT`, `SD_ALARMBERICHT`,
`SD_VORFALLMELDUNG`, `SD_SCHLUESSELNACHWEIS`, `SD_UEBERGABE`.

## 7. Asset-Kategorien

`dienstfahrzeug`, `funkgeraet`, `taschenlampe`, `bodycam`,
`schluesselkasten`, `scanner`, `diensthandy`, `warnweste`.

## 8. Tags-Seed

`#alarm`, `#revier`, `#wachbuch`, `#vorfall`, `#schluessel`,
`#polizei`, `#kunde-informiert`, `#fehlalarm`.

## 9. Akzeptanzkriterien

1. `database/data/branchprofiles/sicherheitsdienst.php` enthält Inhalte aus §3-§8.
2. Pflichtfelder für Revierfahrt, Alarm, Vorfall und Schlüssel greifen.
3. Onboarding-Wizard listet „Sicherheitsdienst".

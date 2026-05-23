# Branchenprofil Kfz- und Fuhrparkservice

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).

## 1. Zweck

Referenzprofil für **Kfz-Werkstätten, mobile Fahrzeugservices und
Fuhrparkservice**. Bildet Fahrzeugakte, Wartung, Reparatur,
Schadensdokumentation, Reifen, HU/AU-Vorbereitung, Übergaben,
Ersatzfahrzeuge und Nachkalkulation ab.

## 2. Strukturparallele

`database/data/branchprofiles/kfz-fuhrparkservice.php` → Installer
`BranchProfileInstaller::install(org, "kfz-fuhrparkservice")`.

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                                      |
| --------------- | ---------------------------------------------------------------------------------------------------------- |
| `entry_type`    | annahme, wartung, reparatur, diagnose, reifenwechsel, schaden, huAu, uebergabe, rueckgabe, nachkalkulation |
| `activity`      | pruefen, messen, tauschen, reinigen, kalibrieren, probefahrt, dokumentieren, bestellen, uebergeben         |
| `defect_type`   | motorschaden, bremsen, elektrik, karosserie, reifen, scheibe, lack, fahrwerk, verschleiss                  |
| `root_cause`    | verschleiss, unfall, bedienfehler, wartungUeberfaellig, material, fremdeinwirkung                          |
| `result`        | erledigt, teilErledigt, fahrbereit, nichtFahrbereit, teileNoetig, kundeInformiert, eskaliert               |
| `product_group` | pkw, transporter, lkw, anhaenger, reifen, bremse, motor, batterie, karosserie, innenraum                   |

## 4. Pflichtklassifikationen

- `entry_type=annahme`: Pflicht Fahrzeug, Kilometerstand, Zustand und
  Kundenauftrag.
- `entry_type=wartung`: Pflicht Wartungsplan, Teile/Material und Ergebnis.
- `entry_type=schaden`: Pflicht Fotos, Schadenbereich, Ursache und Maßnahme.
- `entry_type=reifenwechsel`: Pflicht Reifensatz, Profiltiefe und Lagerort.
- `entry_type=uebergabe`: Pflicht Kilometerstand, Zustand und Unterschrift.

## 5. Prozedurvorlagen

`KFZ_ANNAHME`, `KFZ_WARTUNG`, `KFZ_DIAGNOSE`, `KFZ_REPARATUR`,
`KFZ_REIFEN`, `KFZ_SCHADEN`, `KFZ_UEBERGABE`.

## 6. Protokollvorlagen

`KFZ_ANNAHMEPROTOKOLL`, `KFZ_SERVICEBERICHT`, `KFZ_DIAGNOSEBERICHT`,
`KFZ_SCHADENPROTOKOLL`, `KFZ_REIFENEINLAGERUNG`, `KFZ_UEBERGABE`.

## 7. Asset-Kategorien

`hebebuehne`, `diagnosegeraet`, `drehmomentschluessel`,
`reifenmontiermaschine`, `wuchtmaschine`, `servicefahrzeug`,
`batterietester`, `ersatzfahrzeug`.

## 8. Tags-Seed

`#fahrzeugakte`, `#wartung`, `#reifen`, `#schaden`, `#hu-au`,
`#ersatzteil`, `#probefahrt`, `#uebergabe`.

## 9. Akzeptanzkriterien

1. `database/data/branchprofiles/kfz-fuhrparkservice.php` enthält Inhalte aus §3-§8.
2. Pflichtfelder für Annahme, Wartung, Schaden, Reifen und Übergabe greifen.
3. Onboarding-Wizard listet „Kfz- und Fuhrparkservice".

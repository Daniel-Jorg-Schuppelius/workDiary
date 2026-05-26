# Branchenprofil Gebäudereinigung

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).

## 1. Zweck

Referenzprofil für **Gebäudereinigung und infrastrukturelle Services**.
Bildet Unterhaltsreinigung, Grundreinigung, Glasreinigung, Sonderreinigung,
Qualitätskontrollen, Materialverbrauch, Objektpläne und Reklamationen ab.
Räume, Raumgruppen und Sonderflächen können besondere Anforderungen tragen,
z. B. Sonderreinigung, Hygienehinweis, Zugangsbeschränkung oder
Foto-/Abnahmepflicht.

## 2. Strukturparallele

`database/data/branchprofiles/gebaeudereinigung.php` → Installer
`BranchProfileInstaller::install(org, "gebaeudereinigung")`.

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                                           |
| --------------- | --------------------------------------------------------------------------------------------------------------- |
| `entry_type`    | unterhaltsreinigung, grundreinigung, glasreinigung, sonderreinigung, qualitaetskontrolle, reklamation, begehung |
| `activity`      | reinigen, desinfizieren, saugen, wischen, polieren, auffuellen, entsorgen, kontrollieren, dokumentieren         |
| `defect_type`   | nichtGereinigt, materialFehlt, zugangFehlt, qualitaetsmangel, schaden, hygienemangel, kundenbeschwerde          |
| `root_cause`    | personalEngpass, zugang, material, fremdverschmutzung, planungsfehler, nacharbeitNoetig                         |
| `result`        | erledigt, teilErledigt, nacharbeit, nichtMoeglich, kundeInformiert, eskaliert                                   |
| `product_group` | buero, sanitaer, treppenhaus, glas, boden, kueche, industrie, medizinisch, aussenbereich                        |

## 4. Raum- und Reinigungsanforderungen

- Raum, Raumgruppe oder Fläche am Reinigungsauftrag erfassen.
- Raumbezogene Anforderungen: Reinigungsfrequenz, Sonderreinigung,
  Hygienestufe, Materialbedarf, Zugangsbeschränkung und Abnahmepflicht.
- Qualitätskontrollen und Reklamationen bleiben am Raum oder Bereich
  auswertbar.
- Besondere Reinigung kann als einmalige Anforderung oder als wiederkehrende
  Pflicht am Raum hinterlegt werden.

## 5. Pflichtklassifikationen

- `entry_type=unterhaltsreinigung`: Pflicht Objekt, Bereich und
  Checklistenstatus.
- `entry_type=qualitaetskontrolle`: Pflicht Bewertungsstufe, Foto bei
  Mangel und Ergebnis.
- `entry_type=reklamation`: Pflicht `defect_type`, Maßnahme und
  Rückmeldung an Kunden.
- `entry_type=sonderreinigung`: Pflicht Leistungsbeschreibung,
  Sicherheits-/Materialhinweis und Abnahme.

## 6. Prozedurvorlagen

`GR_UNTERHALT`, `GR_GRUNDREINIGUNG`, `GR_GLAS`,
`GR_SONDERREINIGUNG`, `GR_QS_KONTROLLE`, `GR_REKLAMATION`.

## 7. Protokollvorlagen

`GR_REINIGUNGSNACHWEIS`, `GR_OBJEKTPLAN`, `GR_QS_PROTOKOLL`,
`GR_REKLAMATIONSBERICHT`, `GR_MATERIALVERBRAUCH`, `GR_ABNAHME`.

## 8. Asset-Kategorien

`reinigungswagen`, `einscheibenmaschine`, `nasssauger`,
`glasreinigungsset`, `leiter`, `dosieranlage`, `psaReinigung`.

## 9. Tags-Seed

`#unterhalt`, `#sonderreinigung`, `#glas`, `#sanitaer`,
`#nacharbeit`, `#reklamation`, `#hygiene`.

## 10. Akzeptanzkriterien

1. `database/data/branchprofiles/gebaeudereinigung.php` enthält Inhalte aus §3-§9.
2. Pflichtfelder für Reinigung, QS und Reklamation greifen.
3. Onboarding-Wizard listet „Gebäudereinigung".
4. Räume und Sonderflächen können besondere Reinigungsanforderungen tragen.

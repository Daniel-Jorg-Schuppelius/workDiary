# Branchenprofil Garten- und Landschaftsbau

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).

## 1. Zweck

Referenzprofil für **Garten- und Landschaftsbau sowie Objektpflege**.
Bildet Pflegegänge, Pflanzung, Erdarbeiten, Pflaster, Baum-/Heckenpflege,
Winterdienst, Maschinen, Wetter, Saisonarbeiten und Abnahmen ab.

## 2. Strukturparallele

`database/data/branchprofiles/galabau.php` → Installer
`BranchProfileInstaller::install(org, "galabau")`.

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                                  |
| --------------- | ------------------------------------------------------------------------------------------------------ |
| `entry_type`    | pflegegang, neuanlage, pflanzung, erdarbeit, pflaster, baumpflege, bewaesserung, winterdienst, abnahme |
| `activity`      | maehen, schneiden, pflanzen, giessen, duengen, roden, baggern, pflastern, reinigen, dokumentieren      |
| `defect_type`   | ausfallPflanze, trockenheit, schaedling, setzung, frostschaden, geraeteschaden, zugangFehlt            |
| `root_cause`    | wetter, pflegefehler, material, standort, fremdeinwirkung, kundenaenderung, lieferverzug               |
| `result`        | erledigt, teilErledigt, nacharbeit, saisonbedingtOffen, kundeInformiert, abgenommen                    |
| `product_group` | rasen, hecke, baum, beet, pflaster, zaun, bewaesserung, teich, aussenanlage, winterdienst              |

## 4. Pflichtklassifikationen

- `entry_type=pflegegang`: Pflicht Objekt, erledigte Flächen und Ergebnis.
- `entry_type=pflanzung`: Pflicht Pflanzliste, Standort und Foto.
- `entry_type=baumpflege`: Pflicht Sicherheits-/Absperrvermerk.
- `entry_type=winterdienst`: Pflicht Wetterlage, Zeitraum und Fläche.
- `entry_type=abnahme`: Pflicht Restpunkte und Kundenabnahme.

## 5. Prozedurvorlagen

`GL_PFLEGEGANG`, `GL_PFLANZUNG`, `GL_NEUANLAGE`,
`GL_PFLASTER`, `GL_BAUMPFLEGE`, `GL_WINTERDIENST`, `GL_ABNAHME`.

## 6. Protokollvorlagen

`GL_PFLEGENACHWEIS`, `GL_PFLANZPROTOKOLL`, `GL_BAUTAGESBERICHT`,
`GL_WINTERDIENSTNACHWEIS`, `GL_MAENGEL`, `GL_ABNAHME`.

## 7. Asset-Kategorien

`transporter`, `anhaenger`, `rasenmaeher`, `freischneider`,
`heckenschere`, `minibagger`, `ruettelplatte`, `kettensaege`,
`streuwagen`.

## 8. Tags-Seed

`#pflege`, `#saison`, `#pflanzung`, `#winterdienst`, `#wetter`,
`#abnahme`, `#nacharbeit`.

## 9. Akzeptanzkriterien

1. `database/data/branchprofiles/galabau.php` enthält Inhalte aus §3-§8.
2. Pflichtfelder für Pflege, Pflanzung, Winterdienst und Abnahme greifen.
3. Onboarding-Wizard listet „Garten- und Landschaftsbau".

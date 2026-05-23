# Branchenprofil Bau, Ausbau und Trockenbau

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).

## 1. Zweck

Referenzprofil für **Bau-, Ausbau- und Trockenbaubetriebe**. Bildet
Baustellenfortschritt, Aufmaß, Nachträge, Mängel, Teilabnahmen, Wetter,
Materialverbrauch, Bautagesberichte und Restarbeiten ab.

## 2. Strukturparallele

`database/data/branchprofiles/bau-ausbau.php` → Installer
`BranchProfileInstaller::install(org, "bau-ausbau")`.

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                                 |
| --------------- | ----------------------------------------------------------------------------------------------------- |
| `entry_type`    | bautagesbericht, aufmass, montage, mangel, nachtrag, teilabnahme, material, behinderung, restarbeit   |
| `activity`      | messen, montieren, spachteln, schleifen, dokumentieren, pruefen, reinigen, koordinieren, nacharbeiten |
| `defect_type`   | massabweichung, materialfehler, bauseitigerMangel, feuchtigkeit, beschaedigung, planabweichung        |
| `root_cause`    | vorleistungFehlt, wetter, planung, material, fremdgewerk, bauherrAenderung, lieferverzug              |
| `result`        | erledigt, teilErledigt, offen, nachtragNoetig, behindert, abgenommen, eskaliert                       |
| `product_group` | wand, decke, boden, tuer, fenster, trockenbau, daemmung, malerarbeiten, fliesen, fassade              |

## 4. Pflichtklassifikationen

- `entry_type=bautagesbericht`: Pflicht Baustelle, Wetter,
  Personalstunden und Fortschritt.
- `entry_type=aufmass`: Pflicht Maße, Bereich, Foto/Skizze und Ergebnis.
- `entry_type=mangel`: Pflicht Foto, Ort, `defect_type` und Maßnahme.
- `entry_type=nachtrag`: Pflicht Anlass, Beschreibung, Aufwandsschätzung
  und Kundenfreigabe.
- `entry_type=teilabnahme`: Pflicht Restpunkte und Unterschrift.

## 5. Prozedurvorlagen

`BAU_TAGESBERICHT`, `BAU_AUFMASS`, `BAU_MANGEL`,
`BAU_NACHTRAG`, `BAU_TEILABNAHME`, `BAU_RESTARBEIT`.

## 6. Protokollvorlagen

`BAU_TAGESBERICHT`, `BAU_AUFMASSPROTOKOLL`, `BAU_MAENGELLISTE`,
`BAU_NACHTRAGSPROTOKOLL`, `BAU_ABNAHME`, `BAU_MATERIALVERBRAUCH`.

## 7. Asset-Kategorien

`transporter`, `laserentfernungsmesser`, `nivellierlaser`,
`akkuschrauber`, `geruest`, `baustrahler`, `staubsauger`, `leiter`.

## 8. Tags-Seed

`#baustelle`, `#aufmass`, `#nachtrag`, `#mangel`, `#restpunkte`,
`#wetter`, `#fremdgewerk`, `#abnahme`.

## 9. Akzeptanzkriterien

1. `database/data/branchprofiles/bau-ausbau.php` enthält Inhalte aus §3-§8.
2. Pflichtfelder für Tagesbericht, Aufmaß, Mangel und Nachtrag greifen.
3. Onboarding-Wizard listet „Bau, Ausbau und Trockenbau".

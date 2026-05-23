# Branchenprofil Maschinenbau und Anlagenwartung

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).

## 1. Zweck

Referenzprofil für **Maschinenbau, Anlagenservice und industrielle
Instandhaltung**. Bildet Wartungsintervalle, Störungen, Inspektionen,
Messwerte, Ersatzteile, Stillstände, Prüfungen und Anlagenhistorie ab.

## 2. Strukturparallele

`database/data/branchprofiles/anlagenwartung.php` → Installer
`BranchProfileInstaller::install(org, "anlagenwartung")`.

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                                   |
| --------------- | ------------------------------------------------------------------------------------------------------- |
| `entry_type`    | wartung, stoerung, inspektion, reparatur, inbetriebnahme, kalibrierung, stillstand, ersatzteil, abnahme |
| `activity`      | pruefen, messen, schmieren, tauschen, justieren, reinigen, kalibrieren, testen, dokumentieren           |
| `defect_type`   | lagerschaden, leckage, sensorfehler, ueberhitzung, verschleiss, vibrationsproblem, steuerungsfehler     |
| `root_cause`    | verschleiss, bedienfehler, material, wartungUeberfaellig, fremdteil, prozessabweichung                  |
| `result`        | behoben, teilBehoben, produktiv, stillstand, ersatzteilNoetig, beobachtung, eskaliert                   |
| `product_group` | maschine, anlage, pumpe, motor, sensor, steuerung, hydraulik, pneumatik, foerdertechnik, roboter        |

## 4. Pflichtklassifikationen

- `entry_type=wartung`: Pflicht Anlage, Wartungsplan, Prüfpunkte und Ergebnis.
- `entry_type=stoerung`: Pflicht Stillstandszeit, `defect_type`,
  Ursache und Maßnahme.
- `entry_type=kalibrierung`: Pflicht Messmittel, Soll/Ist-Werte und
  Freigabe.
- `entry_type=ersatzteil`: Pflicht Teil, Menge, Serien-/Chargenbezug
  falls vorhanden.

## 5. Prozedurvorlagen

`AW_WARTUNG`, `AW_STOERUNG`, `AW_INSPEKTION`,
`AW_KALIBRIERUNG`, `AW_ERSATZTEIL`, `AW_INBETRIEBNAHME`.

## 6. Protokollvorlagen

`AW_WARTUNGSPROTOKOLL`, `AW_STOERBERICHT`, `AW_MESSPROTOKOLL`,
`AW_KALIBRIERNACHWEIS`, `AW_ERSATZTEILNACHWEIS`, `AW_ABNAHME`.

## 7. Asset-Kategorien

`servicefahrzeug`, `messgeraet`, `drehmomentschluessel`,
`kalibriergeraet`, `diagnoseLaptop`, `werkzeugwagen`, `endoskopkamera`.

## 8. Tags-Seed

`#wartung`, `#stillstand`, `#ersatzteil`, `#messwerte`,
`#kalibrierung`, `#anlagenakte`, `#notfall`.

## 9. Akzeptanzkriterien

1. `database/data/branchprofiles/anlagenwartung.php` enthält Inhalte aus §3-§8.
2. Pflichtfelder für Wartung, Störung, Kalibrierung und Ersatzteile greifen.
3. Onboarding-Wizard listet „Maschinenbau und Anlagenwartung".

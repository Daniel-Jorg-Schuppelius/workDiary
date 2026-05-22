# Branchenprofil Ambulante Pflege

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).
• Aufbauend auf:
[Branchenprofil IT-Service](branchenprofil-it.md) (MVP-033, gleiche
Mechanik),
[Branchenprofil Handwerk/Service](branchenprofil-handwerk.md) (MVP-034).

## 1. Zweck

Referenzprofil für **ambulante Pflegedienste** (Leistungen nach
SGB V — Behandlungspflege und SGB XI — Grundpflege/Betreuung sowie
hauswirtschaftliche Versorgung). Bildet Tourenplanung, Einsatz-
dokumentation, Pflegevisiten, Medikamentengabe, Wundversorgung,
Sturz- und Vorfallserfassung sowie qualitätsrelevante Pflichten ab.
Es ersetzt **keine** zertifizierte Pflegesoftware mit
Leistungsabrechnung, sondern liefert eine revisionssichere
Tätigkeitsdokumentation als Ergänzung oder Vorstufe.

## 2. Strukturparallele

Aufbau identisch zu MVP-033 / MVP-034:
`database/data/branchprofiles/ambulante-pflege.php` → Installer
`BranchProfileInstaller::install(org, "ambulante-pflege")`.
Idempotent, auditiert (`branch_profile.installed`).

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                                                                                                                       |
| --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `entry_type`    | tourEinsatz, grundpflege, behandlungspflege, betreuung, hauswirtschaft, beratungsbesuch, pflegevisite, vorfall, qualitaetspruefung                                                          |
| `activity`      | koerperpflege, mobilisation, lagerung, nahrungsgabe, medikamentenstellen, medikamentengabe, injektion, wundversorgung, blutzucker, vitalwerte, dokumentation, anleitung, hauswirtschaftlich |
| `defect_type`   | hautlaesion, dekubitusVerdacht, sturz, schmerz, infektVerdacht, medikamentenproblem, mangelernaehrung, dehydration, kommunikation                                                           |
| `root_cause`    | grunderkrankung, neueMedikation, immobilitaet, sturzAnamnese, externerArzt, angehoerige, geraeteausfall                                                                                     |
| `result`        | erledigt, teilErledigt, abgelehntVomKlienten, nichtAngetroffen, abgebrochen, eskaliertArzt, eskaliertLeitung                                                                                |
| `product_group` | wundauflage, kompressionsstrumpf, inkontinenzmaterial, hilfsmittel, medikament, pen, sondennahrung, sauerstoff                                                                              |
| `leistungsart`  | sgbVBehandlungspflege, sgbXIGrundpflege, sgbXIBetreuung, sgbXIHauswirtschaft, sgbXVerhinderungspflege, selbstzahler                                                                         |
| `pflegegrad`    | pg1, pg2, pg3, pg4, pg5                                                                                                                                                                     |
| `kommunikation` | uebergabe, telefonHausarzt, telefonAngehoerige, faxApotheke, klinikbericht, mdkAnfrage                                                                                                      |

## 4. Pflichtklassifikationen

- `entry_type=tourEinsatz`: Pflicht `leistungsart` `onCreate`,
  Pflicht Klientenbezug, Pflicht `result` `beforeComplete`.
- `entry_type=behandlungspflege`: Pflicht `activity` (mind. eine
  aus medikamentengabe, injektion, wundversorgung, blutzucker,
  vitalwerte) `onCreate`; Pflicht ärztliche Verordnung als
  Referenz; Pflicht `result` `beforeComplete`.
- `entry_type=grundpflege`: Pflicht `pflegegrad` (aus Stammdaten
  übernommen) `onCreate`; Pflicht `result` `beforeComplete`.
- `entry_type=vorfall`: Pflicht `defect_type` `onCreate`; Pflicht
  Fließtext-Beschreibung, Pflicht `result` (regelmäßig
  `eskaliertArzt` oder `eskaliertLeitung`); Pflicht
  Vier-Augen-Gegenzeichnung durch PDL/stellv. PDL `beforeComplete`.
- `entry_type=pflegevisite`: Pflicht `result` `beforeComplete`,
  Pflicht Unterschrift PDL.
- `entry_type=beratungsbesuch`: Pflicht Termin im Quartalsraster
  (SGB XI § 37 Abs. 3), Pflicht Ergebnisbewertung 1–3.
- Pflicht Vier-Augen bei `activity=medikamentengabe` für BtM und
  bei `activity=injektion` (außer s.c. Insulin per Standing Order).

Alle als `severity = hard`, außer `defect_type=schmerz` und
`kommunikation` = `soft`.

## 5. Prozedurvorlagen

Mit Code-Präfix `AP_`:

| Code                      | Anwendung                                                              |
| ------------------------- | ---------------------------------------------------------------------- |
| `AP_TOUR_EINSATZ`         | Standard-Einsatz auf der Tour (An-/Abfahrt, Tätigkeiten, Unterschrift) |
| `AP_MEDIKAMENTENGABE`     | Medikamentengabe nach 5-R-Regel mit Quittierung                        |
| `AP_BTM_GABE`             | Betäubungsmittelgabe mit Vier-Augen und BtM-Buch                       |
| `AP_WUNDVERSORGUNG`       | Wundversorgung mit Fotodokumentation und Wundbericht                   |
| `AP_VITALWERTE`           | Vitalwerte (RR, Puls, SpO2, Temperatur, BZ) mit Grenzwert-Alarm        |
| `AP_STURZ_PROTOKOLL`      | Sturzereignis (Hergang, Verletzung, Arzt, Maßnahmen)                   |
| `AP_DEKUBITUS_RISIKO`     | Risikoeinschätzung Dekubitus (Braden) wiederkehrend                    |
| `AP_PFLEGEVISITE`         | Pflegevisite durch PDL mit Bewertung und Maßnahmen                     |
| `AP_BERATUNGSBESUCH_37_3` | Beratungsbesuch nach § 37 Abs. 3 SGB XI                                |
| `AP_NEUAUFNAHME_KLIENT`   | Neuaufnahme inkl. Stammdaten, Verträge, Risikoassessments              |
| `AP_UEBERGABE_SCHICHT`    | Schichtübergabe mit Kurzbericht je Klient                              |
| `AP_HYGIENE_BEGEHUNG`     | Hygienebegehung / interne Auditcheckliste                              |

Jede Vorlage:

- Klientenbezug ist Pflicht.
- Bei dokumentationspflichtigen Tätigkeiten: Unterschriftsfeld
  (digital, Pflegekraft) und ggf. Klientenzeichen.
- Für `AP_WUNDVERSORGUNG`: Fotopflicht (vorher/nachher), Lokalisation
  am Körperdiagramm.
- Für `AP_MEDIKAMENTENGABE` / `AP_BTM_GABE`: Pflicht Referenz auf
  Medikationsplan (Wirkstoff, Dosis, Uhrzeit) und Quittierung pro
  Gabe.
- Für `AP_STURZ_PROTOKOLL`: Pflicht Eskalation (Arzt, Angehörige,
  Leitung) und Vier-Augen.

## 6. Protokollvorlagen

Mit Code-Präfix `AP_`:

| Code                      | Zweck                                                     |
| ------------------------- | --------------------------------------------------------- |
| `AP_LEISTUNGSNACHWEIS`    | Leistungsnachweis pro Klient (Tag/Monat) mit Unterschrift |
| `AP_PFLEGEBERICHT`        | Pflegebericht als chronologische Verlaufsdoku             |
| `AP_WUNDBERICHT`          | Wundbericht mit Foto, Größe, Stadium, Maßnahmen           |
| `AP_STURZBERICHT`         | Sturzbericht                                              |
| `AP_BTM_BUCH_EINTRAG`     | BtM-Buch-Eintrag                                          |
| `AP_PFLEGEVISITE`         | Pflegevisitenprotokoll                                    |
| `AP_BERATUNG_37_3`        | Beratungsbesuchsprotokoll § 37 Abs. 3 SGB XI              |
| `AP_AERZTLICHE_ANORDNUNG` | Übernahme einer ärztlichen Anordnung mit Quittung         |
| `AP_VORFALL_MELDUNG`      | Vorfallmeldung (intern und ggf. an Behörde / MDK)         |

## 7. Asset-Kategorien (Vorbereitung MVP-035)

`dienstwagen`, `dienstkleidung`, `dienstschluessel`,
`mobilesGeraet`, `tabletPflege`, `pflegekoffer`, `bzMessgeraet`,
`blutdruckmessgeraet`, `pulsoximeter`, `infusionspumpe`,
`btmSchrank`, `notfallrucksack`, `hygieneSet`.

## 8. Tags-Seed

`#btm`, `#wunde`, `#sturzgefaehrdet`, `#palliativ`,
`#dekubitusrisiko`, `#diabetes`, `#mrsa`, `#hausnotruf`,
`#allein-lebend`, `#schluesselverwaltung`,
`#beratungsbesuch-faellig`, `#mdk-pruefung`.

## 9. Kompatibilität mit anderen Profilen

- Konfliktfrei zu `it`, `handwerk` und `steuerberater`: alle
  `entry_type`-Codes sind domainspezifisch.
- Sinnvoll kombinierbar mit `handwerk` für Hausmeisterdienste an
  Pflege-WGs oder ähnliche Mischbetriebe.

## 10. Datenschutz, Schweigepflicht und Aufbewahrung

- Klientenbezug ist Pflicht; Sichtbarkeit folgt mandantenscoped
  Sichtbarkeit (Customer-/Klientenbezug analog `customer_id`).
- Schweigepflicht nach § 203 StGB und Datenschutz nach DSGVO:
  Gesundheitsdaten gelten als besondere Kategorie (Art. 9 DSGVO).
  Profil aktiviert je Tätigkeitsart die strengere Sichtbarkeits-
  ebene (`visibility=clientCare`).
- Aufbewahrungsfristen Pflegedokumentation: in der Regel 10 Jahre
  ab Ende der Pflegebeziehung; BtM-Buch 3 Jahre nach letzter
  Eintragung. Profil markiert Protokolle mit
  `retention_years = 10` bzw. `3`.
- Fotodokumentation (Wunden): Pflichtprüfung, ob Einwilligung
  (`consent.photo`) vorliegt; sonst Schritt blockiert.

## 11. Qualität und Pflichten

- Expertenstandards (DNQP) werden über Prozedurvorlagen abgebildet
  (Dekubitus, Sturz, Schmerz, Ernährung, Kontinenz, Wunde).
- MDK/MD-Qualitätsprüfung: `AP_HYGIENE_BEGEHUNG`,
  `AP_PFLEGEVISITE` und `AP_BERATUNG_37_3` liefern
  prüfungsrelevante Nachweise.
- Pflichten nach § 113 SGB XI (interne Qualitätsmanagement-
  Systeme) werden über wiederkehrende Termine
  (`STB_FRISTEN_REVIEW`-Äquivalent: `AP_QM_REVIEW`) unterstützt.

## 12. Akzeptanzkriterien

1. `database/data/branchprofiles/ambulante-pflege.php` enthält
   Inhalte aus §3–§8 vollständig.
2. Installer idempotent + auditiert.
3. Tests: install + reinstall ohne Duplikate; gemeinsame
   Installation mit `handwerk`-Profil führt zu erwarteter
   Vereinigung ohne Konflikte.
4. Pflichtfelder, Vier-Augen-Regel (BtM, Vorfall) und
   Fotopflicht (Wunde) gemäß §4 / §5 werden im UI / API geprüft.
5. Beratungsbesuch-Quartalsraster wird als wiederkehrender Termin
   pro Klient angelegt.
6. Onboarding-Wizard (MVP-048) listet das Profil mit Beschreibung
   „Ambulanter Pflegedienst (SGB V / SGB XI)".

## 13. Out-of-scope

- Leistungsabrechnung mit Pflegekasse / Krankenkasse
  (DTA § 105 SGB XI / § 302 SGB V): nicht enthalten; das Profil
  liefert die Tätigkeits- und Pflichtdokumentation, nicht den
  Abrechnungsdatensatz.
- Tourenplanung / Routenoptimierung: außerhalb dieses Profils;
  Profil dokumentiert die Touren, plant sie aber nicht.
- Verordnungsmanagement mit Arztpraxis-Anbindung
  (eAU/eRezept): außerhalb.
- Spezialprofile (Intensivpflege außerklinisch, Kinderkrankenpflege,
  Palliativ-Spezialteam) — Folge.

## 14. Folge

- Spezialisierungen (Intensivpflege, Pädiatrie, Palliativ).
- Integration in MVP-048 Onboarding-Wizard.
- Anbindung Medikationsplan und ärztliche Verordnungen als
  eigenes Feature.

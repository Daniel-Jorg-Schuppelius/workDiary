# Branchenprofil Spedition und Transportlogistik

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).
• Aufbauend auf:
[Branchenprofil Handwerk/Service](branchenprofil-handwerk.md) (MVP-034,
gleiche Mechanik).

## 1. Zweck

Referenzprofil für **Speditionen, Transportdienstleister und logistiknahe
Dienste**. Bildet Auftragserfassung, Disposition, Abholung, Umschlag,
Transport, Zustellung, Rückladung, Schadenserfassung, Wartezeiten,
Paletten-/Lademittelkonten und Nachkalkulation ab. Fokus:
nachvollziehbare Transportereignisse, Dienstmittel- und Fahrzeugbezug,
Zustellnachweise und belastbare Abrechnungsvorbereitung.

## 2. Strukturparallele

Aufbau identisch zu MVP-033 / MVP-034:
`database/data/branchprofiles/spedition.php` → Installer
`BranchProfileInstaller::install(org, "spedition")`.
Idempotent, auditiert (`branch_profile.installed`).

## 3. Klassifikationen (Auszug)

| Domain              | Codes                                                                                                                                             |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| `entry_type`        | transportauftrag, disposition, abholung, beladung, umschlag, transport, zustellung, rueckladung, wartezeit, schaden, reklamation, nachkalkulation |
| `activity`          | planen, avisieren, laden, sichern, fahren, umladen, entladen, scannen, dokumentieren, palettenTauschen, reinigen, kontrollieren                   |
| `defect_type`       | lieferverzug, transportschaden, fehlmenge, falscheWare, temperaturAbweichend, annahmeVerweigert, dokumentFehlt, lademittelDifferenz               |
| `root_cause`        | verkehr, verspaeteteBereitstellung, rampenstau, falscheAdresse, fahrzeugDefekt, personalEngpass, wetter, kundenfehler, subunternehmer             |
| `result`            | zugestellt, teilZugestellt, nichtZugestellt, retourniert, umdisponiert, eskaliert, schadenGemeldet, abgerechnet                                   |
| `product_group`     | stueckgut, teilpartie, komplettladung, express, kuehlgut, gefahrgut, sperrgut, palettenware, retouren, lagerware                                  |
| `dienstmittel_type` | sattelzug, wechselbruecke, sprinter, kuehlfahrzeug, anhaenger, auflieger, stapler, hubwagen, scanner, spanngurt                                   |
| `lademittel_type`   | euroPalette, einwegPalette, gitterbox, rollcontainer, behaelter, wechselbruecke                                                                   |

## 4. Pflichtklassifikationen

- `entry_type=transportauftrag`: Pflicht Auftraggeber, Abholort,
  Zustellort, Zeitfenster, `product_group` und geplantes Dienstmittel
  `onCreate`.
- `entry_type=disposition`: Pflicht Fahrzeug/Fahrer oder
  Subunternehmer, Tourdatum und Dispositionsstatus `beforeComplete`.
- `entry_type=abholung`: Pflicht Abholzeit, Ladeeinheiten,
  Lademittelstatus und `result` `beforeComplete`.
- `entry_type=beladung`: Pflicht Ladungssicherung-Vermerk,
  Foto bei Sonderladung und verantwortliche Person `beforeComplete`.
- `entry_type=transport`: Pflicht Fahrzeugbezug, Start-/Endzeit und
  Kilometerstand oder Tourreferenz `beforeComplete`.
- `entry_type=zustellung`: Pflicht Zustellzeit, Empfänger,
  Zustellnachweis und `result` `beforeComplete`.
- `entry_type=schaden`: Pflicht `defect_type` `onCreate`; Pflicht
  Fotos, Schadensbeschreibung, Verantwortungsstatus und `result`
  `beforeComplete`.
- `entry_type=wartezeit`: Pflicht Standort, Beginn, Ende und Ursache
  `beforeComplete`.

Alle als `severity = hard`, außer `defect_type` bei
`reklamation` = `soft`, solange die Ursache noch ungeklärt ist.

## 5. Prozedurvorlagen

Mit Code-Präfix `SP_`:

| Code                   | Anwendung                                                      |
| ---------------------- | -------------------------------------------------------------- |
| `SP_TRANSPORTAUFTRAG`  | Auftragserfassung mit Lade-/Entladestellen und Zeitfenstern    |
| `SP_DISPOSITION_TOUR`  | Tourdisposition mit Fahrer, Fahrzeug, Subunternehmer           |
| `SP_ABHOLUNG`          | Abholung mit Ladeeinheiten, Lademitteln und Dokumentenprüfung  |
| `SP_LADUNGSSICHERUNG`  | Beladung und Sicherung mit Foto-/Checkpflicht                  |
| `SP_TRANSPORTEREIGNIS` | Ereignis auf Tour: Stau, Umleitung, Pause, Zwischenstopp       |
| `SP_ZUSTELLUNG_POD`    | Zustellung mit Proof of Delivery und Empfängerbestätigung      |
| `SP_PALETTENTAUSCH`    | Lademitteltausch und Differenzdokumentation                    |
| `SP_SCHADENSMELDUNG`   | Schaden mit Fotos, Beteiligten, Sofortmaßnahme und Eskalation  |
| `SP_WARTEZEIT_DOKU`    | Wartezeit an Rampe, Kunde oder Umschlagpunkt                   |
| `SP_NACHKALKULATION`   | Nachkalkulation mit Zeit, Strecke, Maut, Wartezeit, Zuschlägen |

Jede Vorlage:

- Auftraggeber-, Sendungs- oder Tourbezug ist Pflicht.
- Fahrzeug, Fahrer oder Subunternehmer werden aus der Disposition
  übernommen.
- Bei Abholung/Zustellung: Zeitstempel und Nachweisfeld.
- Bei Schaden: Foto- und Maßnahmenpflicht.
- Bei Lademitteln: Soll/Ist- und Tauschstatus.

## 6. Protokollvorlagen

Mit Code-Präfix `SP_`:

| Code                   | Zweck                                                     |
| ---------------------- | --------------------------------------------------------- |
| `SP_TRANSPORTAUFTRAG`  | Transportauftrag mit Lade-/Entladedaten und Sendungsdaten |
| `SP_ABHOLBELEG`        | Abholnachweis mit Ladeeinheiten und Lademitteln           |
| `SP_LADUNGSSICHERUNG`  | Ladungssicherungsprotokoll                                |
| `SP_ZUSTELLNACHWEIS`   | Proof of Delivery mit Empfänger, Zeit, Signatur oder Foto |
| `SP_PALETTENSCHEIN`    | Paletten-/Lademitteltausch                                |
| `SP_SCHADENSPROTOKOLL` | Schaden, Fehlmenge oder Annahmeverweigerung               |
| `SP_WARTEZEITNACHWEIS` | Wartezeitnachweis für Abrechnung oder Eskalation          |
| `SP_TOURBERICHT`       | Tages-/Tourbericht mit Ereignissen, Zeiten und Kilometern |

## 7. Asset-Kategorien (Vorbereitung MVP-035)

`sattelzug`, `zugmaschine`, `auflieger`, `wechselbruecke`,
`anhaenger`, `sprinter`, `kuehlfahrzeug`, `stapler`, `hubwagen`,
`scanner`, `telematikgeraet`, `spanngurt`, `antirutschmatte`,
`sperrbalken`, `ladebordwand`, `tankkarte`, `mautbox`.

## 8. Tags-Seed

`#express`, `#kuehlgut`, `#gefahrgut`, `#adr`, `#rampe`,
`#wartezeit`, `#palettentausch`, `#schaden`, `#retoure`,
`#subunternehmer`, `#nachtfahrt`, `#fixtermin`, `#messenah`.

## 9. Kompatibilität mit anderen Profilen

- Konfliktfrei zu `it`, `handwerk`, `steuerberater`,
  `ambulante-pflege` und `partyservice`: alle `entry_type`-Codes sind
  domainspezifisch.
- Sinnvoll kombinierbar mit `partyservice`, wenn Cateringbetriebe eigene
  Liefer- und Rücknahmetouren detailliert dokumentieren.
- Sinnvoll kombinierbar mit `handwerk` für Betriebe mit Serviceflotte,
  Geräteauslieferung oder Baustellenlogistik.

## 10. Transportpflichten, Sicherheit und Aufbewahrung

- Ladungssicherung wird über `SP_LADUNGSSICHERUNG` dokumentiert;
  Pflichtfotos können für Sonderladung oder beschädigte Ware aktiviert
  werden.
- Kühltransporte nutzen Temperaturfelder analog zu
  `temperaturAbweichend` und optional wiederkehrende Messpunkte.
- Gefahrgut/ADR wird über Tags und Pflichtqualifikationen vorbereitet;
  das Profil ersetzt keine Gefahrgutsoftware.
- Zustellnachweise, Schadensprotokolle und Wartezeitnachweise werden mit
  mandantenkonfigurierbarer Aufbewahrungsfrist markiert.

## 11. Akzeptanzkriterien

1. `database/data/branchprofiles/spedition.php` enthält Inhalte aus
   §3–§8 vollständig.
2. Installer idempotent + auditiert.
3. Tests: install + reinstall ohne Duplikate; gemeinsame Installation mit
   `partyservice` oder `handwerk` führt zu erwarteter Vereinigung ohne
   Konflikte.
4. Pflichtfelder für Auftrag, Disposition, Abholung, Beladung, Transport,
   Zustellung, Schaden und Wartezeit gemäß §4 werden im UI / API geprüft.
5. Onboarding-Wizard (MVP-048) listet das Profil mit Beschreibung
   „Spedition und Transportlogistik".

## 12. Out-of-scope

- Vollständiges Transport Management System mit Frachtenbörse,
  Routenoptimierung oder Telematik-Liveortung.
- Zoll-, Export- und Gefahrgut-Spezialsoftware.
- Automatische Maut-, Tankkarten- oder Tachodatenintegration.
- Lagerverwaltung mit Stellplatzlogik und Bestandsführung.

## 13. Folge

- Spezialisierungen für Kühltransport, Gefahrgut, Stückgut,
  Baustellenlogistik, Möbeltransport und Kurier-/Expressdienste.
- Integration in MVP-048 Onboarding-Wizard.
- Demo-Daten für typische Touren mit Abholung, Zustellung,
  Palettentausch, Wartezeit und Schadensmeldung.

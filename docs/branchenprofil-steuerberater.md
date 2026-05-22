# Branchenprofil Steuerberatung

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).
• Aufbauend auf:
[Branchenprofil IT-Service](branchenprofil-it.md) (MVP-033, gleiche
Mechanik),
[Branchenprofil Handwerk/Service](branchenprofil-handwerk.md) (MVP-034).

## 1. Zweck

Referenzprofil für **Steuerberatungs- und Buchhaltungskanzleien**
(StBerG, BOStB). Bildet die mandantenbezogene Leistungserbringung
(Finanzbuchführung, Lohn, Jahresabschluss, Steuererklärungen,
Beratung, Fristenüberwachung) ab, ohne eine vollständige
Kanzleisoftware zu ersetzen. Fokus: revisionssichere Tätigkeits-
und Fristenprotokollierung sowie standardisierte Mandanten-
prozesse.

## 2. Strukturparallele

Aufbau identisch zu MVP-033 / MVP-034:
`database/data/branchprofiles/steuerberater.php` → Installer
`BranchProfileInstaller::install(org, "steuerberater")`.
Idempotent, auditiert (`branch_profile.installed`).

## 3. Klassifikationen (Auszug)

| Domain          | Codes                                                                                                                               |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| `entry_type`    | fibu, lohn, abschluss, steuererklaerung, voranmeldung, beratung, fristensache, kommunikation, pruefung                              |
| `activity`      | belegerfassung, kontieren, abstimmen, erstellen, pruefen, einreichen, beraten, korrespondenz, archivieren                           |
| `defect_type`   | belegFehlt, belegUnleserlich, kontierungUnklar, mandantNichtErreichbar, fristKritisch, datenInkonsistent                            |
| `root_cause`    | mandantSpaet, datenLuecke, behoerdenanfrage, gesetzAenderung, internerFehler, systemausfall                                         |
| `result`        | eingereicht, freigegebenDurchMandant, ruecklaufBehoerde, vertagt, eskaliert, archiviert                                             |
| `product_group` | einkommensteuer, koerperschaftsteuer, gewerbesteuer, umsatzsteuer, lohnsteuer, sozialversicherung, jahresabschluss, eur, fibu, lohn |
| `kommunikation` | telefon, email, postEingang, postAusgang, mandantenportal, datev, elster, behoerde                                                  |

## 4. Pflichtklassifikationen

- `entry_type=steuererklaerung`: Pflicht `product_group` `onCreate`
  (welche Steuerart); Pflicht `result` `beforeComplete`.
- `entry_type=voranmeldung`: Pflicht `product_group` (umsatzsteuer,
  lohnsteuer) `onCreate`; Pflicht `result=eingereicht` oder
  `vertagt` `beforeComplete`.
- `entry_type=abschluss`: Pflicht `product_group` `onCreate`;
  Pflicht `result` und Freigabe-Vermerk `beforeComplete`.
- `entry_type=fibu` / `lohn`: Pflicht Buchungsperiode (Monat/Jahr)
  im Eintragsfeld `period`; Pflicht `result` `beforeComplete`.
- `entry_type=fristensache`: Pflicht Fristdatum, Pflicht
  `root_cause` falls Frist verschoben.
- `entry_type=kommunikation`: Pflicht `kommunikation` `onCreate`,
  Pflicht Mandantenbezug.

Alle als `severity = hard`, außer `defect_type` bei `beratung` =
`soft`.

## 5. Prozedurvorlagen

Mit Code-Präfix `STB_`:

| Code                    | Anwendung                                                 |
| ----------------------- | --------------------------------------------------------- |
| `STB_FIBU_MONAT`        | Monats-FiBu (Belege, Kontierung, Abstimmung, USt-Voran)   |
| `STB_LOHN_MONAT`        | Lohnabrechnung mit Vier-Augen und DEÜV-Meldungen          |
| `STB_USTVA`             | Umsatzsteuer-Voranmeldung inkl. ELSTER-Übermittlung       |
| `STB_LSTAN`             | Lohnsteuer-Anmeldung inkl. ELSTER-Übermittlung            |
| `STB_JAHRESABSCHLUSS`   | Jahresabschluss mit Mandanten-Freigabe                    |
| `STB_STEUERERKLAERUNG`  | Steuererklärung (ESt / KSt / GewSt) mit Reviewschritt     |
| `STB_FRISTEN_REVIEW`    | Wöchentliche Fristenüberwachung                           |
| `STB_MANDANTENANNAHME`  | Onboarding eines neuen Mandanten (Vollmachten, DSGVO)     |
| `STB_BETRIEBSPRUEFUNG`  | Prüfungsbegleitung (Anforderungsliste, Schriftverkehr)    |
| `STB_GELDWAESCHE_CHECK` | Geldwäschegesetz-Plausibilität bei Mandantenannahme/Re-ID |

Jede Vorlage:

- Pflicht-Vier-Augen-Schritt bei abgehenden Meldungen
  (Voranmeldungen, Erklärungen, Abschluss, Lohnmeldungen),
- Dokumentation der Datenquellen (DATEV, Mandantenportal, Bank,
  Beleg),
- abschließender Archivierungs-/Ablageschritt.

## 6. Protokollvorlagen

Mit Code-Präfix `STB_`:

| Code                       | Zweck                                                    |
| -------------------------- | -------------------------------------------------------- |
| `STB_BERATUNGSPROTOKOLL`   | Beratungsgespräch mit Mandant (Inhalt, Empfehlungen)     |
| `STB_TELEFONNOTIZ`         | Telefonnotiz mit Pflichtfeldern (Mandant, Anlass, Folge) |
| `STB_FRISTENVERLAENGERUNG` | Fristverlängerungsantrag und -bewilligung                |
| `STB_FREIGABE_MANDANT`     | Freigabevermerk Mandant für Abschluss / Erklärung        |
| `STB_PRUEFUNGSVERMERK`     | Vermerk zur Betriebsprüfung                              |
| `STB_GELDWAESCHE_VERMERK`  | Geldwäsche-Risikoeinschätzung und Re-Identifizierung     |

## 7. Asset-Kategorien (Vorbereitung MVP-035)

`workstation`, `notebook`, `monitor`, `phone`, `printer`,
`scanner`, `signaturePad`, `cardReader`, `softwareLicense`,
`datevAccount`, `safe`, `archiveShelf`.

## 8. Tags-Seed

`#frist-kritisch`, `#mandantenrueckfrage`, `#datev-import`,
`#elster`, `#vier-augen`, `#geldwaesche`, `#betriebsprüfung`,
`#dauerauftrag`, `#einmalig`, `#quartalsende`.

## 9. Kompatibilität mit anderen Profilen

- Konfliktfrei zu `it` und `handwerk`: alle `entry_type`-Codes sind
  domainspezifisch und kollidieren nicht.
- Sinnvoll kombinierbar mit `it`, wenn die Kanzlei IT-nahe
  Dienstleistungen für Mandanten erbringt (z. B. DATEV-Hosting).

## 10. Datenschutz und Berufsrecht

- Mandantenbezug ist Pflicht für alle `entry_type`-Werte.
- Berufliche Verschwiegenheit (§ 57 StBerG, § 203 StGB):
  Tätigkeitsprotokolle dürfen nur mandantenbezogen sichtbar sein;
  Sichtbarkeit folgt der bestehenden mandantenscoped Sichtbarkeit
  (siehe `customer_id`-Scope, Customer-Portal-Guard).
- Aufbewahrungsfristen: Profil markiert relevante Protokolle mit
  `retention_years = 10` (Buchführung), `retention_years = 6`
  (Korrespondenz) — Abgleich mit MVP-Archiv (siehe Archivpolicy).
- Geldwäschegesetz: Risikoanalyse über `STB_GELDWAESCHE_CHECK`
  und Vermerk `STB_GELDWAESCHE_VERMERK`; Re-Identifizierung
  jährlich als wiederkehrende Frist.

## 11. Akzeptanzkriterien

1. `database/data/branchprofiles/steuerberater.php` enthält Inhalte
   aus §3–§8 vollständig.
2. Installer idempotent + auditiert.
3. Tests: install + reinstall ohne Duplikate; gemeinsame
   Installation mit `it`-Profil führt zu erwarteter Vereinigung
   ohne Konflikte.
4. Pflichtfelder gemäß §4 werden im UI / API geprüft.
5. Onboarding-Wizard (MVP-048) listet das Profil mit Beschreibung
   „Steuerberatungs- und Buchhaltungskanzlei".

## 12. Out-of-scope

- Vollständige Kanzleisoftware (Fristenverwaltung, Rechnungswesen
  intern, DATEV-Schnittstelle): wird nicht ersetzt, sondern
  ergänzt.
- ELSTER-/DATEV-Integration: außerhalb dieses Profils; das Profil
  liefert nur die Klassifikation und Prozedur, nicht den
  Versandkanal.
- Spezialprofile (Wirtschaftsprüfer, Rechtsanwalt) — Folge.

## 13. Folge

- Spezialisierungen (z. B. WP-Profil, Lohnbüro-Profil).
- Integration in MVP-048 Onboarding-Wizard.

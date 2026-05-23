# Branchenprofil Partyservice und Catering

Status: Geplant • Quelle:
[Feature 042 — Gewerke / Branchenprofile](features/042-gewerke-branchenprofile.md).
• Aufbauend auf:
[Branchenprofil Handwerk/Service](branchenprofil-handwerk.md) (MVP-034,
gleiche Mechanik).

## 1. Zweck

Referenzprofil für **Partyservice-, Catering- und Eventverpflegungsbetriebe**.
Bildet Anfrage, Angebot, Menü- und Buffetplanung, Produktion, Verpackung,
Lieferung, Aufbau, Ausgabe, Rücknahme, Reinigung, Leergut und Nachkalkulation
ab. Fokus: verlässliche Einsatzdokumentation, Lebensmittelhygiene,
Equipment-Nachweis und klare Abnahme beim Kunden oder Veranstalter.

## 2. Strukturparallele

Aufbau identisch zu MVP-033 / MVP-034:
`database/data/branchprofiles/partyservice.php` → Installer
`BranchProfileInstaller::install(org, "partyservice")`.
Idempotent, auditiert (`branch_profile.installed`).

## 3. Klassifikationen (Auszug)

| Domain              | Codes                                                                                                                                        |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `entry_type`        | cateringAnfrage, angebot, eventVorbereitung, produktion, lieferung, aufbau, betreuungVorOrt, abbau, ruecknahme, reklamation, nachkalkulation |
| `activity`          | menuPlanen, einkaufen, vorbereiten, kochen, kuehlen, verpacken, transportieren, aufbauen, ausgeben, nachlegen, reinigen, inventur            |
| `defect_type`       | mengeAbweichend, temperaturAbweichend, lieferverzug, equipmentFehlt, bruch, qualitaetsmangel, allergenUnklar, kundenAenderung                |
| `root_cause`        | fehlplanung, lieferant, verkehr, personalEngpass, falscheBestellung, kundenNachtrag, wetter, equipmentDefekt                                 |
| `result`            | erledigt, teilErledigt, kundeAbgenommen, nachlieferung, gutschrift, eskaliert, storniert                                                     |
| `product_group`     | buffet, fingerfood, grill, getraenke, dessert, kaffeeKuchen, geschirr, besteck, waermebehaelter, kuehlbox, zelt, mobiliar                    |
| `event_type`        | hochzeit, geburtstag, firmenevent, trauerfeier, vereinsfest, messe, schulung, privateFeier                                                   |
| `dienstmittel_type` | kuehlfahrzeug, lieferfahrzeug, thermobox, chafingDish, kaffeeautomat, zapfanlage, spuelkiste, transportbox                                   |

## 4. Pflichtklassifikationen

- `entry_type=cateringAnfrage`: Pflicht `event_type`, Personenanzahl,
  Veranstaltungsdatum und Lieferort `onCreate`.
- `entry_type=angebot`: Pflicht `product_group` und Personenanzahl
  `onCreate`; Pflicht Angebotsstatus `beforeComplete`.
- `entry_type=produktion`: Pflicht `product_group`, geplante Menge,
  verantwortliche Person und Temperatur-/Kühlvermerk `beforeComplete`.
- `entry_type=lieferung`: Pflicht Lieferfenster, Lieferadresse,
  `dienstmittel_type` und `result` `beforeComplete`.
- `entry_type=aufbau`: Pflicht Foto nach Aufbau, Equipment-Liste und
  Kundenkontakt `beforeComplete`.
- `entry_type=ruecknahme`: Pflicht Leergut-/Equipment-Abgleich,
  Bruch-/Fehlmengenvermerk und `result` `beforeComplete`.
- `entry_type=reklamation`: Pflicht `defect_type` `onCreate`;
  Pflicht `root_cause`, Maßnahme und `result` `beforeComplete`.

Alle als `severity = hard`, außer `defect_type` bei
`eventVorbereitung` = `soft`.

## 5. Prozedurvorlagen

Mit Code-Präfix `PS_`:

| Code                   | Anwendung                                                         |
| ---------------------- | ----------------------------------------------------------------- |
| `PS_ANFRAGE_AUFNAHME`  | Erstaufnahme mit Eventdaten, Personenanzahl und Allergenen        |
| `PS_ANGEBOT_ERSTELLEN` | Angebot mit Menü, Equipment, Lieferkosten und Zahlungsbedingungen |
| `PS_EVENT_PLANUNG`     | Ablaufplanung bis Veranstaltungstag                               |
| `PS_PRODUKTION`        | Küchenproduktion mit Mengen, Chargen und Temperaturkontrolle      |
| `PS_LIEFERUNG`         | Kommissionierung, Verladung, Kühlkette, Zustellung                |
| `PS_AUFBAU_BUFFET`     | Buffet-/Equipment-Aufbau mit Fotopflicht und Kundenabnahme        |
| `PS_BETREUUNG_EVENT`   | Betreuung vor Ort inkl. Nachlegen, Rückfragen und Sonderwünsche   |
| `PS_RUECKNAHME`        | Abbau, Rücknahme, Leergut, Bruch und Reinigung                    |
| `PS_REKLAMATION`       | Reklamation mit Ursache, Sofortmaßnahme und Kulanzentscheidung    |

Jede Vorlage:

- Kunden-/Veranstaltungsbezug ist Pflicht.
- Personenanzahl, Zeitfenster und Lieferort werden aus dem Auftrag übernommen.
- Bei Lebensmitteln: Allergen- und Temperaturvermerk.
- Bei Lieferung/Aufbau: Foto- oder Unterschriftsnachweis.
- Bei Rücknahme: Equipment-Soll/Ist-Abgleich.

## 6. Protokollvorlagen

Mit Code-Präfix `PS_`:

| Code                     | Zweck                                                         |
| ------------------------ | ------------------------------------------------------------- |
| `PS_EVENTBRIEFING`       | Eventbriefing mit Ablauf, Ansprechpartnern und Besonderheiten |
| `PS_PRODUKTIONSPLAN`     | Produktionsplan mit Mengen, Zeiten und Verantwortlichen       |
| `PS_TEMPERATURPROTOKOLL` | Kühl-/Warmhalte- und Transporttemperaturen                    |
| `PS_LIEFERSCHEIN`        | Lieferung mit Positionen, Equipment und Übergabe              |
| `PS_AUFBAU_ABNAHME`      | Aufbauabnahme mit Fotos und Kundenunterschrift                |
| `PS_RUECKNAHMEPROTOKOLL` | Rücknahme, Bruch, Fehlmengen und Leergut                      |
| `PS_REKLAMATIONSBERICHT` | Reklamation mit Ursache, Maßnahme und Entscheidung            |

## 7. Asset-Kategorien (Vorbereitung MVP-035)

`kuehlfahrzeug`, `lieferfahrzeug`, `thermobox`, `kuehlbox`,
`chafingDish`, `waermeplatte`, `kaffeeautomat`, `zapfanlage`,
`geschirrSet`, `besteckSet`, `glasSet`, `servierplatte`,
`transportwagen`, `spuelkiste`, `zelt`, `stehtisch`, `buffettisch`.

## 8. Tags-Seed

`#hochzeit`, `#firmenevent`, `#allergene`, `#vegetarisch`, `#vegan`,
`#glutenfrei`, `#kuehlkette`, `#lieferung`, `#aufbau-vor-ort`,
`#equipment-rueckgabe`, `#reklamation`, `#express`.

## 9. Kompatibilität mit anderen Profilen

- Konfliktfrei zu `it`, `handwerk`, `steuerberater` und
  `ambulante-pflege`: alle `entry_type`-Codes sind domainspezifisch.
- Sinnvoll kombinierbar mit `handwerk` für Betriebe, die zusätzlich
  Eventtechnik, Zelte, Mobiliar oder Hausmeisterleistungen anbieten.
- Sinnvoll kombinierbar mit `fuhrpark`- oder Logistikprofilen, sobald
  diese als Spezialprofile existieren.

## 10. Hygiene, Allergene und Aufbewahrung

- Lebensmittelhygiene nach HACCP-Grundsätzen: Temperatur- und
  Kühlkettennachweise werden über `PS_TEMPERATURPROTOKOLL` und
  Pflichtfelder in Produktion/Lieferung abgebildet.
- Allergene und besondere Ernährungsformen sind bei Anfrage, Angebot und
  Produktion sichtbar zu führen.
- Rückverfolgbarkeit: Produktionschargen oder Lieferantenreferenzen können
  pro Position dokumentiert werden, ohne eine Warenwirtschaft zu ersetzen.
- Aufbewahrung: Lieferscheine, Abnahmen, Temperaturprotokolle und
  Reklamationen werden mit mandantenkonfigurierbarer Frist markiert.

## 11. Akzeptanzkriterien

1. `database/data/branchprofiles/partyservice.php` enthält Inhalte aus
   §3–§8 vollständig.
2. Installer idempotent + auditiert.
3. Tests: install + reinstall ohne Duplikate; gemeinsame Installation mit
   `handwerk`-Profil führt zu erwarteter Vereinigung ohne Konflikte.
4. Pflichtfelder für Anfrage, Produktion, Lieferung, Aufbau, Rücknahme und
   Reklamation gemäß §4 werden im UI / API geprüft.
5. Onboarding-Wizard (MVP-048) listet das Profil mit Beschreibung
   „Partyservice und Catering".

## 12. Out-of-scope

- Vollständige Warenwirtschaft, Rezepturverwaltung oder Kassensystem.
- Rechtssichere Lebensmittelkennzeichnung als eigenständiges Modul.
- Routenoptimierung und Tourenplanung; das Profil dokumentiert Lieferung
  und Rücknahme, plant sie aber nicht.
- Spezialprofile für Großküchen, Kantinen, Foodtrucks oder Eventtechnik.

## 13. Folge

- Spezialisierungen für Eventcatering, Schul-/Kita-Verpflegung,
  Foodtruck-Betrieb und Getränkeservice.
- Integration in MVP-048 Onboarding-Wizard.
- Demo-Daten für typische Veranstaltungen mit Angebot, Lieferung,
  Aufbauabnahme und Rücknahme.

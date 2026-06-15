# Internationalisierung und Rechtsräume

## Status

In Progress — MVP-Kern umgesetzt (Stand 2026-06-14): mandantenbezogener
Feiertags-Rechtsraum (Land/Bundesland je Organisation, regionale Feiertage),
länderspezifische Spesen-/Pauschalsätze (PerDiemRate mit `country` +
`region_label`), Mehrsprachigkeit (de/en/fr/it/es) und org-Locale/Datums-/
Zahlenformate sind produktiv. Offen: länderspezifische Steuer-/Währungslogik
in der Fakturierung und nicht hart kodierte rechtliche Aufbewahrungsfristen
(siehe „Umsetzung").

## Umsetzung

- **Feiertags-Rechtsraum (org-konfigurierbar):** Bis dato bestimmte der
  `HolidayService` die Feiertage **fix** über `config('app.holidays.provider')`
  — eine Organisation konnte ihr Bundesland nicht wählen. Jetzt liest der
  Service den Yasumi-Provider mandantenbewusst über
  `Setting::get('holidays.provider')` (neue `config/holidays.php`); die Region
  wird in den Org-Einstellungen (*Region & Feiertage*) gewählt
  (`App\Support\HolidayRegions`: alle 16 DE-Bundesländer + bundesweit + AT).
  Der Cache ist jetzt **pro Rechtsraum** geschlüsselt. **Konsistenz:**
  Feiertagszuschläge (`SurchargeCalculator`) und die Dienstplan-Compliance
  lesen ausschließlich über den `HolidayService` und nutzen damit automatisch
  dieselbe Quelle — die Feiertagsberechnung wurde **nicht dupliziert**.
- **Spesen-/Pauschalsätze pro Land/Region:** Bereits vor diesem Schritt
  vorhanden — `PerDiemRate` trägt `country` (ISO-2) + `region_label`
  (Stadt/Region), der `PerDiemRateLookup` wählt den Satz nach Reiseziel mit
  Fallback Region → Landesstandard; Admin-Pflege unter *Pauschalensätze*,
  Foreign-Rate-Seeder + Tests existieren. Hier war keine strukturelle Lücke.
- **Bewusst offen (Folgearbeit):** länderspezifische **Steuer-/Währungslogik**
  in der Fakturierung (heute Org-Default-Steuersatz/-Währung, keine je-Land-
  Steuerregeln) sowie nicht hart kodierte **rechtliche Aufbewahrungsfristen**
  je Rechtsraum — beides größerer Umfang und über den MVP hinaus.

## Ziel

WorkDiary soll perspektivisch verschiedene Sprachen, Länder und Rechtsräume
unterstützen: Feiertage, Arbeitszeitregeln, Spesen, Währungen, Steuern,
Aufbewahrungsfristen, Datumsformate und Dokumentensprache.

## Warum

Auch wenn der Fokus zunächst DACH ist, beeinflussen Rechtsräume Datenmodell,
Konfiguration und Auswertungen. Diese Unterschiede sollten nicht hart codiert
werden.

## MVP

- Mandantenbezogene Sprache und Region.
- Feiertage und Arbeitszeitregeln konfigurierbar.
- Spesen- und Pauschalensätze pro Land/Region.
- Währungen und Steuerangaben in Rechnungs-/Auswertungsgrundlagen.
- Übersetzbare Vorlagen und Protokolle.

## Akzeptanzkriterien

- DACH-Regeln können getrennt konfiguriert werden.
- Berichte und PDFs nutzen Sprache/Format des Mandanten.
- Rechtliche Fristen sind nicht global hart codiert.
- Neue Regionen können ohne Kernumbau ergänzt werden.

## Abhängigkeiten

- Mandantenfähigkeit und Betriebsmodelle
- Lohn, Zuschläge und DATEV/Lexware
- Spesen und Per-Diem
- Vorlagen- und Formularsystem

## GitHub Issues

- TBD

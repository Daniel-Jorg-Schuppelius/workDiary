<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoBlueprintProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Demo;

use App\Enums\Asset\AssetClass;
use App\Enums\Demo\DemoIndustry;
use App\Enums\Protocol\ProtocolItemResult;

/**
 * Branchenspezifische Demo-Inhalte (Kunden, Projekte, Hauptauftrag, Material,
 * Asset) je {@see DemoIndustry} — generisch, keine echten Firmen/Personen.
 * Aus dem DemoSeederService extrahiert (Refactoring Welle 2, B6b).
 *
 * Schlüsselsatz ist für ALLE Branchen identisch (DemoIndustriesTest prüft
 * das): `procedure_code` benennt die Prozedurvorlage des Branchenprofils für
 * den Demo-Durchlauf; Prozeduren/Tags/Kataloge kommen aus dem Profil selbst.
 */
class DemoBlueprintProvider {
    /**
     * @return array<string, mixed>
     */
    public function blueprint(DemoIndustry $industry): array {
        return match ($industry) {
            DemoIndustry::ItService => [
                'customers' => [
                    ['name' => 'ACME GmbH', 'city' => 'Berlin'],
                    ['name' => 'Beispiel-Apotheke', 'city' => 'Köln'],
                    ['name' => 'Mustermann KG', 'city' => 'München'],
                ],
                'projects' => [
                    0 => ['Server-Migration ACME', 'Helpdesk ACME'],
                    1 => ['Wartung Apotheken-System'],
                    2 => ['Netzwerk-Refresh Mustermann', 'Outlook-Migration Mustermann'],
                ],
                'asset' => [
                    'name' => 'Demo-Server ACME-SRV-01',
                    'manufacturer' => 'Beispiel Systems',
                    'model' => 'RX-2000',
                    'class' => AssetClass::Device,
                    'location' => 'Serverraum Berlin',
                ],
                'materials' => [
                    ['sku' => 'IT-SW-24', 'name' => 'Switch 24-Port Gigabit', 'unit' => 'Stk', 'price' => '189.0000'],
                    ['sku' => 'IT-PATCH-2M', 'name' => 'Patchkabel Cat6 2m', 'unit' => 'Stk', 'price' => '4.5000'],
                    ['sku' => 'IT-USV-1500', 'name' => 'USV 1500VA', 'unit' => 'Stk', 'price' => '349.0000'],
                ],
                'main_case' => [
                    'title' => 'Server-Migration ACME — Beispielauftrag',
                    'content' => 'Migration des Datei- und Druckerservers nach ACME-Vorgabe. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Server-Migration',
                    'open_issue_title' => 'Backup-Verifikation steht aus',
                    'open_issue_desc' => 'Wiederherstellungstest mit Demo-Daten innerhalb einer Woche.',
                    'protocol_title' => 'Abnahme Server-Migration ACME',
                    'protocol_items' => [
                        ['label' => 'Dienste laufen nach Migration', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Backup erfolgreich eingerichtet', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Wiederherstellungstest durchgeführt', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Abstimmung Wartungsfenster mit ACME',
                    'comm_body' => 'Telefonat mit Kunde zur Bestätigung des Migrationsfensters und Abnahme.',
                ],
                'background_title' => 'Demo-Wartung',
                'procedure_code' => 'IT_NETWORK_CHANGE',
            ],
            DemoIndustry::Elektro => [
                'customers' => [
                    ['name' => 'Wohnbau Muster eG', 'city' => 'Hamburg'],
                    ['name' => 'Bäckerei Beispiel', 'city' => 'Dortmund'],
                    ['name' => 'Hausverwaltung Musterstadt', 'city' => 'Leipzig'],
                ],
                'projects' => [
                    0 => ['Wallbox-Installation Tiefgarage', 'E-Check Wohnanlage'],
                    1 => ['Verteilererneuerung Backstube'],
                    2 => ['PV-Anschluss Mehrfamilienhaus', 'Störungsdienst Musterstadt'],
                ],
                'asset' => [
                    'name' => 'Unterverteilung UV-Tiefgarage',
                    'manufacturer' => 'Beispiel Elektrotechnik',
                    'model' => 'UV-63A',
                    'class' => AssetClass::Installation,
                    'location' => 'Tiefgarage Hamburg',
                ],
                'materials' => [
                    ['sku' => 'EL-WB-11', 'name' => 'Wallbox 11 kW', 'unit' => 'Stk', 'price' => '649.0000'],
                    ['sku' => 'EL-NYM-3X', 'name' => 'NYM-J 3x2,5 mm²', 'unit' => 'm', 'price' => '1.2000'],
                    ['sku' => 'EL-LS-B16', 'name' => 'Leitungsschutzschalter B16', 'unit' => 'Stk', 'price' => '6.9000'],
                ],
                'main_case' => [
                    'title' => 'Wallbox-Installation Tiefgarage — Beispielauftrag',
                    'content' => 'Installation einer 11-kW-Wallbox inkl. Leitungsverlegung und Messung. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Wallbox-Installation',
                    'open_issue_title' => 'Schlussmessung Isolationswiderstand offen',
                    'open_issue_desc' => 'Messprotokoll nach VDE 0100-600 vor Inbetriebnahme vervollständigen.',
                    'protocol_title' => 'Abnahme Wallbox-Installation',
                    'protocol_items' => [
                        ['label' => 'Schutzleiterprüfung bestanden', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Isolationsmessung dokumentiert', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Funktionsprüfung FI durchgeführt', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Terminabstimmung Inbetriebnahme Wallbox',
                    'comm_body' => 'Telefonat mit Hausverwaltung zur Freigabe und Schlüsselübergabe Tiefgarage.',
                ],
                'background_title' => 'Demo-Elektroeinsatz',
                'procedure_code' => 'EL_SICHERHEITSCHECK',
            ],
            DemoIndustry::Facility => [
                'customers' => [
                    ['name' => 'Büropark Muster KG', 'city' => 'Frankfurt'],
                    ['name' => 'Einkaufszentrum Beispiel', 'city' => 'Stuttgart'],
                    ['name' => 'Wohnanlage Musterquartier', 'city' => 'Hannover'],
                ],
                'projects' => [
                    0 => ['Objektbetreuung Büropark', 'Winterdienst Büropark'],
                    1 => ['Haustechnik Einkaufszentrum'],
                    2 => ['Grünpflege Musterquartier', 'Hausmeisterdienst Musterquartier'],
                ],
                'asset' => [
                    'name' => 'Lüftungsanlage RLT-01',
                    'manufacturer' => 'Beispiel Klimatechnik',
                    'model' => 'RLT-4000',
                    'class' => AssetClass::Machine,
                    'location' => 'Technikzentrale Frankfurt',
                ],
                'materials' => [
                    ['sku' => 'FM-FILTER-G4', 'name' => 'Luftfilter G4', 'unit' => 'Stk', 'price' => '12.5000'],
                    ['sku' => 'FM-STREU-25', 'name' => 'Auftausalz 25 kg', 'unit' => 'Sack', 'price' => '8.9000'],
                    ['sku' => 'FM-LEUCHT-LED', 'name' => 'LED-Leuchtmittel E27', 'unit' => 'Stk', 'price' => '3.4000'],
                ],
                'main_case' => [
                    'title' => 'Wartungsrunde Büropark — Beispielauftrag',
                    'content' => 'Monatliche Objektkontrolle inkl. Filterwechsel Lüftung und Kleinreparaturen. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Objektbetreuung',
                    'open_issue_title' => 'Defekte Beleuchtung Tiefgarage Ebene 2',
                    'open_issue_desc' => 'Austausch der defekten LED-Leuchten bis zur nächsten Wartungsrunde.',
                    'protocol_title' => 'Abnahme Wartungsrunde Büropark',
                    'protocol_items' => [
                        ['label' => 'Lüftungsfilter gewechselt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Notbeleuchtung geprüft', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Kleinreparaturen erledigt', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Rückmeldung Mängel an Objektleitung',
                    'comm_body' => 'Telefonat mit Objektleitung zur Freigabe der erforderlichen Kleinreparaturen.',
                ],
                'background_title' => 'Demo-Objektrunde',
                'procedure_code' => 'FM_OBJEKTKONTROLLE',
            ],
            DemoIndustry::WartungService => [
                'customers' => [
                    ['name' => 'Maschinenbau Muster AG', 'city' => 'Essen'],
                    ['name' => 'Getränke Beispiel GmbH', 'city' => 'Bremen'],
                    ['name' => 'Pumpenwerk Musterstadt', 'city' => 'Kassel'],
                ],
                'projects' => [
                    0 => ['Wartungsvertrag Produktionslinie 1', 'Störungsdienst Muster AG'],
                    1 => ['Jahreswartung Abfüllanlage'],
                    2 => ['Pumpenservice Musterstadt', 'Ersatzteilmanagement Musterstadt'],
                ],
                'asset' => [
                    'name' => 'Kompressor KAE-200',
                    'manufacturer' => 'Beispiel Drucklufttechnik',
                    'model' => 'KAE-200',
                    'class' => AssetClass::Machine,
                    'location' => 'Halle 2 Essen',
                ],
                'materials' => [
                    ['sku' => 'WS-FILTER-LUFT', 'name' => 'Luftfilterelement', 'unit' => 'Stk', 'price' => '24.9000'],
                    ['sku' => 'WS-OEL-46', 'name' => 'Hydrauliköl HLP 46', 'unit' => 'l', 'price' => '6.5000'],
                    ['sku' => 'WS-KEIL-XPA', 'name' => 'Keilriemen XPA 1250', 'unit' => 'Stk', 'price' => '11.8000'],
                ],
                'main_case' => [
                    'title' => 'Jahreswartung Kompressor KAE-200 — Beispielauftrag',
                    'content' => 'Wartung nach Herstellervorgabe gemäß Wartungsvertrag (Prüfintervall 12 Monate, SLA-Reaktion 4 h): Filter-/Ölwechsel, Keilriemen prüfen, Probelauf mit Messwerten. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Jahreswartung',
                    'open_issue_title' => 'Nachschmierung Antriebslager fällig',
                    'open_issue_desc' => 'Lager der Antriebseinheit innerhalb der SLA-Frist nachschmieren und im Wartungsnachweis dokumentieren.',
                    'protocol_title' => 'Abnahme Jahreswartung Kompressor',
                    'protocol_items' => [
                        ['label' => 'Filter und Öl gewechselt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Probelauf ohne Befund', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Messwerte im Sollbereich dokumentiert', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Terminbestätigung Wartungsfenster Halle 2',
                    'comm_body' => 'Telefonat mit der Instandhaltungsleitung zur Freigabe des Wartungsfensters und Abstimmung des Probelaufs.',
                ],
                'background_title' => 'Demo-Serviceeinsatz',
                'procedure_code' => 'AW_WARTUNG',
            ],
            DemoIndustry::Sicherheitsdienst => [
                'customers' => [
                    ['name' => 'Logistikpark Muster GmbH', 'city' => 'Duisburg'],
                    ['name' => 'Klinikum Beispielstadt', 'city' => 'Bochum'],
                    ['name' => 'Autohaus Mustermann', 'city' => 'Wuppertal'],
                ],
                'projects' => [
                    0 => ['Objektschutz Logistikpark', 'Revierstreife Gewerbegebiet Nord'],
                    1 => ['Empfangs- und Pfortendienst Klinikum'],
                    2 => ['Alarmverfolgung Autohaus', 'Schließdienst Autohaus'],
                ],
                'asset' => [
                    'name' => 'Schließanlage Haupttor Logistikpark',
                    'manufacturer' => 'Beispiel Schließtechnik',
                    'model' => 'SA-2400',
                    'class' => AssetClass::Installation,
                    'location' => 'Pforte Haupttor Duisburg',
                ],
                'materials' => [
                    ['sku' => 'SD-SIEGEL-100', 'name' => 'Plombensiegel nummeriert (100 Stk)', 'unit' => 'Pack', 'price' => '18.5000'],
                    ['sku' => 'SD-LAMPE-LED', 'name' => 'Taschenlampe LED wiederaufladbar', 'unit' => 'Stk', 'price' => '39.9000'],
                    ['sku' => 'SD-KONTROLL-RFID', 'name' => 'Kontrollpunkt-Chip RFID', 'unit' => 'Stk', 'price' => '4.2000'],
                ],
                'main_case' => [
                    'title' => 'Nachtschicht Objektschutz Logistikpark — Beispielauftrag',
                    'content' => 'Objektschutz mit Kontrollgängen nach Streifenplan (6 Kontrollpunkte, 2-Stunden-Intervall), Tor- und Zutrittskontrolle. Wachbuch-Vorfall 02:40 Uhr: Tor 3 unverschlossen vorgefunden, gesichert und gemeldet. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Objektschutz Nachtschicht',
                    'open_issue_title' => 'Tor 3: Schließzylinder tauschen',
                    'open_issue_desc' => 'Zylinder rastet nicht zuverlässig ein (Wachbuch 02:40 Uhr). Austausch mit dem Objektverantwortlichen abstimmen und im Schlüsselnachweis dokumentieren.',
                    'protocol_title' => 'Kontrollgang-Protokoll Logistikpark Nachtschicht',
                    'protocol_items' => [
                        ['label' => 'Alle Kontrollpunkte im Intervall quittiert', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Außenhaut, Tore und Notausgänge geprüft', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Vorfall Tor 3 an Objektleitung gemeldet', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Meldung Vorfall Tor 3 an Objektleitung',
                    'comm_body' => 'Telefonat mit der Objektleitung zur Meldung des unverschlossenen Tors und Abstimmung des Zylindertauschs.',
                ],
                'background_title' => 'Demo-Revierstreife',
                'procedure_code' => 'SD_REVIERFAHRT',
            ],
            DemoIndustry::BauAusbau => [
                'customers' => [
                    ['name' => 'Bauträger Muster GmbH', 'city' => 'Nürnberg'],
                    ['name' => 'Wohnungsgenossenschaft Beispiel eG', 'city' => 'Augsburg'],
                    ['name' => 'Architekturbüro Mustermann', 'city' => 'Regensburg'],
                ],
                'projects' => [
                    0 => ['Trockenbau Bürogebäude Süd', 'Estrich Bürogebäude Süd'],
                    1 => ['Malerarbeiten Sanierung Block C'],
                    2 => ['Innenausbau Praxis Mustermann', 'Nachträge Praxis Mustermann'],
                ],
                'asset' => [
                    'name' => 'Fassadengerüst Bürogebäude Süd',
                    'manufacturer' => 'Beispiel Gerüstbau',
                    'model' => 'Rahmengerüst 70',
                    'class' => AssetClass::Machine,
                    'location' => 'Baustelle Bürogebäude Süd, Nürnberg',
                ],
                'materials' => [
                    ['sku' => 'BAU-GKB-125', 'name' => 'Gipskartonplatte 12,5 mm', 'unit' => 'm²', 'price' => '3.9000'],
                    ['sku' => 'BAU-CW-75', 'name' => 'Ständerprofil CW 75', 'unit' => 'm', 'price' => '1.8000'],
                    ['sku' => 'BAU-SPACHTEL-25', 'name' => 'Fugenspachtel 25 kg', 'unit' => 'Sack', 'price' => '14.5000'],
                ],
                'main_case' => [
                    'title' => 'Trockenbau Bürogebäude Süd, 2. OG — Beispielauftrag',
                    'content' => 'Bautagebuch: bedeckt, 14 °C, 4 Arbeitskräfte. Ständerwände Achse B–D gestellt und einseitig beplankt; Aufmaß 2. OG: 186,40 m² Wandfläche. Behinderung: Elektro-Vorleistung Achse D fehlt. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Trockenbau 2. OG',
                    'open_issue_title' => 'Mangel: Maßabweichung Türöffnung Raum 2.14',
                    'open_issue_desc' => 'Lichte Breite 12 mm unter Plan; Nacharbeit vor Beplankung, Fotodokumentation in der Mängelliste ergänzen.',
                    'protocol_title' => 'Mängelprotokoll Teilabnahme 2. OG',
                    'protocol_items' => [
                        ['label' => 'Ständerwände lot- und fluchtgerecht', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Aufmaß 2. OG mit Bauleitung abgestimmt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Türöffnung Raum 2.14 nachgearbeitet', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Behinderungsanzeige Elektro-Vorleistung Achse D',
                    'comm_body' => 'Telefonat mit der Bauleitung zur fehlenden Elektro-Vorleistung; Fortsetzung der Beplankung erst nach Freigabe.',
                ],
                'background_title' => 'Demo-Bautag',
                'procedure_code' => 'BAU_TAGESBERICHT',
            ],
            DemoIndustry::Spedition => [
                'customers' => [
                    ['name' => 'Möbelwerk Muster GmbH', 'city' => 'Osnabrück'],
                    ['name' => 'Frischelogistik Beispiel KG', 'city' => 'Hamburg'],
                    ['name' => 'Baustoffhandel Mustermann', 'city' => 'Bielefeld'],
                ],
                'projects' => [
                    0 => ['Linienverkehr Möbelwerk Nord', 'Stückgut Möbelwerk Süd'],
                    1 => ['Kühltransporte Frischelogistik'],
                    2 => ['Baustellenlogistik Mustermann', 'Palettentausch Mustermann'],
                ],
                'asset' => [
                    'name' => 'Sattelzug MU-ST 1042',
                    'manufacturer' => 'Beispiel Nutzfahrzeuge',
                    'model' => 'Sattelzugmaschine 440',
                    'class' => AssetClass::Vehicle,
                    'location' => 'Betriebshof Osnabrück',
                ],
                'materials' => [
                    ['sku' => 'SP-ZURR-50', 'name' => 'Zurrgurt 50 mm / 5 t', 'unit' => 'Stk', 'price' => '12.9000'],
                    ['sku' => 'SP-KANTE-800', 'name' => 'Kantenschutzwinkel 800 mm', 'unit' => 'Stk', 'price' => '2.4000'],
                    ['sku' => 'SP-ANTIRUTSCH', 'name' => 'Antirutschmatte 5 m Rolle', 'unit' => 'Rolle', 'price' => '29.5000'],
                ],
                'main_case' => [
                    'title' => 'Tour Osnabrück–Hamburg, Stückgut Möbelwerk — Beispielauftrag',
                    'content' => 'Tour 4711: 3 Abholungen, 5 Zustellungen, 14 Paletten. Ladungssicherung vor Abfahrt geprüft. Lenk-/Ruhezeit: 45-min-Pause nach 4,5 h Lenkzeit auf dem Rasthof eingehalten. Zustellung 4: Transportschaden an 1 Palette (Kartonage eingedrückt) — Schadensprotokoll mit Fotos. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Tour 4711',
                    'open_issue_title' => 'Ladungsschaden Zustellung 4 beim Versicherer melden',
                    'open_issue_desc' => 'Schadensprotokoll und Fotos an die Transportversicherung übermitteln; Empfängervorbehalt auf dem Ablieferbeleg vermerkt.',
                    'protocol_title' => 'Schadensprotokoll Ladung Tour 4711',
                    'protocol_items' => [
                        ['label' => 'Ladungssicherung vor Abfahrt geprüft', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Lenk- und Ruhezeiten eingehalten', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Schadensmeldung an Versicherer übermittelt', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Avisierung Zustellung 4 und Schadensvorbehalt',
                    'comm_body' => 'Telefonat mit der Disposition zur Avisierung des Empfängers und zum Schadensvorbehalt auf dem Ablieferbeleg.',
                ],
                'background_title' => 'Demo-Tour',
                'procedure_code' => 'SP_LADUNGSSICHERUNG',
            ],
            DemoIndustry::Partyservice => [
                'customers' => [
                    ['name' => 'Muster Industrie AG (Betriebsfeier)', 'city' => 'Mannheim'],
                    ['name' => 'Familie Beispiel (Hochzeit)', 'city' => 'Heidelberg'],
                    ['name' => 'Stadtverwaltung Musterstadt', 'city' => 'Karlsruhe'],
                ],
                'projects' => [
                    0 => ['Sommerfest Muster Industrie', 'Weihnachtsfeier Muster Industrie'],
                    1 => ['Hochzeit Beispiel — Buffet 80 Personen'],
                    2 => ['Ratsempfang Musterstadt', 'Kaffeebar Bürgerfest Musterstadt'],
                ],
                'asset' => [
                    'name' => 'Kühlfahrzeug MU-PS 220',
                    'manufacturer' => 'Beispiel Kühlfahrzeuge',
                    'model' => 'Kühlkoffer 3,5 t',
                    'class' => AssetClass::Vehicle,
                    'location' => 'Betriebshof Mannheim',
                ],
                'materials' => [
                    ['sku' => 'PS-CHAFING-GN1', 'name' => 'Chafing Dish GN 1/1 (Miete)', 'unit' => 'Stk', 'price' => '9.5000'],
                    ['sku' => 'PS-BRENNPASTE', 'name' => 'Brennpaste 200 g', 'unit' => 'Stk', 'price' => '1.9000'],
                    ['sku' => 'PS-ALLERGEN-KARTE', 'name' => 'Allergenkarten-Set Buffet', 'unit' => 'Set', 'price' => '6.0000'],
                ],
                'main_case' => [
                    'title' => 'Sommerfest Muster Industrie, Buffet 120 Personen — Beispielauftrag',
                    'content' => 'Event-Catering: Anlieferung 16:00 Uhr, Buffetaufbau, Service bis 22:00 Uhr. Allergene je Gericht nach LMIV gekennzeichnet (Gluten, Milch, Ei, Sellerie, Senf). Hygiene-/Temperaturprotokoll: Kühlkette Anlieferung 4 °C, Warmhaltung ≥ 65 °C. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Sommerfest Service',
                    'open_issue_title' => 'Allergenkarte Dessert „Tiramisu" nachreichen',
                    'open_issue_desc' => 'Kennzeichnung Ei/Milch/Gluten fehlte am Buffet; Allergenkarte vor der nächsten Veranstaltung ergänzen und in der Menükarte hinterlegen.',
                    'protocol_title' => 'Hygiene- und Temperaturprotokoll Sommerfest',
                    'protocol_items' => [
                        ['label' => 'Kerntemperatur bei Anlieferung ≤ 7 °C dokumentiert', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Warmhaltetemperatur ≥ 65 °C vor Service geprüft', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Allergenkennzeichnung aller Gerichte vollständig', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Abstimmung Gästezahl und Sonderkost Sommerfest',
                    'comm_body' => 'Telefonat mit der Eventleitung zur finalen Gästezahl (120) und zu Sonderkost (vegan, glutenfrei) für 8 Personen.',
                ],
                'background_title' => 'Demo-Veranstaltung',
                'procedure_code' => 'PS_HACCP_KUEHLKETTE',
            ],
        };
    }
}

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
 * Aus dem DemoSeederService extrahiert (Refactoring Welle 2, B6b). Seit
 * MVP-837 je Branchenprofil ein Blueprint (19).
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
            DemoIndustry::DruckKopiershop => [
                'customers' => [
                    ['name' => 'Werbeagentur Muster GmbH', 'city' => 'Düsseldorf'],
                    ['name' => 'Kanzlei Beispiel & Partner', 'city' => 'Krefeld'],
                    ['name' => 'Sportverein Musterstadt e. V.', 'city' => 'Mönchengladbach'],
                ],
                'projects' => [
                    0 => ['Kampagnenflyer Herbst', 'Rollups Messeauftritt'],
                    1 => ['Briefbögen und Visitenkarten Kanzlei'],
                    2 => ['Vereinsheft Jubiläum', 'Banner Sportplatz'],
                ],
                'asset' => [
                    'name' => 'Digitaldruckmaschine DP-7000',
                    'manufacturer' => 'Beispiel Drucksysteme',
                    'model' => 'DP-7000',
                    'class' => AssetClass::Machine,
                    'location' => 'Produktion Düsseldorf',
                ],
                'materials' => [
                    ['sku' => 'DR-PAP-135', 'name' => 'Bilderdruckpapier 135 g/m² SRA3', 'unit' => 'Blatt', 'price' => '0.0600'],
                    ['sku' => 'DR-TONER-CMYK', 'name' => 'Tonerset CMYK DP-7000', 'unit' => 'Set', 'price' => '289.0000'],
                    ['sku' => 'DR-LAMI-125', 'name' => 'Laminierfolie 125 µm A4', 'unit' => 'Stk', 'price' => '0.1500'],
                ],
                'main_case' => [
                    'title' => 'Kampagnenflyer Herbst, 5.000 Stück — Beispielauftrag',
                    'content' => 'Druckauftrag: Datei-Check (Beschnitt, Farbprofil, Auflösung), Druckfreigabe durch den Kunden, Produktion auf DP-7000, Falzen auf Wickelfalz, Verpackung zu 500 Stück. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Druckauftrag Flyer',
                    'open_issue_title' => 'Farbabweichung Hausfarbe im Andruck',
                    'open_issue_desc' => 'Andruck weicht vom Farbmuster ab; Farbprofil prüfen und erneut zur Freigabe vorlegen.',
                    'protocol_title' => 'Qualitätskontrolle Kampagnenflyer',
                    'protocol_items' => [
                        ['label' => 'Datei-Check ohne Befund', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Druckfreigabe des Kunden dokumentiert', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Farbabgleich Andruck bestätigt', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Rückfrage Druckfreigabe Farbmuster',
                    'comm_body' => 'Telefonat mit der Agentur zur Farbabweichung im Andruck; Freigabe erst nach korrigiertem Proof.',
                ],
                'background_title' => 'Demo-Druckauftrag',
                'procedure_code' => 'DR_DRUCKFREIGABE',
            ],
            DemoIndustry::Galabau => [
                'customers' => [
                    ['name' => 'Wohnungsbau Muster GmbH', 'city' => 'Münster'],
                    ['name' => 'Gemeinde Beispielhausen', 'city' => 'Beispielhausen'],
                    ['name' => 'Familie Mustermann', 'city' => 'Coesfeld'],
                ],
                'projects' => [
                    0 => ['Grünpflege Wohnanlage Nord', 'Winterdienst Wohnanlage Nord'],
                    1 => ['Spielplatz Neuanlage Dorfmitte'],
                    2 => ['Gartenneuanlage Mustermann', 'Baumpflege Mustermann'],
                ],
                'asset' => [
                    'name' => 'Aufsitzmäher RM-1200',
                    'manufacturer' => 'Beispiel Gartentechnik',
                    'model' => 'RM-1200',
                    'class' => AssetClass::Machine,
                    'location' => 'Betriebshof Münster',
                ],
                'materials' => [
                    ['sku' => 'GL-RASEN-10', 'name' => 'Rasensaat Spielrasen 10 kg', 'unit' => 'Sack', 'price' => '49.0000'],
                    ['sku' => 'GL-RINDE-70', 'name' => 'Rindenmulch 70 l', 'unit' => 'Sack', 'price' => '6.9000'],
                    ['sku' => 'GL-PFLASTER-20', 'name' => 'Betonpflaster 20×10 cm grau', 'unit' => 'm²', 'price' => '14.5000'],
                ],
                'main_case' => [
                    'title' => 'Pflegegang Wohnanlage Nord, Frühjahr — Beispielauftrag',
                    'content' => 'Pflegegang: Rasenschnitt, Gehölzrückschnitt, Beete mulchen, Wege abkehren. Wetter: trocken, 16 °C. Nacharbeit: Hecke Block B durch Kunden gemeldet. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Pflegegang',
                    'open_issue_title' => 'Nacharbeit Heckenschnitt Block B',
                    'open_issue_desc' => 'Hecke an Block B beim Pflegegang ausgelassen; Nacharbeit vor der nächsten Abnahme einplanen.',
                    'protocol_title' => 'Abnahme Pflegegang Wohnanlage Nord',
                    'protocol_items' => [
                        ['label' => 'Rasenflächen gemäht und Schnittgut entsorgt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Beete gemulcht, Wege gereinigt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Heckenschnitt Block B nachgearbeitet', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Rückmeldung Hausverwaltung zum Pflegegang',
                    'comm_body' => 'Telefonat mit der Hausverwaltung zur Nacharbeit an Block B und zum Termin des nächsten Pflegegangs.',
                ],
                'background_title' => 'Demo-Pflegegang',
                'procedure_code' => 'GL_PFLEGEGANG',
            ],
            DemoIndustry::Gebaeudereinigung => [
                'customers' => [
                    ['name' => 'Bürohaus Muster GmbH', 'city' => 'Dresden'],
                    ['name' => 'Arztpraxis Dr. Beispiel', 'city' => 'Leipzig'],
                    ['name' => 'Schule Musterstadt', 'city' => 'Chemnitz'],
                ],
                'projects' => [
                    0 => ['Unterhaltsreinigung Bürohaus', 'Glasreinigung Bürohaus'],
                    1 => ['Praxisreinigung mit Hygieneplan'],
                    2 => ['Grundreinigung Sommerferien', 'Unterhaltsreinigung Schule'],
                ],
                'asset' => [
                    'name' => 'Scheuersaugmaschine SSM-45',
                    'manufacturer' => 'Beispiel Reinigungstechnik',
                    'model' => 'SSM-45',
                    'class' => AssetClass::Machine,
                    'location' => 'Reinigungslager Bürohaus Dresden',
                ],
                'materials' => [
                    ['sku' => 'GR-ALLZWECK-10', 'name' => 'Allzweckreiniger 10 l', 'unit' => 'Kanister', 'price' => '24.9000'],
                    ['sku' => 'GR-MIKRO-40', 'name' => 'Mikrofasertuch 40×40 cm', 'unit' => 'Stk', 'price' => '1.2000'],
                    ['sku' => 'GR-SANI-1', 'name' => 'Sanitärreiniger 1 l', 'unit' => 'Flasche', 'price' => '4.9000'],
                ],
                'main_case' => [
                    'title' => 'Unterhaltsreinigung Bürohaus, Etage 3 — Beispielauftrag',
                    'content' => 'Unterhaltsreinigung nach Leistungsverzeichnis: Büros, Sanitär, Teeküchen; Qualitätskontrolle mit Stichprobe in fünf Räumen. Befund: Kalkflecken Sanitär 3.2. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Unterhaltsreinigung',
                    'open_issue_title' => 'Nacharbeit Sanitär 3.2 (Kalkflecken)',
                    'open_issue_desc' => 'Armaturen und Fliesen in Sanitär 3.2 nachreinigen; Objektleitung bittet um Rückmeldung mit Foto.',
                    'protocol_title' => 'Qualitätskontrolle Etage 3',
                    'protocol_items' => [
                        ['label' => 'Büros nach Leistungsverzeichnis gereinigt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Teeküchen und Böden ohne Befund', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Sanitär 3.2 nachgearbeitet', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Rückmeldung Objektleitung zur Qualitätskontrolle',
                    'comm_body' => 'Telefonat mit der Objektleitung zum Befund in Sanitär 3.2 und zur Terminierung der Nacharbeit.',
                ],
                'background_title' => 'Demo-Reinigungseinsatz',
                'procedure_code' => 'GR_QS_KONTROLLE',
            ],
            DemoIndustry::Handwerk => [
                'customers' => [
                    ['name' => 'Bäckerei Muster', 'city' => 'Würzburg'],
                    ['name' => 'Hausverwaltung Beispiel GmbH', 'city' => 'Schweinfurt'],
                    ['name' => 'Hotel Mustermann', 'city' => 'Bamberg'],
                ],
                'projects' => [
                    0 => ['Wartung Lüftung Backstube', 'Reparatur Sektionaltor'],
                    1 => ['Kleinreparaturen Wohnanlage Süd'],
                    2 => ['Installation Zimmertüren Hotel', 'Kundendienst Hotel'],
                ],
                'asset' => [
                    'name' => 'Sektionaltor Halle 1',
                    'manufacturer' => 'Beispiel Torsysteme',
                    'model' => 'ST-4000',
                    'class' => AssetClass::Installation,
                    'location' => 'Backstube Würzburg',
                ],
                'materials' => [
                    ['sku' => 'HW-SCHR-4X40', 'name' => 'Spanplattenschrauben 4×40 (200 Stk)', 'unit' => 'Pack', 'price' => '6.9000'],
                    ['sku' => 'HW-SILIKON-310', 'name' => 'Sanitärsilikon 310 ml', 'unit' => 'Kartusche', 'price' => '5.4000'],
                    ['sku' => 'HW-FEDER-ST', 'name' => 'Torsionsfeder ST-4000', 'unit' => 'Stk', 'price' => '89.0000'],
                ],
                'main_case' => [
                    'title' => 'Wartung Sektionaltor Halle 1 — Beispielauftrag',
                    'content' => 'Wartungseinsatz nach Herstellervorgabe: Federspannung prüfen, Laufrollen schmieren, Sicherheitseinrichtungen testen, Torsionsfeder tauschen. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Wartung Sektionaltor',
                    'open_issue_title' => 'Ersatzteil Torsionsfeder nachbestellen',
                    'open_issue_desc' => 'Zweite Feder zeigt Rissbildung; Ersatzteil bestellen und Folgetermin mit dem Kunden abstimmen.',
                    'protocol_title' => 'Abnahme Wartung Sektionaltor',
                    'protocol_items' => [
                        ['label' => 'Sicherheitseinrichtungen geprüft', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Laufrollen und Führungen geschmiert', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Zweite Torsionsfeder getauscht', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Abstimmung Folgetermin Federtausch',
                    'comm_body' => 'Telefonat mit der Bäckerei zum Folgetermin außerhalb der Backzeiten.',
                ],
                'background_title' => 'Demo-Serviceeinsatz',
                'procedure_code' => 'HW_MAINTENANCE',
            ],
            DemoIndustry::KfzFuhrparkservice => [
                'customers' => [
                    ['name' => 'Pflegedienst Muster GmbH (Flotte)', 'city' => 'Kiel'],
                    ['name' => 'Kurierdienst Beispiel', 'city' => 'Lübeck'],
                    ['name' => 'Stadtwerke Musterstadt', 'city' => 'Neumünster'],
                ],
                'projects' => [
                    0 => ['Flottenwartung Pflegedienst', 'Reifenservice Pflegedienst'],
                    1 => ['Inspektion Kurierfahrzeuge'],
                    2 => ['HU/AU-Vorbereitung Stadtwerke', 'Schadensabwicklung Stadtwerke'],
                ],
                'asset' => [
                    'name' => 'Transporter MU-PD 301',
                    'manufacturer' => 'Beispiel Nutzfahrzeuge',
                    'model' => 'Kastenwagen L2H2',
                    'class' => AssetClass::Vehicle,
                    'location' => 'Werkstatt Kiel',
                ],
                'materials' => [
                    ['sku' => 'KFZ-OEL-5W30', 'name' => 'Motoröl 5W-30 (1 l)', 'unit' => 'l', 'price' => '9.9000'],
                    ['sku' => 'KFZ-FILTER-OEL', 'name' => 'Ölfilter', 'unit' => 'Stk', 'price' => '12.5000'],
                    ['sku' => 'KFZ-BREMS-VA', 'name' => 'Bremsbeläge Vorderachse (Satz)', 'unit' => 'Satz', 'price' => '68.0000'],
                ],
                'main_case' => [
                    'title' => 'Inspektion Transporter MU-PD 301 — Beispielauftrag',
                    'content' => 'Fahrzeugannahme mit Schadensrundgang, Inspektion nach Serviceplan (Ölwechsel, Filter, Bremsen), Probefahrt, Übergabe mit Fahrzeugakte. Befund: Bremsbeläge vorn an der Verschleißgrenze. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Inspektion',
                    'open_issue_title' => 'Bremsbeläge Vorderachse tauschen',
                    'open_issue_desc' => 'Verschleißgrenze erreicht; Freigabe des Kunden einholen und Ersatzteil einplanen.',
                    'protocol_title' => 'Übergabeprotokoll Inspektion',
                    'protocol_items' => [
                        ['label' => 'Ölwechsel und Filter nach Serviceplan', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Probefahrt ohne Befund', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Bremsbeläge vorn getauscht', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Freigabe Bremsenreparatur',
                    'comm_body' => 'Telefonat mit dem Fuhrparkverantwortlichen zur Freigabe der Bremsenreparatur und zum Abholtermin.',
                ],
                'background_title' => 'Demo-Werkstattauftrag',
                'procedure_code' => 'KFZ_WARTUNG',
            ],
            DemoIndustry::Pflege => [
                'customers' => [
                    ['name' => 'Klient A (Muster)', 'city' => 'Bonn'],
                    ['name' => 'Klient B (Beispiel)', 'city' => 'Siegburg'],
                    ['name' => 'Seniorenwohnanlage Musterstadt', 'city' => 'Troisdorf'],
                ],
                'projects' => [
                    0 => ['Tour Nord — Klient A', 'Behandlungspflege Klient A'],
                    1 => ['Tour Süd — Klient B'],
                    2 => ['Betreuung Seniorenwohnanlage', 'Beratungsbesuche Seniorenwohnanlage'],
                ],
                'asset' => [
                    'name' => 'Pflegebett P-200',
                    'manufacturer' => 'Beispiel Medizintechnik',
                    'model' => 'P-200',
                    'class' => AssetClass::Device,
                    'location' => 'Wohnung Klient A, Bonn',
                ],
                'materials' => [
                    ['sku' => 'PF-HANDSCHUH-100', 'name' => 'Einmalhandschuhe Nitril (100 Stk)', 'unit' => 'Box', 'price' => '8.9000'],
                    ['sku' => 'PF-DESINF-500', 'name' => 'Händedesinfektion 500 ml', 'unit' => 'Flasche', 'price' => '4.5000'],
                    ['sku' => 'PF-KOMPRESSE-10', 'name' => 'Sterile Kompressen 10×10 cm', 'unit' => 'Pack', 'price' => '3.2000'],
                ],
                'main_case' => [
                    'title' => 'Tour Nord, Frühdienst — Beispielauftrag',
                    'content' => 'Frühdienst Tour Nord: Grundpflege und Medikamentengabe nach Pflegeplan bei fünf Klienten, Dokumentation je Einsatz, Übergabe an den Spätdienst. Alle Angaben generisch und erfunden. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Tour Nord',
                    'open_issue_title' => 'Beratungsbesuch nach § 37.3 terminieren',
                    'open_issue_desc' => 'Quartalsfrist für den Beratungsbesuch bei Klient A läuft ab; Termin mit den Angehörigen abstimmen.',
                    'protocol_title' => 'Pflegevisite Tour Nord',
                    'protocol_items' => [
                        ['label' => 'Pflegeplan aktuell und umgesetzt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Medikamentengabe dokumentiert', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Beratungsbesuch terminiert', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Abstimmung Beratungsbesuch mit Angehörigen',
                    'comm_body' => 'Telefonat mit den Angehörigen von Klient A zum Termin des Beratungsbesuchs.',
                ],
                'background_title' => 'Demo-Pflegeeinsatz',
                'procedure_code' => 'PF_MEDIKAMENTENGABE',
            ],
            DemoIndustry::Shk => [
                'customers' => [
                    ['name' => 'Hausverwaltung Muster GmbH', 'city' => 'Freiburg'],
                    ['name' => 'Familie Beispiel', 'city' => 'Offenburg'],
                    ['name' => 'Kindergarten Musterstadt', 'city' => 'Lahr'],
                ],
                'projects' => [
                    0 => ['Heizungswartung Wohnanlage West', 'Notdienst Wohnanlage West'],
                    1 => ['Badsanierung Beispiel'],
                    2 => ['Trinkwasserprüfung Kindergarten', 'Wartung Lüftung Kindergarten'],
                ],
                'asset' => [
                    'name' => 'Gas-Brennwertkessel GBK-24',
                    'manufacturer' => 'Beispiel Heiztechnik',
                    'model' => 'GBK-24',
                    'class' => AssetClass::Installation,
                    'location' => 'Heizraum Wohnanlage West, Freiburg',
                ],
                'materials' => [
                    ['sku' => 'SHK-DICHT-SET', 'name' => 'Dichtungssatz Brenner GBK', 'unit' => 'Set', 'price' => '18.5000'],
                    ['sku' => 'SHK-ELEKTRODE', 'name' => 'Zündelektrode GBK-24', 'unit' => 'Stk', 'price' => '34.0000'],
                    ['sku' => 'SHK-KUPFER-15', 'name' => 'Kupferrohr 15 mm', 'unit' => 'm', 'price' => '4.8000'],
                ],
                'main_case' => [
                    'title' => 'Heizungswartung Wohnanlage West — Beispielauftrag',
                    'content' => 'Jahreswartung Gas-Brennwertkessel: Brenner reinigen, Elektrode und Dichtungen tauschen, Abgasmessung, Druckprüfung Heizkreis. Befund: Ausdehnungsgefäß Vordruck zu niedrig. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Heizungswartung',
                    'open_issue_title' => 'Ausdehnungsgefäß Vordruck nachstellen',
                    'open_issue_desc' => 'Vordruck unter Sollwert; Gefäß prüfen, ggf. tauschen und im Wartungsnachweis dokumentieren.',
                    'protocol_title' => 'Wartungsprotokoll GBK-24',
                    'protocol_items' => [
                        ['label' => 'Abgaswerte im Sollbereich', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Druckprüfung Heizkreis bestanden', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Ausdehnungsgefäß nachgestellt', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Terminabstimmung Wartungsfenster Heizraum',
                    'comm_body' => 'Telefonat mit der Hausverwaltung zum Zugang zum Heizraum und zur Information der Mieter.',
                ],
                'background_title' => 'Demo-SHK-Einsatz',
                'procedure_code' => 'SHK_WARTUNG',
            ],
            DemoIndustry::Steuerberater => [
                'customers' => [
                    ['name' => 'Muster Handels GmbH', 'city' => 'Wiesbaden'],
                    ['name' => 'Beispiel Einzelunternehmen', 'city' => 'Mainz'],
                    ['name' => 'Mustermann & Söhne KG', 'city' => 'Darmstadt'],
                ],
                'projects' => [
                    0 => ['Finanzbuchführung Muster Handels GmbH', 'Jahresabschluss Muster Handels GmbH'],
                    1 => ['Einnahmen-Überschuss-Rechnung Beispiel'],
                    2 => ['Lohnbuchhaltung Mustermann KG', 'Betriebsprüfung Mustermann KG'],
                ],
                'asset' => [
                    'name' => 'Registrierkasse mit TSE (Mandant)',
                    'manufacturer' => 'Beispiel Kassensysteme',
                    'model' => 'KS-300 TSE',
                    'class' => AssetClass::Device,
                    'location' => 'Ladenlokal Muster Handels GmbH, Wiesbaden',
                ],
                'materials' => [
                    ['sku' => 'STB-ORDNER', 'name' => 'Belegordner Mandant', 'unit' => 'Stk', 'price' => '3.9000'],
                    ['sku' => 'STB-SCAN-BOX', 'name' => 'Scanbox Belegtransport', 'unit' => 'Stk', 'price' => '12.0000'],
                    ['sku' => 'STB-PORTO', 'name' => 'Porto Einschreiben', 'unit' => 'Stk', 'price' => '4.1500'],
                ],
                'main_case' => [
                    'title' => 'Finanzbuchführung Monat Muster Handels GmbH — Beispielauftrag',
                    'content' => 'Monatsbuchführung: Belege importieren, Kontierung prüfen, offene Posten abstimmen, Umsatzsteuer-Voranmeldung im Vier-Augen-Prinzip freigeben. Rückfrage: drei Belege ohne Rechnungsempfänger. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Finanzbuchführung',
                    'open_issue_title' => 'Mandantenrückfrage zu drei Belegen',
                    'open_issue_desc' => 'Belege ohne Rechnungsempfänger; Klärung mit dem Mandanten vor Abgabe der Voranmeldung.',
                    'protocol_title' => 'Monatsabschluss Finanzbuchführung',
                    'protocol_items' => [
                        ['label' => 'Belege vollständig importiert', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Offene Posten abgestimmt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Voranmeldung im Vier-Augen-Prinzip freigegeben', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Rückfrage Belege ohne Rechnungsempfänger',
                    'comm_body' => 'Telefonat mit der Geschäftsführung des Mandanten zur Nachreichung der Belegangaben.',
                ],
                'background_title' => 'Demo-Mandantenvorgang',
                'procedure_code' => 'STB_FIBU_MONAT',
            ],
            DemoIndustry::TaxiMietwagen => [
                'customers' => [
                    ['name' => 'Krankenkasse Muster (Krankenfahrten)', 'city' => 'Erfurt'],
                    ['name' => 'Hotel Beispiel', 'city' => 'Weimar'],
                    ['name' => 'Muster Industrie AG (Werksverkehr)', 'city' => 'Jena'],
                ],
                'projects' => [
                    0 => ['Krankenfahrten Dialyse', 'Serienfahrten Reha'],
                    1 => ['Flughafentransfer Hotelgäste'],
                    2 => ['Werksverkehr Frühschicht', 'Mietwagen Geschäftsreisen'],
                ],
                'asset' => [
                    'name' => 'Taxi MU-TX 1001',
                    'manufacturer' => 'Beispiel Automobile',
                    'model' => 'Kombi Taxi',
                    'class' => AssetClass::Vehicle,
                    'location' => 'Betriebshof Erfurt',
                ],
                'materials' => [
                    ['sku' => 'TX-QUITTUNG', 'name' => 'Quittungsblock Taxi', 'unit' => 'Block', 'price' => '2.9000'],
                    ['sku' => 'TX-KINDERSITZ', 'name' => 'Kindersitz (Leihe)', 'unit' => 'Stk', 'price' => '0.0000'],
                    ['sku' => 'TX-DESINF-500', 'name' => 'Flächendesinfektion 500 ml', 'unit' => 'Flasche', 'price' => '5.9000'],
                ],
                'main_case' => [
                    'title' => 'Fahrtauftrag Krankenfahrt Dialyse, Vorbestellung — Beispielauftrag',
                    'content' => 'Vorbestellte Krankenfahrt: Annahme mit Fahrtauftrag und Verordnung, Disposition auf Taxi MU-TX 1001, Personenfahrt mit Wartezeit, Abrechnung gegenüber der Krankenkasse. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Fahrtauftrag',
                    'open_issue_title' => 'Verordnung für Rückfahrt fehlt',
                    'open_issue_desc' => 'Rückfahrt ohne Verordnungsblatt; Nachreichung mit der Praxis klären, sonst keine Abrechnung.',
                    'protocol_title' => 'Schichtabrechnung Frühschicht',
                    'protocol_items' => [
                        ['label' => 'Fahrtaufträge vollständig erfasst', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Bar- und Kartenumsätze abgestimmt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Verordnung Rückfahrt nachgereicht', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Rückfrage Verordnung bei der Praxis',
                    'comm_body' => 'Telefonat mit der Praxis zur Nachreichung des Verordnungsblatts für die Rückfahrt.',
                ],
                'background_title' => 'Demo-Fahrtauftrag',
                'procedure_code' => 'TX_FAHRTAUFTRAG_ANNAHME',
            ],
            DemoIndustry::Veranstalter => [
                'customers' => [
                    ['name' => 'Stadt Musterstadt (Kulturamt)', 'city' => 'Musterstadt'],
                    ['name' => 'Muster Technologie AG', 'city' => 'Ulm'],
                    ['name' => 'Verein Beispielfest e. V.', 'city' => 'Biberach'],
                ],
                'projects' => [
                    0 => ['Stadtfest Musterstadt', 'Weihnachtsmarkt Musterstadt'],
                    1 => ['Firmenjubiläum Muster Technologie'],
                    2 => ['Beispielfest Sommer', 'Beispielfest Ticketing'],
                ],
                'asset' => [
                    'name' => 'Mobile Bühne 8×6 m',
                    'manufacturer' => 'Beispiel Bühnenbau',
                    'model' => 'MB-86',
                    'class' => AssetClass::Installation,
                    'location' => 'Marktplatz Musterstadt',
                ],
                'materials' => [
                    ['sku' => 'VA-BAUZAUN', 'name' => 'Bauzaunelement 3,5 m (Miete/Tag)', 'unit' => 'Stk', 'price' => '2.5000'],
                    ['sku' => 'VA-BAND-100', 'name' => 'Einlassbändchen (100 Stk)', 'unit' => 'Rolle', 'price' => '9.9000'],
                    ['sku' => 'VA-FEUERL-6', 'name' => 'Feuerlöscher 6 kg (Miete)', 'unit' => 'Stk', 'price' => '12.0000'],
                ],
                'main_case' => [
                    'title' => 'Stadtfest Musterstadt, Durchführung Samstag — Beispielauftrag',
                    'content' => 'Durchführung Stadtfest: Genehmigungen (Sondernutzung, Gestattung, Lärmschutz) vorliegend, Sicherheitskonzept im Vier-Augen-Prinzip freigegeben, Aufbau koordiniert, 4.000 Besucher erwartet. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Stadtfest',
                    'open_issue_title' => 'Gestattung Ausschank verlängern',
                    'open_issue_desc' => 'Gestattung endet 22:00 Uhr, Programm läuft bis 23:00 Uhr; Verlängerung beim Ordnungsamt beantragen.',
                    'protocol_title' => 'Durchführungsprotokoll Stadtfest',
                    'protocol_items' => [
                        ['label' => 'Genehmigungen vollständig und vor Ort', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Sicherheitskonzept mit Ordnungsdienst abgestimmt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Gestattung Ausschank verlängert', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Abstimmung Verlängerung mit dem Ordnungsamt',
                    'comm_body' => 'Telefonat mit dem Ordnungsamt zur Verlängerung der Gestattung und zur Lärmschutzauflage.',
                ],
                'background_title' => 'Demo-Veranstaltung',
                'procedure_code' => 'VA_GENEHMIGUNGEN',
            ],
            DemoIndustry::Veranstaltungstechnik => [
                'customers' => [
                    ['name' => 'Kongresszentrum Muster', 'city' => 'Hannover'],
                    ['name' => 'Beispiel Eventagentur', 'city' => 'Braunschweig'],
                    ['name' => 'Stadthalle Musterstadt', 'city' => 'Göttingen'],
                ],
                'projects' => [
                    0 => ['Konferenz Frühjahr — Ton und Licht', 'Hybrid-Event Streaming'],
                    1 => ['Produktlaunch Bühnentechnik'],
                    2 => ['Konzertreihe Stadthalle', 'Rigging Stadthalle'],
                ],
                'asset' => [
                    'name' => 'Line-Array-System LA-12',
                    'manufacturer' => 'Beispiel Audio',
                    'model' => 'LA-12',
                    'class' => AssetClass::Device,
                    'location' => 'Lager Hannover',
                ],
                'materials' => [
                    ['sku' => 'VT-XLR-10', 'name' => 'XLR-Kabel 10 m', 'unit' => 'Stk', 'price' => '14.9000'],
                    ['sku' => 'VT-GAFFA-50', 'name' => 'Gaffa-Tape 50 m schwarz', 'unit' => 'Rolle', 'price' => '9.5000'],
                    ['sku' => 'VT-SAFETY-2', 'name' => 'Safety-Stahlseil 2 m', 'unit' => 'Stk', 'price' => '6.9000'],
                ],
                'main_case' => [
                    'title' => 'Konferenz Frühjahr, Aufbau und Soundcheck — Beispielauftrag',
                    'content' => 'Aufbau Beschallung und Licht im Plenarsaal, Stromcheck und Rigging-Check im Vier-Augen-Prinzip, Soundcheck mit Referenten. Befund: Brummschleife Rednerpult. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Aufbau Konferenz',
                    'open_issue_title' => 'Brummschleife Rednerpult beseitigen',
                    'open_issue_desc' => 'Brummen auf dem Pultmikrofon; DI-Box mit Ground-Lift einsetzen und vor Einlass erneut prüfen.',
                    'protocol_title' => 'Safety-Check und Soundcheck Plenarsaal',
                    'protocol_items' => [
                        ['label' => 'Stromverteilung geprüft und freigegeben', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Rigging-Punkte mit Safeties gesichert', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Brummschleife Rednerpult beseitigt', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Abstimmung Soundcheck-Zeitfenster mit dem Veranstalter',
                    'comm_body' => 'Telefonat mit der Eventagentur zum Zeitfenster für den Soundcheck vor dem Einlass.',
                ],
                'background_title' => 'Demo-Technikeinsatz',
                'procedure_code' => 'VT_SOUNDCHECK',
            ],
            // Sportverein (MVP-848): Arbeitstagebuch der Geschäftsstelle; die
            // Vereinsdaten selbst legt der ClubDemoSeeder an.
            DemoIndustry::Verein => [
                'customers' => [
                    ['name' => 'Stadt Musterstadt, Sportamt', 'city' => 'Musterstadt'],
                    ['name' => 'Bäckerei Beispiel (Sponsor)', 'city' => 'Musterstadt'],
                    ['name' => 'Grundschule am Park', 'city' => 'Musterstadt'],
                ],
                'projects' => [
                    0 => ['Hallenbelegung Sporthalle Nord', 'Sportfest Stadtpark'],
                    1 => ['Trikotsponsoring E-Jugend'],
                    2 => ['Schul-AG Judo', 'Bewegungstag Grundschule'],
                ],
                'asset' => [
                    'name' => 'Vereinsbus MU-SV 9 (9-Sitzer)',
                    'manufacturer' => 'Beispiel Fahrzeugbau',
                    'model' => 'Kleinbus 9-Sitzer',
                    'class' => AssetClass::Vehicle,
                    'location' => 'Vereinsheim Musterstadt',
                ],
                'materials' => [
                    ['sku' => 'VE-BALL-5', 'name' => 'Trainingsball Größe 5', 'unit' => 'Stk', 'price' => '18.5000'],
                    ['sku' => 'VE-EH-SET', 'name' => 'Erste-Hilfe-Set Sport', 'unit' => 'Stk', 'price' => '34.9000'],
                    ['sku' => 'VE-HUT-20', 'name' => 'Markierungshütchen 20er-Set', 'unit' => 'Set', 'price' => '12.0000'],
                ],
                'main_case' => [
                    'title' => 'Sportfest Stadtpark, Aufbau und Helferplanung — Beispielauftrag',
                    'content' => 'Sportfest des Vereins im Stadtpark: Sportstätten und Bühne belegt, Helfer und Terminrollen eingeteilt, Erste Hilfe mit dem Sanitätsdienst abgestimmt. Befund: Stromanschluss für die Zeitmessung fehlt. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Sportfest',
                    'open_issue_title' => 'Stromanschluss Zeitmessung klären',
                    'open_issue_desc' => 'Für die Zeitmessung an der Laufbahn fehlt ein abgesicherter Anschluss; vor dem Aufbautag mit dem Sportamt klären.',
                    'protocol_title' => 'Sicherheits- und Aufbaucheck Sportfest',
                    'protocol_items' => [
                        ['label' => 'Sportstätten und Ressourcen belegt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Erste Hilfe und Sanitätsdienst bestätigt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Stromanschluss Zeitmessung geklärt', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Abstimmung Platzbelegung mit dem Sportamt',
                    'comm_body' => 'Telefonat mit dem Sportamt zur Belegung des Stadtparks und der Laufbahn am Sportfest-Wochenende.',
                ],
                'background_title' => 'Demo-Trainingsbetrieb',
                'procedure_code' => 'VE_SPORTFEST',
            ],
        };
    }
}

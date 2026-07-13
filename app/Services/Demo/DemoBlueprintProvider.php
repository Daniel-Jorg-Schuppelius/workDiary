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
            ],
        };
    }
}

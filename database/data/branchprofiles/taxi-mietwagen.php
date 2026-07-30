<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : taxi-mietwagen.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Enums\Classification\ClassificationRequirementPhase;
use App\Enums\Classification\ClassificationRequirementSeverity;

/**
 * Branchenprofil Taxi- und Mietwagenunternehmen (MVP-456,
 * branchenprofil-taxi-mietwagen.md). Personenbeförderung nach PBefG:
 * Fahrtaufträge, Disposition, Betriebsarten-Gates, Geräte-/Konzessions-
 * Nachweise und Schichtabrechnung. Die Fachakte (`passenger_rides`) und die
 * Pflichtgates der Betriebsarten liegen im Code (PassengerRideService) —
 * dieses Profil liefert Klassifikationen, Pflichtfelder der
 * Klassifikations-Domänen, Prozedurvorlagen und den Qualifikations-Seed.
 *
 * Getrennt vom Speditionsprofil: dort Sendung/Ladung/Zustellnachweis, hier
 * Fahrtauftrag, Fahrgastanforderungen, Tarif-/Gerätesnapshot und Zahlung.
 */
return [
    'code' => 'taxi-mietwagen',
    'label' => 'Taxi / Mietwagen (Personenbeförderung)',
    'version' => 1,
    'description' => 'Personenbeförderung nach PBefG: Taxen-, Mietwagen- und gebündelter Bedarfsverkehr mit Disposition, Konzessions-/Geräte-Nachweisen, Tarifen und Schichtabrechnung.',

    'classifications' => [
        'entry_type' => [
            ['code' => 'fahrtanfrage', 'label' => 'Fahrtanfrage'],
            ['code' => 'vorbestellung', 'label' => 'Vorbestellung'],
            ['code' => 'sofortfahrt', 'label' => 'Sofortfahrt'],
            ['code' => 'serienfahrt', 'label' => 'Serienfahrt'],
            ['code' => 'bereitstellung', 'label' => 'Bereitstellung'],
            ['code' => 'personenfahrt', 'label' => 'Personenfahrt'],
            ['code' => 'wartezeit', 'label' => 'Wartezeit'],
            ['code' => 'leerfahrt', 'label' => 'Leerfahrt'],
            ['code' => 'fahrzeugwechsel', 'label' => 'Fahrzeugwechsel'],
            ['code' => 'stoerung', 'label' => 'Störung'],
            ['code' => 'unfall', 'label' => 'Unfall'],
            ['code' => 'reklamation', 'label' => 'Reklamation'],
            ['code' => 'abrechnung', 'label' => 'Abrechnung'],
        ],
        'activity' => [
            ['code' => 'annehmen', 'label' => 'Annehmen'],
            ['code' => 'disponieren', 'label' => 'Disponieren'],
            ['code' => 'anfahren', 'label' => 'Anfahren'],
            ['code' => 'warten', 'label' => 'Warten'],
            ['code' => 'aufnehmen', 'label' => 'Fahrgast aufnehmen'],
            ['code' => 'befoerdern', 'label' => 'Befördern'],
            ['code' => 'abrechnen', 'label' => 'Abrechnen'],
            ['code' => 'kassieren', 'label' => 'Kassieren'],
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'product_group' => [
            ['code' => 'taxifahrt', 'label' => 'Taxifahrt'],
            ['code' => 'mietwagenfahrt', 'label' => 'Mietwagenfahrt'],
            ['code' => 'gebuendelterBedarfsverkehr', 'label' => 'Gebündelter Bedarfsverkehr'],
            ['code' => 'krankenfahrt', 'label' => 'Krankenfahrt'],
            ['code' => 'schuelerfahrt', 'label' => 'Schülerfahrt'],
            ['code' => 'rollstuhlfahrt', 'label' => 'Rollstuhlfahrt'],
            ['code' => 'grossraumfahrt', 'label' => 'Großraumfahrt'],
        ],
        'defect_type' => [
            ['code' => 'verspaetung', 'label' => 'Verspätung'],
            ['code' => 'fahrgastNichtErschienen', 'label' => 'Fahrgast nicht erschienen'],
            ['code' => 'falscheAdresse', 'label' => 'Falsche Adresse'],
            ['code' => 'fahrzeugDefekt', 'label' => 'Fahrzeugdefekt'],
            ['code' => 'fahrerAusfall', 'label' => 'Fahrerausfall'],
            ['code' => 'taxameterStoerung', 'label' => 'Taxameterstörung'],
            ['code' => 'tseStoerung', 'label' => 'TSE-Störung'],
            ['code' => 'zahlungFehlgeschlagen', 'label' => 'Zahlung fehlgeschlagen'],
            ['code' => 'unfall', 'label' => 'Unfall'],
            ['code' => 'barrierefreiheitNichtErfuellt', 'label' => 'Barrierefreiheit nicht erfüllt'],
        ],
        'root_cause' => [
            ['code' => 'verkehr', 'label' => 'Verkehr'],
            ['code' => 'wetter', 'label' => 'Wetter'],
            ['code' => 'disposition', 'label' => 'Disposition'],
            ['code' => 'kunde', 'label' => 'Kunde'],
            ['code' => 'vermittler', 'label' => 'Vermittler'],
            ['code' => 'fahrzeug', 'label' => 'Fahrzeug'],
            ['code' => 'fahrer', 'label' => 'Fahrer'],
            ['code' => 'geraet', 'label' => 'Gerät'],
            ['code' => 'zahlungsdienst', 'label' => 'Zahlungsdienst'],
        ],
        'result' => [
            ['code' => 'angenommen', 'label' => 'Angenommen'],
            ['code' => 'disponiert', 'label' => 'Disponiert'],
            ['code' => 'fahrgastAufgenommen', 'label' => 'Fahrgast aufgenommen'],
            ['code' => 'abgeschlossen', 'label' => 'Abgeschlossen'],
            ['code' => 'storniert', 'label' => 'Storniert'],
            ['code' => 'noShow', 'label' => 'No-show'],
            ['code' => 'abgebrochen', 'label' => 'Abgebrochen'],
            ['code' => 'umdisponiert', 'label' => 'Umdisponiert'],
            ['code' => 'abgerechnet', 'label' => 'Abgerechnet'],
        ],
        'priority' => [
            ['code' => 'standard', 'label' => 'Standard'],
            ['code' => 'vorbestellt', 'label' => 'Vorbestellt'],
            ['code' => 'dringend', 'label' => 'Dringend'],
            ['code' => 'medizinisch', 'label' => 'Medizinisch'],
            ['code' => 'barrierefrei', 'label' => 'Barrierefrei'],
        ],
        'dienstmittel_type' => [
            ['code' => 'taxi', 'label' => 'Taxi'],
            ['code' => 'mietwagen', 'label' => 'Mietwagen'],
            ['code' => 'grossraumtaxi', 'label' => 'Großraumtaxi'],
            ['code' => 'rollstuhltaxi', 'label' => 'Rollstuhltaxi'],
            ['code' => 'taxameter', 'label' => 'Taxameter'],
            ['code' => 'wegstreckenzaehler', 'label' => 'Wegstreckenzähler'],
            ['code' => 'tse', 'label' => 'TSE / Sicherheitsmodul'],
            ['code' => 'kartenleser', 'label' => 'Kartenleser'],
            ['code' => 'kindersitz', 'label' => 'Kindersitz'],
        ],
        'permit_type' => [
            ['code' => 'taxikonzession', 'label' => 'Taxikonzession (§ 47 PBefG)'],
            ['code' => 'mietwagengenehmigung', 'label' => 'Mietwagengenehmigung (§ 49 PBefG)'],
            ['code' => 'bedarfsverkehrsgenehmigung', 'label' => 'Genehmigung gebündelter Bedarfsverkehr (§ 50 PBefG)'],
            ['code' => 'fahrgastbefoerderung', 'label' => 'Fahrerlaubnis zur Fahrgastbeförderung (§ 48 FeV)'],
            ['code' => 'eichnachweis', 'label' => 'Eichnachweis Taxameter/Wegstreckenzähler'],
            ['code' => 'bokraftPruefung', 'label' => 'BOKraft-Prüfung'],
        ],
    ],

    'classification_requirements' => [
        [
            'entry_type_code' => 'fahrtanfrage',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
            'note' => 'Beförderungsart bei Annahme festlegen (Betriebsart wird eingefroren).',
        ],
        [
            'entry_type_code' => 'personenfahrt',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'stoerung',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'unfall',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'reklamation',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'abrechnung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
            'note' => 'Schicht-/Kassenabschluss nur mit dokumentiertem Ergebnis.',
        ],
    ],

    'procedure_templates' => [
        [
            'code' => 'TX_FAHRTAUFTRAG_ANNAHME',
            'name' => 'Fahrtauftrag annehmen',
            'domain' => 'taxi-mietwagen',
            'risk_level' => 'normal',
            'description' => 'Bestellkanal, Abholung, Ziel/Zielfreiheit, Zeitfenster, Fahrgastanforderungen und Betriebsart erfassen.',
            'steps' => [
                ['code' => 'kanal', 'step_type' => 'confirm', 'label' => 'Bestellkanal und Betriebsart festhalten'],
                ['code' => 'abholung', 'step_type' => 'confirm', 'label' => 'Abholort und Zeitfenster erfassen'],
                ['code' => 'anforderungen', 'step_type' => 'confirm', 'label' => 'Fahrgast-/Barrierefreiheitsanforderungen erfassen'],
            ],
        ],
        [
            'code' => 'TX_DISPOSITION',
            'name' => 'Fahrt disponieren',
            'domain' => 'taxi-mietwagen',
            'risk_level' => 'high',
            'description' => 'Fahrer/Fahrzeug mit gültiger Konzession, Fahrgastbeförderungserlaubnis und Gerätestatus zuweisen; Konflikte prüfen.',
            'steps' => [
                ['code' => 'fahrer', 'step_type' => 'confirm', 'label' => 'Fahrerberechtigung prüfen (P-Schein gültig)'],
                ['code' => 'fahrzeug', 'step_type' => 'dienstmittel', 'label' => 'Fahrzeug mit passender Betriebsart zuweisen'],
                ['code' => 'konflikte', 'step_type' => 'confirm', 'label' => 'Zeit-/Reservierungskonflikte prüfen'],
            ],
        ],
        [
            'code' => 'TX_SCHICHTBEGINN',
            'name' => 'Schichtbeginn',
            'domain' => 'taxi-mietwagen',
            'risk_level' => 'normal',
            'description' => 'Fahrzeug-, Geräte-, Sicherheits- und Kassenprüfung vor Schichtstart.',
            'steps' => [
                ['code' => 'fahrzeugPruefen', 'step_type' => 'confirm', 'label' => 'Fahrzeugzustand und Sauberkeit prüfen'],
                ['code' => 'geraete', 'step_type' => 'confirm', 'label' => 'Taxameter/TSE/Kartenleser betriebsbereit'],
                ['code' => 'kasse', 'step_type' => 'confirm', 'label' => 'Wechselgeld/Kassenbestand aufnehmen'],
            ],
        ],
        [
            'code' => 'TX_FAHRTAUFTRAG_ABSCHLUSS',
            'name' => 'Fahrt abschließen',
            'domain' => 'taxi-mietwagen',
            'risk_level' => 'normal',
            'description' => 'Fahrt-, Tarif-, Geräte-, Steuer- und Zahlungsabschluss dokumentieren.',
            'steps' => [
                ['code' => 'geraetewert', 'step_type' => 'confirm', 'label' => 'Taxameter-/Gerätewert übernehmen'],
                ['code' => 'steuer', 'step_type' => 'confirm', 'label' => 'Steuerentscheidung (Betriebsart/50-km-Grenze) prüfen'],
                ['code' => 'zahlung', 'step_type' => 'confirm', 'label' => 'Zahlungsart erfassen'],
            ],
        ],
        [
            'code' => 'TX_MIETWAGEN_RUECKKEHR',
            'name' => 'Mietwagen-Rückkehr/Folgeauftrag',
            'domain' => 'taxi-mietwagen',
            'risk_level' => 'high',
            'description' => 'Rückkehr zum Betriebssitz oder Folgeauftrag nach § 49 Abs. 4 PBefG nachweisbar dokumentieren.',
            'steps' => [
                ['code' => 'rueckkehr', 'step_type' => 'confirm', 'label' => 'Rückkehr oder Folgeauftrag dokumentieren'],
            ],
        ],
        [
            'code' => 'TX_SCHICHTABRECHNUNG',
            'name' => 'Schichtabrechnung',
            'domain' => 'taxi-mietwagen',
            'risk_level' => 'high',
            'description' => 'Geräte-, Bar-, Karten-, Vermittler- und Kassenabgleich; Differenzen bleiben offen, bis sie begründet geklärt sind.',
            'steps' => [
                ['code' => 'geraetesumme', 'step_type' => 'confirm', 'label' => 'Taxameter-/TSE-Summen aufnehmen'],
                ['code' => 'zahlarten', 'step_type' => 'confirm', 'label' => 'Bar/Karte/Gutschein/Rechnung/Vermittler trennen'],
                ['code' => 'differenz', 'step_type' => 'confirm', 'label' => 'Differenz klären oder begründet offen lassen'],
            ],
        ],
        [
            'code' => 'TX_STOERUNG_UNFALL',
            'name' => 'Störung / Unfall',
            'domain' => 'taxi-mietwagen',
            'risk_level' => 'critical',
            'description' => 'Panne, Gerätefehler oder Unfall mit Fahrgastschutz und Eskalation dokumentieren.',
            'steps' => [
                ['code' => 'sichern', 'step_type' => 'confirm', 'label' => 'Fahrgäste sichern, Unfallstelle absichern'],
                ['code' => 'fotos', 'step_type' => 'photo', 'label' => 'Schäden dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'meldung', 'step_type' => 'confirm', 'label' => 'Disposition/Versicherung informieren'],
            ],
        ],
        [
            'code' => 'TX_FUNDGEGENSTAND',
            'name' => 'Fundgegenstand',
            'domain' => 'taxi-mietwagen',
            'risk_level' => 'normal',
            'description' => 'Fundsache datensparsam aufnehmen und nachweisbar übergeben.',
            'steps' => [
                ['code' => 'aufnahme', 'step_type' => 'confirm', 'label' => 'Fundsache und Fundfahrt (ohne Fahrgastdaten) erfassen'],
                ['code' => 'uebergabe', 'step_type' => 'confirm', 'label' => 'Übergabe an Fundbüro/Fahrgast dokumentieren'],
            ],
        ],
    ],

    'qualifications_seed' => [
        [
            'name' => 'Fahrerlaubnis zur Fahrgastbeförderung (P-Schein)',
            'abbreviation' => 'P-Schein',
            'description' => 'Fahrerlaubnis zur Fahrgastbeförderung nach § 48 FeV — befristet, mit Ablaufkontrolle; Voraussetzung der Disposition.',
        ],
        [
            'name' => 'Ortskunde-/Fachkundenachweis Personenbeförderung',
            'abbreviation' => 'Fachkunde PBefG',
            'description' => 'Fachkunde-/Ortskundenachweis gemäß behördlicher Anforderung des Tarifgebiets.',
        ],
    ],

    'tags_seed' => [
        '#taxi', '#mietwagen', '#vorbestellung', '#krankenfahrt', '#rollstuhl',
        '#grossraum', '#nachtfahrt', '#flughafen', '#vermittler', '#barzahlung',
        '#kartenzahlung', '#schichtabrechnung',
    ],
];

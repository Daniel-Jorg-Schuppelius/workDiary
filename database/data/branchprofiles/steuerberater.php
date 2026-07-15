<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : steuerberater.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'steuerberater',
    'label' => 'Steuerberatung',
    'version' => 1,
    // Feature 081 (MVP-373): empfohlener Funktionsumfang — als vorausgewählte
    // Checkliste auf der Seite „Funktionsumfang“, nie still angewendet.
    'modules_recommended' => [
        'module.planung',
        'module.spesen',
        'module.vertrieb',
        'module.documents',
        'module.forms',
        'module.knowledge',
        'module.auswertungen_team',
        'module.kanban',
        'module.chat',
        'module.datenschutz',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'fibu', 'label' => 'Finanzbuchfuehrung'],
            ['code' => 'lohn', 'label' => 'Lohn'],
            ['code' => 'abschluss', 'label' => 'Jahresabschluss'],
            ['code' => 'steuererklaerung', 'label' => 'Steuererklaerung'],
            ['code' => 'voranmeldung', 'label' => 'Voranmeldung'],
            ['code' => 'beratung', 'label' => 'Beratung'],
            ['code' => 'fristensache', 'label' => 'Fristensache'],
            ['code' => 'kommunikation', 'label' => 'Kommunikation'],
            ['code' => 'pruefung', 'label' => 'Pruefung'],
        ],
        'activity' => [
            ['code' => 'belegerfassung', 'label' => 'Belegerfassung'],
            ['code' => 'kontieren', 'label' => 'Kontieren'],
            ['code' => 'abstimmen', 'label' => 'Abstimmen'],
            ['code' => 'erstellen', 'label' => 'Erstellen'],
            ['code' => 'pruefen', 'label' => 'Pruefen'],
            ['code' => 'einreichen', 'label' => 'Einreichen'],
            ['code' => 'beraten', 'label' => 'Beraten'],
            ['code' => 'korrespondenz', 'label' => 'Korrespondenz'],
            ['code' => 'archivieren', 'label' => 'Archivieren'],
        ],
        'defect_type' => [
            ['code' => 'belegFehlt', 'label' => 'Beleg fehlt'],
            ['code' => 'belegUnleserlich', 'label' => 'Beleg unleserlich'],
            ['code' => 'kontierungUnklar', 'label' => 'Kontierung unklar'],
            ['code' => 'mandantNichtErreichbar', 'label' => 'Mandant nicht erreichbar'],
            ['code' => 'fristKritisch', 'label' => 'Frist kritisch'],
            ['code' => 'datenInkonsistent', 'label' => 'Daten inkonsistent'],
        ],
        'root_cause' => [
            ['code' => 'mandantSpaet', 'label' => 'Mandant spaet'],
            ['code' => 'datenLuecke', 'label' => 'Datenluecke'],
            ['code' => 'behoerdenanfrage', 'label' => 'Behoerdenanfrage'],
            ['code' => 'gesetzAenderung', 'label' => 'Gesetzesaenderung'],
            ['code' => 'internerFehler', 'label' => 'Interner Fehler'],
            ['code' => 'systemausfall', 'label' => 'Systemausfall'],
        ],
        'result' => [
            ['code' => 'eingereicht', 'label' => 'Eingereicht'],
            ['code' => 'freigegebenDurchMandant', 'label' => 'Freigegeben durch Mandant'],
            ['code' => 'ruecklaufBehoerde', 'label' => 'Ruecklauf Behoerde'],
            ['code' => 'vertagt', 'label' => 'Vertagt'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
            ['code' => 'archiviert', 'label' => 'Archiviert'],
        ],
        'product_group' => [
            ['code' => 'einkommensteuer', 'label' => 'Einkommensteuer'],
            ['code' => 'koerperschaftsteuer', 'label' => 'Koerperschaftsteuer'],
            ['code' => 'gewerbesteuer', 'label' => 'Gewerbesteuer'],
            ['code' => 'umsatzsteuer', 'label' => 'Umsatzsteuer'],
            ['code' => 'lohnsteuer', 'label' => 'Lohnsteuer'],
            ['code' => 'sozialversicherung', 'label' => 'Sozialversicherung'],
            ['code' => 'jahresabschluss', 'label' => 'Jahresabschluss'],
            ['code' => 'fibu', 'label' => 'Fibu'],
            ['code' => 'lohn', 'label' => 'Lohn'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'steuererklaerung',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'steuererklaerung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'voranmeldung',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'voranmeldung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'abschluss',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'abschluss',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'fibu',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'lohn',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'fristensache',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'kommunikation',
            'required_domain' => 'activity',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'beratung',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        [
            'code' => 'STB_USTVA',
            'name' => 'Umsatzsteuer-Voranmeldung',
            'domain' => 'steuerberater',
            'risk_level' => 'high',
            'description' => 'USt-Voranmeldung mit Vier-Augen-Kontrolle und ELSTER-Übermittlung.',
            'steps' => [
                ['code' => 'belege', 'step_type' => 'confirm', 'label' => 'Belege/Vollständigkeit prüfen'],
                ['code' => 'kontierung', 'step_type' => 'confirm', 'label' => 'Kontierung prüfen'],
                ['code' => 'erstellen', 'step_type' => 'confirm', 'label' => 'Voranmeldung erstellen'],
                ['code' => 'vierAugen', 'step_type' => 'confirm', 'label' => 'Vier-Augen-Kontrolle', 'requires_second_person' => true],
                ['code' => 'elster', 'step_type' => 'confirm', 'label' => 'Per ELSTER übermitteln'],
                ['code' => 'nachweis', 'step_type' => 'file', 'label' => 'Übertragungsprotokoll ablegen', 'requires_proof_type' => 'file'],
            ],
        ],
        [
            'code' => 'STB_FRISTEN_REVIEW',
            'name' => 'Fristenkontrolle',
            'domain' => 'steuerberater',
            'risk_level' => 'normal',
            'description' => 'Periodische Kontrolle laufender Fristen und Eskalation kritischer Termine.',
            'steps' => [
                ['code' => 'liste', 'step_type' => 'confirm', 'label' => 'Fristenliste durchgehen'],
                ['code' => 'kritisch', 'step_type' => 'choice', 'label' => 'Kritische Fristen markieren'],
                ['code' => 'info', 'step_type' => 'confirm', 'label' => 'Mandanten/Team informieren', 'required' => false, 'blocking' => false],
            ],
        ],
        [
            'code' => 'STB_GELDWAESCHE_CHECK',
            'name' => 'Geldwäsche-Prüfung (GwG)',
            'domain' => 'steuerberater',
            'risk_level' => 'high',
            'description' => 'Identifizierung und Risikoeinstufung nach GwG bei Mandatsannahme.',
            'steps' => [
                ['code' => 'identitaet', 'step_type' => 'confirm', 'label' => 'Identität feststellen (GwG §10)'],
                ['code' => 'risiko', 'step_type' => 'choice', 'label' => 'Risikoeinstufung vornehmen'],
                ['code' => 'wB', 'step_type' => 'confirm', 'label' => 'Wirtschaftlich Berechtigten ermitteln'],
                ['code' => 'doku', 'step_type' => 'file', 'label' => 'Nachweise ablegen', 'requires_proof_type' => 'file'],
            ],
        ],
        ['code' => 'STB_FIBU_MONAT'],
        ['code' => 'STB_LOHN_MONAT'],
        ['code' => 'STB_LSTAN'],
        ['code' => 'STB_JAHRESABSCHLUSS'],
        ['code' => 'STB_STEUERERKLAERUNG'],
        ['code' => 'STB_MANDANTENANNAHME'],
        ['code' => 'STB_BETRIEBSPRUEFUNG'],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'STB_BERATUNGSPROTOKOLL'],
        ['code' => 'STB_TELEFONNOTIZ'],
        ['code' => 'STB_FRISTENVERLAENGERUNG'],
        ['code' => 'STB_FREIGABE_MANDANT'],
        ['code' => 'STB_PRUEFUNGSVERMERK'],
        ['code' => 'STB_GELDWAESCHE_VERMERK'],
    ],
    'asset_categories' => [
        'workstation',
        'notebook',
        'monitor',
        'phone',
        'printer',
        'scanner',
        'signaturePad',
        'cardReader',
        'softwareLicense',
        'datevAccount',
    ],
    'tags_seed' => [
        '#frist-kritisch',
        '#mandantenrueckfrage',
        '#datev-import',
        '#elster',
        '#vier-augen',
        '#geldwaesche',
        '#betriebspruefung',
        '#quartalsende',
    ],
];

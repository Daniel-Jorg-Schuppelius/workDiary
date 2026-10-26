<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : vermieter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

// Vermieter: Dauer- und Ferienvermietung über das Verleihmodul (Wohnung = Mietprofil eines Assets mit Vermietungsart).
// Abläufe, Protokoll und Vertragspflichten sind Arbeitshilfen; Fristen und Inhalte legt der Vermieter fest.
return [
    'code' => 'vermieter',
    'label' => 'Vermieter / Wohnungen und Ferienwohnungen',
    'description' => 'Dauer- und Ferienvermietung: Wohnungsbestand mit Belegungsübersicht, Mietverhältnisse, Buchungsanfragen im Kundenportal, Übergabe und Abnahme mit Protokoll sowie Mietvertragsvorlagen mit Fristen.',
    'version' => 1,
    'modules_recommended' => [
        'module.rental',
        'module.contracts',
        'module.documents',
        'module.vertrieb',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'wohnungsuebergabe', 'label' => 'Wohnungsübergabe'],
            ['code' => 'wohnungsabnahme', 'label' => 'Wohnungsabnahme'],
            ['code' => 'gaestewechsel', 'label' => 'Gästewechsel'],
            ['code' => 'endreinigung', 'label' => 'Endreinigung'],
            ['code' => 'besichtigung', 'label' => 'Besichtigung'],
            ['code' => 'schadensaufnahme', 'label' => 'Schadensaufnahme'],
            ['code' => 'zaehlerablesung', 'label' => 'Zählerablesung'],
            ['code' => 'wohnungsinstandhaltung', 'label' => 'Wohnungsinstandhaltung'],
        ],
        'activity' => [
            ['code' => 'uebergeben', 'label' => 'Übergeben'],
            ['code' => 'abnehmen', 'label' => 'Abnehmen'],
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'ablesen', 'label' => 'Ablesen'],
            ['code' => 'besichtigen', 'label' => 'Besichtigen'],
            ['code' => 'reparieren', 'label' => 'Reparieren'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'wasserschaden', 'label' => 'Wasserschaden'],
            ['code' => 'schimmel', 'label' => 'Feuchtigkeit / Schimmel'],
            ['code' => 'heizung', 'label' => 'Heizung / Warmwasser'],
            ['code' => 'sanitaer', 'label' => 'Sanitär'],
            ['code' => 'elektrik', 'label' => 'Elektrik'],
            ['code' => 'schloss', 'label' => 'Schloss / Schlüssel'],
            ['code' => 'inventar', 'label' => 'Inventar beschädigt oder fehlt'],
        ],
        'root_cause' => [
            ['code' => 'verschleiss', 'label' => 'Verschleiß'],
            ['code' => 'nutzung', 'label' => 'Unsachgemäße Nutzung'],
            ['code' => 'witterung', 'label' => 'Witterung'],
            ['code' => 'bausubstanz', 'label' => 'Bausubstanz'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'handwerker', 'label' => 'Handwerker beauftragt'],
            ['code' => 'mieterInformiert', 'label' => 'Mieter informiert'],
            ['code' => 'kaution', 'label' => 'Mit der Kaution abzurechnen'],
            ['code' => 'offen', 'label' => 'Offen'],
        ],
    ],
    // Eine Abnahme ohne Ergebnis lässt offen, ob mit der Kaution abzurechnen ist.
    'classification_requirements' => [
        ['entry_type_code' => 'wohnungsabnahme', 'required_domain' => 'result', 'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value, 'severity' => ClassificationRequirementSeverity::Soft->value, 'min_count' => 1],
        ['entry_type_code' => 'schadensaufnahme', 'required_domain' => 'defect_type', 'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value, 'severity' => ClassificationRequirementSeverity::Soft->value, 'min_count' => 1],
    ],
    'procedure_templates' => [
        [
            'code' => 'VM_WOHNUNGSUEBERGABE',
            'name' => 'Wohnungsübergabe',
            'name_i18n' => ['en' => 'Apartment handover', 'es' => 'Entrega de vivienda', 'fr' => 'Remise du logement', 'it' => 'Consegna appartamento'],
            'domain' => 'vermieter',
            'risk_level' => 'normal',
            'description' => 'Einzug: Zustand, Zählerstände und Schlüssel gemeinsam mit dem Mieter festhalten.',
            'steps' => [
                ['code' => 'zustand', 'step_type' => 'confirm', 'label' => 'Zustand und Ausstattung gemeinsam prüfen'],
                ['code' => 'zaehler', 'step_type' => 'text', 'label' => 'Zählerstände festhalten'],
                ['code' => 'schluessel', 'step_type' => 'number', 'label' => 'Anzahl übergebener Schlüssel'],
                ['code' => 'maengel', 'step_type' => 'text', 'label' => 'Mängel dokumentieren', 'required' => false, 'blocking' => false],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Mängel fotografieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'unterschrift', 'step_type' => 'signature', 'label' => 'Unterschrift Mieter'],
            ],
        ],
        [
            'code' => 'VM_WOHNUNGSABNAHME',
            'name' => 'Wohnungsabnahme',
            'name_i18n' => ['en' => 'Move-out inspection', 'es' => 'Inspección de salida', 'fr' => 'État des lieux de sortie', 'it' => 'Verifica di riconsegna'],
            'domain' => 'vermieter',
            'risk_level' => 'normal',
            'description' => 'Auszug: Zustand mit der Übergabe vergleichen, Schäden belegen, Zähler und Schlüssel zurücknehmen.',
            'steps' => [
                ['code' => 'vergleich', 'step_type' => 'confirm', 'label' => 'Zustand mit dem Übergabeprotokoll vergleichen'],
                ['code' => 'schaeden', 'step_type' => 'text', 'label' => 'Schäden über die übliche Abnutzung hinaus festhalten', 'required' => false, 'blocking' => false],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Schäden fotografieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'zaehler', 'step_type' => 'text', 'label' => 'Zählerstände festhalten'],
                ['code' => 'schluessel', 'step_type' => 'number', 'label' => 'Anzahl zurückgegebener Schlüssel'],
                ['code' => 'kaution', 'step_type' => 'text', 'label' => 'Einbehalt aus der Kaution notieren', 'required' => false, 'blocking' => false],
                ['code' => 'unterschrift', 'step_type' => 'signature', 'label' => 'Unterschrift Mieter'],
            ],
        ],
        [
            'code' => 'VM_GAESTEWECHSEL',
            'name' => 'Gästewechsel Ferienwohnung',
            'name_i18n' => ['en' => 'Holiday apartment changeover', 'es' => 'Cambio de huéspedes', 'fr' => 'Changement de locataires', 'it' => 'Cambio ospiti'],
            'domain' => 'vermieter',
            'risk_level' => 'normal',
            'description' => 'Zwischen Abreise und Anreise: reinigen, Wäsche wechseln, Inventar prüfen und Schäden melden.',
            'steps' => [
                ['code' => 'reinigung', 'step_type' => 'confirm', 'label' => 'Endreinigung erledigt'],
                ['code' => 'waesche', 'step_type' => 'confirm', 'label' => 'Bettwäsche und Handtücher gewechselt'],
                ['code' => 'inventar', 'step_type' => 'confirm', 'label' => 'Inventar vollständig und unbeschädigt'],
                ['code' => 'schaeden', 'step_type' => 'text', 'label' => 'Schäden oder Fehlbestand melden', 'required' => false, 'blocking' => false],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Wohnung nach der Reinigung fotografieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'schluessel', 'step_type' => 'confirm', 'label' => 'Schlüssel oder Zugangscode für die nächsten Gäste bereit'],
            ],
        ],
    ],
    'protocol_templates' => [
        ['code' => 'VM_WOHNUNGSUEBERGABE'],
        ['code' => 'FM_SCHLUESSELNACHWEIS'],
        ['code' => 'FM_ZAEHLERABLESUNG'],
    ],
    // Eigene Felder der Wohnung (MVP-868): ergänzen nur fehlende, eigene Felder bleiben.
    'custom_fields' => [
        'assets' => [
            ['label' => 'Wohnfläche', 'type' => 'number', 'unit' => 'm²', 'min' => 1, 'max' => 2000],
            ['label' => 'Zimmer', 'type' => 'number', 'min' => 1, 'max' => 30],
            ['label' => 'Etage', 'type' => 'text'],
            ['label' => 'Haustiere erlaubt', 'type' => 'boolean'],
            ['label' => 'Energieausweis gültig bis', 'type' => 'date'],
        ],
    ],
    // Vertragsvorlagen (MVP-893): Laufzeit, Kündigung, Pflichten relativ zum Mietbeginn.
    'contract_templates' => [
        [
            'name' => 'Wohnraummietvertrag',
            'kind' => 'rent',
            'title' => 'Mietvertrag Wohnraum',
            'term_kind' => 'open_ended',
            'notice_period_days' => 90,
            'value_period' => 'monthly',
            'indexation_method' => 'none',
            'obligations' => [
                ['kind' => 'documentation', 'title' => 'Kaution getrennt vom eigenen Vermögen anlegen', 'offset_months' => 0],
                ['kind' => 'documentation', 'title' => 'Betriebskostenabrechnung zustellen', 'offset_months' => 12, 'recurring' => true, 'recurrence_months' => 12],
                ['kind' => 'review', 'title' => 'Rauchwarnmelder prüfen', 'offset_months' => 12, 'recurring' => true, 'recurrence_months' => 12],
            ],
        ],
        [
            'name' => 'Wohnraummietvertrag mit Indexmiete',
            'kind' => 'rent',
            'title' => 'Mietvertrag Wohnraum (Indexmiete)',
            'term_kind' => 'open_ended',
            'notice_period_days' => 90,
            'value_period' => 'monthly',
            'indexation_method' => 'cpi',
            'obligations' => [
                ['kind' => 'documentation', 'title' => 'Kaution getrennt vom eigenen Vermögen anlegen', 'offset_months' => 0],
                ['kind' => 'documentation', 'title' => 'Betriebskostenabrechnung zustellen', 'offset_months' => 12, 'recurring' => true, 'recurrence_months' => 12],
                ['kind' => 'indexation', 'title' => 'Indexanpassung prüfen', 'offset_months' => 12, 'recurring' => true, 'recurrence_months' => 12],
                ['kind' => 'review', 'title' => 'Rauchwarnmelder prüfen', 'offset_months' => 12, 'recurring' => true, 'recurrence_months' => 12],
            ],
        ],
        [
            'name' => 'Mietbedingungen Ferienwohnung',
            'kind' => 'rental_terms',
            'title' => 'Mietbedingungen Ferienwohnung',
            'term_kind' => 'open_ended',
            'value_period' => 'once',
            'indexation_method' => 'none',
            'obligations' => [
                ['kind' => 'review', 'title' => 'Mietbedingungen und Preise für die Saison prüfen', 'offset_months' => 12, 'recurring' => true, 'recurrence_months' => 12],
            ],
        ],
    ],
    'tags_seed' => [
        '#vermietung',
        '#dauervermietung',
        '#ferienwohnung',
        '#wohnungsuebergabe',
        '#auszug',
        '#gaestewechsel',
        '#schaden',
    ],
];

<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : galabau.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'galabau',
    'label' => 'Garten- und Landschaftsbau',
    'version' => 1,
    'classifications' => [
        'entry_type' => [
            ['code' => 'pflegegang', 'label' => 'Pflegegang'],
            ['code' => 'neuanlage', 'label' => 'Neuanlage'],
            ['code' => 'pflanzung', 'label' => 'Pflanzung'],
            ['code' => 'erdarbeit', 'label' => 'Erdarbeit'],
            ['code' => 'pflaster', 'label' => 'Pflasterarbeit'],
            ['code' => 'baumpflege', 'label' => 'Baumpflege'],
            ['code' => 'bewaesserung', 'label' => 'Bewässerung'],
            ['code' => 'winterdienst', 'label' => 'Winterdienst'],
            ['code' => 'abnahme', 'label' => 'Abnahme'],
        ],
        'activity' => [
            ['code' => 'maehen', 'label' => 'Mähen'],
            ['code' => 'schneiden', 'label' => 'Schneiden'],
            ['code' => 'pflanzen', 'label' => 'Pflanzen'],
            ['code' => 'giessen', 'label' => 'Gießen'],
            ['code' => 'duengen', 'label' => 'Düngen'],
            ['code' => 'roden', 'label' => 'Roden'],
            ['code' => 'baggern', 'label' => 'Baggern'],
            ['code' => 'pflastern', 'label' => 'Pflastern'],
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'ausfallPflanze', 'label' => 'Pflanzenausfall'],
            ['code' => 'trockenheit', 'label' => 'Trockenheit'],
            ['code' => 'schaedling', 'label' => 'Schädlingsbefall'],
            ['code' => 'setzung', 'label' => 'Setzung'],
            ['code' => 'frostschaden', 'label' => 'Frostschaden'],
            ['code' => 'geraeteschaden', 'label' => 'Geräteschaden'],
            ['code' => 'zugangFehlt', 'label' => 'Zugang fehlt'],
        ],
        'root_cause' => [
            ['code' => 'wetter', 'label' => 'Wetter'],
            ['code' => 'pflegefehler', 'label' => 'Pflegefehler'],
            ['code' => 'material', 'label' => 'Material'],
            ['code' => 'standort', 'label' => 'Standort'],
            ['code' => 'fremdeinwirkung', 'label' => 'Fremdeinwirkung'],
            ['code' => 'kundenaenderung', 'label' => 'Kundenänderung'],
            ['code' => 'lieferverzug', 'label' => 'Lieferverzug'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'teilErledigt', 'label' => 'Teilweise erledigt'],
            ['code' => 'nacharbeit', 'label' => 'Nacharbeit'],
            ['code' => 'saisonbedingtOffen', 'label' => 'Saisonbedingt offen'],
            ['code' => 'kundeInformiert', 'label' => 'Kunde informiert'],
            ['code' => 'abgenommen', 'label' => 'Abgenommen'],
        ],
        'product_group' => [
            ['code' => 'rasen', 'label' => 'Rasen'],
            ['code' => 'hecke', 'label' => 'Hecke'],
            ['code' => 'baum', 'label' => 'Baum'],
            ['code' => 'beet', 'label' => 'Beet'],
            ['code' => 'pflaster', 'label' => 'Pflaster'],
            ['code' => 'zaun', 'label' => 'Zaun'],
            ['code' => 'bewaesserung', 'label' => 'Bewässerung'],
            ['code' => 'teich', 'label' => 'Teich'],
            ['code' => 'aussenanlage', 'label' => 'Außenanlage'],
            ['code' => 'winterdienst', 'label' => 'Winterdienst'],
        ],
        // Gewerke / Nachunternehmer-Kategorien (Auswahl je Eintrag; konkreter
        // Betrieb liegt im Lieferanten-/Nachunternehmer-Stamm).
        'trade' => [
            ['code' => 'erdbau', 'label' => 'Erdbau'],
            ['code' => 'pflasterbau', 'label' => 'Pflasterbau'],
            ['code' => 'pflanzung', 'label' => 'Pflanzung / Begrünung'],
            ['code' => 'baumpflege', 'label' => 'Baumpflege'],
            ['code' => 'bewaesserung', 'label' => 'Bewässerung'],
            ['code' => 'zaunbau', 'label' => 'Zaunbau'],
            ['code' => 'teichbau', 'label' => 'Teich- / Wasserbau'],
            ['code' => 'holzbau', 'label' => 'Holzbau'],
        ],
        // Genehmigungsarten (Auswahl/Filter je Eintrag). Status, Frist und
        // Nachweis werden im Genehmigungs-Register (Permit) geführt.
        'permit_type' => [
            ['code' => 'baumfaellung', 'label' => 'Baumfällgenehmigung'],
            ['code' => 'wasserrecht', 'label' => 'Wasserrechtliche Erlaubnis'],
            ['code' => 'sondernutzung', 'label' => 'Sondernutzung öffentl. Raum'],
            ['code' => 'naturschutz', 'label' => 'Naturschutzrechtliche Genehmigung'],
            ['code' => 'entsorgungsnachweis', 'label' => 'Entsorgungsnachweis'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'baumpflege',
            'required_domain' => 'permit_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'allow_multi' => true,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'pflegegang',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'pflegegang',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'pflanzung',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'baumpflege',
            'required_domain' => 'activity',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'winterdienst',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'winterdienst',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'abnahme',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        [
            'code' => 'GL_BAUMPFLEGE',
            'name' => 'Baumpflege / Verkehrssicherung',
            'domain' => 'galabau',
            'risk_level' => 'high',
            'description' => 'Baumarbeiten mit Gefährdungsbeurteilung und Absicherung.',
            'steps' => [
                ['code' => 'gefaehrdung', 'step_type' => 'confirm', 'label' => 'Gefährdungsbeurteilung durchführen'],
                ['code' => 'absicherung', 'step_type' => 'confirm', 'label' => 'Arbeitsbereich absichern', 'requires_second_person' => true],
                ['code' => 'psa', 'step_type' => 'confirm', 'label' => 'PSA gegen Absturz prüfen'],
                ['code' => 'durchfuehrung', 'step_type' => 'confirm', 'label' => 'Arbeiten durchführen'],
                ['code' => 'nachweis', 'step_type' => 'photo', 'label' => 'Foto-/Pflegenachweis', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
            ],
        ],
        [
            'code' => 'GL_ABNAHME',
            'name' => 'Abnahme Außenanlage',
            'domain' => 'galabau',
            'risk_level' => 'normal',
            'description' => 'Abnahme mit Restpunkten und Kundenunterschrift.',
            'steps' => [
                ['code' => 'sichtung', 'step_type' => 'confirm', 'label' => 'Leistung gemeinsam sichten'],
                ['code' => 'restpunkte', 'step_type' => 'text', 'label' => 'Restpunkte erfassen', 'required' => false, 'blocking' => false],
                ['code' => 'ergebnis', 'step_type' => 'choice', 'label' => 'Abnahmeergebnis festhalten'],
                ['code' => 'unterschrift', 'step_type' => 'signature', 'label' => 'Kundenunterschrift', 'requires_proof_type' => 'signature'],
            ],
        ],
        ['code' => 'GL_PFLEGEGANG'],
        ['code' => 'GL_PFLANZUNG'],
        ['code' => 'GL_NEUANLAGE'],
        ['code' => 'GL_PFLASTER'],
        ['code' => 'GL_WINTERDIENST'],
    ],
    'maintenance_plans_seed' => [
        ['code' => 'GB-BAUMKONTROLLE-12M', 'label' => 'Baumkontrolle (Verkehrssicherungspflicht)', 'category_code' => 'baum', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 30],
        ['code' => 'GB-SPIELPLATZ-12M', 'label' => 'Spielplatzprüfung (DIN EN 1176, Jahreshauptinspektion)', 'category_code' => 'spielplatz', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 30],
        ['code' => 'GB-BEWAESSERUNG-12M', 'label' => 'Bewässerungsanlage Saisonwartung', 'category_code' => 'bewaesserung', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
        ['code' => 'GB-MASCHINE-12M', 'label' => 'Maschinenwartung (Mäher/Geräte)', 'category_code' => 'maschine', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
    ],
    'room_requirement_templates_seed' => [
        ['code' => 'gl_pflegeintervall', 'kind' => 'operatorDuty', 'label' => 'Pflegeintervall Außenanlage', 'note' => 'Grünfläche/Außenbereich mit Pflegeintervall führen.'],
        ['code' => 'gl_verkehrssicherung', 'kind' => 'technicalInspection', 'label' => 'Baumkontrolle / Verkehrssicherung', 'level' => 'jährlich'],
        ['code' => 'gl_winterdienst', 'kind' => 'other', 'label' => 'Winterdienstpflicht', 'note' => 'Fläche im Winterdienstplan berücksichtigen.'],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'GL_PFLEGENACHWEIS'],
        ['code' => 'GL_PFLANZPROTOKOLL'],
        ['code' => 'GL_BAUTAGESBERICHT'],
        ['code' => 'GL_WINTERDIENSTNACHWEIS'],
        ['code' => 'GL_MAENGEL'],
        ['code' => 'GL_ABNAHME'],
    ],
    'asset_categories' => [
        'transporter',
        'anhaenger',
        'rasenmaeher',
        'freischneider',
        'heckenschere',
        'minibagger',
        'ruettelplatte',
        'kettensaege',
        'streuwagen',
    ],
    'tags_seed' => [
        '#pflege',
        '#saison',
        '#pflanzung',
        '#winterdienst',
        '#wetter',
        '#abnahme',
        '#nacharbeit',
    ],
];

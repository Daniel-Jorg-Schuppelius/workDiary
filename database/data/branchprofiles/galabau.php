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
    ],
    'classification_requirements' => [
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
        ['code' => 'GL_PFLEGEGANG'],
        ['code' => 'GL_PFLANZUNG'],
        ['code' => 'GL_NEUANLAGE'],
        ['code' => 'GL_PFLASTER'],
        ['code' => 'GL_BAUMPFLEGE'],
        ['code' => 'GL_WINTERDIENST'],
        ['code' => 'GL_ABNAHME'],
    ],
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

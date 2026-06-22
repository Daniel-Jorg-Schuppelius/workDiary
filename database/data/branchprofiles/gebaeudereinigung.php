<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gebaeudereinigung.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'gebaeudereinigung',
    'label' => 'Gebäudereinigung',
    'version' => 1,
    'classifications' => [
        'entry_type' => [
            ['code' => 'unterhaltsreinigung', 'label' => 'Unterhaltsreinigung'],
            ['code' => 'grundreinigung', 'label' => 'Grundreinigung'],
            ['code' => 'glasreinigung', 'label' => 'Glasreinigung'],
            ['code' => 'sonderreinigung', 'label' => 'Sonderreinigung'],
            ['code' => 'qualitaetskontrolle', 'label' => 'Qualitätskontrolle'],
            ['code' => 'reklamation', 'label' => 'Reklamation'],
            ['code' => 'begehung', 'label' => 'Begehung'],
        ],
        'activity' => [
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'desinfizieren', 'label' => 'Desinfizieren'],
            ['code' => 'saugen', 'label' => 'Saugen'],
            ['code' => 'wischen', 'label' => 'Wischen'],
            ['code' => 'polieren', 'label' => 'Polieren'],
            ['code' => 'auffuellen', 'label' => 'Auffüllen'],
            ['code' => 'entsorgen', 'label' => 'Entsorgen'],
            ['code' => 'kontrollieren', 'label' => 'Kontrollieren'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'nichtGereinigt', 'label' => 'Nicht gereinigt'],
            ['code' => 'materialFehlt', 'label' => 'Material fehlt'],
            ['code' => 'zugangFehlt', 'label' => 'Zugang fehlt'],
            ['code' => 'qualitaetsmangel', 'label' => 'Qualitätsmangel'],
            ['code' => 'schaden', 'label' => 'Schaden'],
            ['code' => 'hygienemangel', 'label' => 'Hygienemangel'],
            ['code' => 'kundenbeschwerde', 'label' => 'Kundenbeschwerde'],
        ],
        'root_cause' => [
            ['code' => 'personalEngpass', 'label' => 'Personalengpass'],
            ['code' => 'zugang', 'label' => 'Zugang'],
            ['code' => 'material', 'label' => 'Material'],
            ['code' => 'fremdverschmutzung', 'label' => 'Fremdverschmutzung'],
            ['code' => 'planungsfehler', 'label' => 'Planungsfehler'],
            ['code' => 'nacharbeitNoetig', 'label' => 'Nacharbeit nötig'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'teilErledigt', 'label' => 'Teilweise erledigt'],
            ['code' => 'nacharbeit', 'label' => 'Nacharbeit'],
            ['code' => 'nichtMoeglich', 'label' => 'Nicht möglich'],
            ['code' => 'kundeInformiert', 'label' => 'Kunde informiert'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'buero', 'label' => 'Büro'],
            ['code' => 'sanitaer', 'label' => 'Sanitär'],
            ['code' => 'treppenhaus', 'label' => 'Treppenhaus'],
            ['code' => 'glas', 'label' => 'Glas'],
            ['code' => 'boden', 'label' => 'Boden'],
            ['code' => 'kueche', 'label' => 'Küche'],
            ['code' => 'industrie', 'label' => 'Industrie'],
            ['code' => 'medizinisch', 'label' => 'Medizinisch'],
            ['code' => 'aussenbereich', 'label' => 'Außenbereich'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'unterhaltsreinigung',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'unterhaltsreinigung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'qualitaetskontrolle',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'qualitaetskontrolle',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'reklamation',
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
            'entry_type_code' => 'sonderreinigung',
            'required_domain' => 'activity',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'sonderreinigung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        [
            'code' => 'GR_QS_KONTROLLE',
            'name' => 'Qualitätskontrolle Reinigung',
            'domain' => 'gebaeudereinigung',
            'risk_level' => 'normal',
            'description' => 'Objektbegehung mit Bewertung und Foto-/Abnahmenachweis.',
            'steps' => [
                ['code' => 'bereichWaehlen', 'step_type' => 'choice', 'label' => 'Bereich/Raumgruppe wählen'],
                ['code' => 'bewertung', 'step_type' => 'choice', 'label' => 'Reinigungsqualität bewerten'],
                ['code' => 'maengelFoto', 'step_type' => 'photo', 'label' => 'Mängel fotografieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'nacharbeit', 'step_type' => 'confirm', 'label' => 'Nacharbeit veranlassen (falls nötig)', 'required' => false, 'blocking' => false],
                ['code' => 'abnahme', 'step_type' => 'signature', 'label' => 'Abnahme dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'GR_SONDERREINIGUNG',
            'name' => 'Sonder-/Hygienereinigung',
            'domain' => 'gebaeudereinigung',
            'risk_level' => 'normal',
            'description' => 'Sonderreinigung mit PSA-/Hygienehinweis und Materialnachweis.',
            'steps' => [
                ['code' => 'gefaehrdung', 'step_type' => 'confirm', 'label' => 'Gefährdung/Hygienehinweis beachten'],
                ['code' => 'psa', 'step_type' => 'confirm', 'label' => 'PSA bereitstellen/anlegen'],
                ['code' => 'material', 'step_type' => 'material', 'label' => 'Reinigungs-/Desinfektionsmittel erfassen', 'required' => false, 'blocking' => false],
                ['code' => 'durchfuehrung', 'step_type' => 'confirm', 'label' => 'Reinigung durchführen'],
                ['code' => 'nachweis', 'step_type' => 'photo', 'label' => 'Foto-/Reinigungsnachweis', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
            ],
        ],
        ['code' => 'GR_UNTERHALT'],
        ['code' => 'GR_GRUNDREINIGUNG'],
        ['code' => 'GR_GLAS'],
        ['code' => 'GR_REKLAMATION'],
    ],
    'room_requirement_templates_seed' => [
        ['code' => 'gr_hygiene', 'kind' => 'hygieneLevel', 'label' => 'Hygienestufe', 'level' => 'standard', 'note' => 'Geforderte Hygienestufe für den Bereich.'],
        ['code' => 'gr_sonderreinigung', 'kind' => 'specialCleaning', 'label' => 'Sonderreinigung erforderlich', 'note' => 'Bereich benötigt Sonder-/Tiefenreinigung.'],
        ['code' => 'gr_zutritt', 'kind' => 'accessRestriction', 'label' => 'Zugangsbeschränkter Bereich', 'note' => 'Schlüssel/Begleitung nötig.'],
        ['code' => 'gr_fotopflicht', 'kind' => 'other', 'label' => 'Foto-/Abnahmepflicht', 'note' => 'Reinigung mit Foto- oder Abnahmenachweis dokumentieren.'],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'GR_REINIGUNGSNACHWEIS'],
        ['code' => 'GR_OBJEKTPLAN'],
        ['code' => 'GR_QS_PROTOKOLL'],
        ['code' => 'GR_REKLAMATIONSBERICHT'],
        ['code' => 'GR_MATERIALVERBRAUCH'],
        ['code' => 'GR_ABNAHME'],
    ],
    'asset_categories' => [
        'reinigungswagen',
        'einscheibenmaschine',
        'nasssauger',
        'glasreinigungsset',
        'leiter',
        'dosieranlage',
        'psaReinigung',
    ],
    'tags_seed' => [
        '#unterhalt',
        '#sonderreinigung',
        '#glas',
        '#sanitaer',
        '#nacharbeit',
        '#reklamation',
        '#hygiene',
    ],
    'cleaning_profiles_seed' => [
        [
            'code' => 'standard',
            'label' => 'Standard-Unterhaltsreinigung',
            'interval_days' => 7,
            'requirements' => [
                'glass' => false,
                'disinfection' => false,
                'hygiene_protocol' => false,
                'ppe' => false,
                'footwear_change' => false,
                'gowning' => false,
            ],
        ],
        [
            'code' => 'glass_only',
            'label' => 'Glasreinigung',
            'interval_days' => 90,
            'requirements' => [
                'glass' => true,
                'disinfection' => false,
                'hygiene_protocol' => false,
                'ppe' => true,
                'footwear_change' => false,
                'gowning' => false,
            ],
        ],
        [
            'code' => 'disinfection',
            'label' => 'Desinfektionsreinigung',
            'interval_days' => 1,
            'requirements' => [
                'glass' => false,
                'disinfection' => true,
                'hygiene_protocol' => true,
                'ppe' => true,
                'footwear_change' => false,
                'gowning' => false,
            ],
        ],
        [
            'code' => 'cleanroom',
            'label' => 'Reinraumreinigung',
            'interval_days' => 1,
            'requirements' => [
                'glass' => false,
                'disinfection' => true,
                'hygiene_protocol' => true,
                'ppe' => true,
                'footwear_change' => true,
                'gowning' => true,
            ],
        ],
    ],
];

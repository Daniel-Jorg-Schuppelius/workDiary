<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : partyservice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

/*
 * Branchenprofil Partyservice / Catering.
 *
 * Schwerpunkt: Auftragsabwicklung vom Angebot bis zur Rücknahme, mit harten
 * Pflichtgates für Lebensmittelsicherheit. Branchenspezifika ohne eigene
 * Klassifikations-Domäne (Allergenkennzeichnung nach LMIV, HACCP-/Kühlketten-
 * nachweis) werden über die Prozedurvorlagen erzwungen, nicht über Domänen –
 * die Kern-Domänen sind durch ClassificationDomain fest umrissen.
 */
return [
    'code' => 'partyservice',
    'label' => 'Partyservice / Catering',
    'version' => 2,
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
        'module.fuhrpark',
        'module.lager',
        'module.rental',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'anfrage', 'label' => 'Anfrage'],
            ['code' => 'angebot', 'label' => 'Angebot'],
            ['code' => 'menueplanung', 'label' => 'Menüplanung'],
            ['code' => 'einkauf', 'label' => 'Einkauf'],
            ['code' => 'vorbereitung', 'label' => 'Vorbereitung / Mise en place'],
            ['code' => 'anlieferung', 'label' => 'Anlieferung'],
            ['code' => 'aufbau', 'label' => 'Buffet-Aufbau'],
            ['code' => 'service', 'label' => 'Service vor Ort'],
            ['code' => 'abbau', 'label' => 'Abbau'],
            ['code' => 'ruecknahme', 'label' => 'Rücknahme / Reinigung'],
            ['code' => 'reklamation', 'label' => 'Reklamation'],
        ],
        'activity' => [
            ['code' => 'planen', 'label' => 'Planen'],
            ['code' => 'einkaufen', 'label' => 'Einkaufen'],
            ['code' => 'vorbereiten', 'label' => 'Vorbereiten'],
            ['code' => 'kochen', 'label' => 'Kochen'],
            ['code' => 'anrichten', 'label' => 'Anrichten'],
            ['code' => 'transportieren', 'label' => 'Transportieren'],
            ['code' => 'aufbauen', 'label' => 'Aufbauen'],
            ['code' => 'servieren', 'label' => 'Servieren'],
            ['code' => 'abbauen', 'label' => 'Abbauen'],
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'kuehlketteUnterbrochen', 'label' => 'Kühlkette unterbrochen'],
            ['code' => 'mengeFalsch', 'label' => 'Menge falsch'],
            ['code' => 'allergenInfoFehlt', 'label' => 'Allergeninfo fehlt'],
            ['code' => 'qualitaetsmangel', 'label' => 'Qualitätsmangel'],
            ['code' => 'personalEngpass', 'label' => 'Personalengpass'],
            ['code' => 'equipmentFehlt', 'label' => 'Equipment fehlt'],
            ['code' => 'transportschaden', 'label' => 'Transportschaden'],
            ['code' => 'kundenAenderung', 'label' => 'Kundenänderung'],
            ['code' => 'hygienemangel', 'label' => 'Hygienemangel'],
        ],
        'root_cause' => [
            ['code' => 'planung', 'label' => 'Planung'],
            ['code' => 'einkauf', 'label' => 'Einkauf'],
            ['code' => 'kuehlung', 'label' => 'Kühlung'],
            ['code' => 'personal', 'label' => 'Personal'],
            ['code' => 'lieferant', 'label' => 'Lieferant'],
            ['code' => 'transport', 'label' => 'Transport'],
            ['code' => 'kommunikation', 'label' => 'Kommunikation'],
            ['code' => 'wetter', 'label' => 'Wetter'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'teilErledigt', 'label' => 'Teilweise erledigt'],
            ['code' => 'nachbessern', 'label' => 'Nachbessern'],
            ['code' => 'ersatzGeliefert', 'label' => 'Ersatz geliefert'],
            ['code' => 'kundeInformiert', 'label' => 'Kunde informiert'],
            ['code' => 'freigegeben', 'label' => 'Freigegeben'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'priority' => [
            ['code' => 'standard', 'label' => 'Standard'],
            ['code' => 'express', 'label' => 'Express / kurzfristig'],
            ['code' => 'grossveranstaltung', 'label' => 'Großveranstaltung'],
        ],
        'product_group' => [
            ['code' => 'vorspeise', 'label' => 'Vorspeise'],
            ['code' => 'hauptgang', 'label' => 'Hauptgang'],
            ['code' => 'dessert', 'label' => 'Dessert'],
            ['code' => 'fingerfood', 'label' => 'Fingerfood'],
            ['code' => 'buffet', 'label' => 'Buffet'],
            ['code' => 'getraenke', 'label' => 'Getränke'],
            ['code' => 'kaffeebar', 'label' => 'Kaffeebar'],
            ['code' => 'sonderkost', 'label' => 'Sonderkost (vegan/halal/glutenfrei)'],
        ],
        'goodwill_reason' => [
            ['code' => 'kulanz', 'label' => 'Kulanz'],
            ['code' => 'verspaetung', 'label' => 'Verspätung'],
            ['code' => 'qualitaet', 'label' => 'Qualitätsmangel'],
            ['code' => 'stammkunde', 'label' => 'Stammkunde'],
        ],
        'dienstmittel_type' => [
            ['code' => 'chafingDish', 'label' => 'Chafing Dish'],
            ['code' => 'thermoport', 'label' => 'Thermoport'],
            ['code' => 'kuehlbox', 'label' => 'Kühlbox'],
            ['code' => 'geschirr', 'label' => 'Geschirr'],
            ['code' => 'glaeser', 'label' => 'Gläser'],
            ['code' => 'besteck', 'label' => 'Besteck'],
            ['code' => 'mobiliar', 'label' => 'Mobiliar (Tische/Stühle)'],
        ],
        // 14 Hauptallergene nach LMIV (EU 1169/2011, Anhang II). Werden je
        // Menüplanung zugeordnet und vor Abschluss erzwungen (allow_multi).
        // `keine` (v2, MVP-455): markiert eine Zutat explizit als geklärt
        // allergenfrei — Zutaten ohne jede Zuordnung blockieren die
        // Rezeptfreigabe.
        'allergen' => [
            ['code' => 'keine', 'label' => 'Keine Allergene'],
            ['code' => 'gluten', 'label' => 'Glutenhaltiges Getreide'],
            ['code' => 'krebstiere', 'label' => 'Krebstiere'],
            ['code' => 'ei', 'label' => 'Eier'],
            ['code' => 'fisch', 'label' => 'Fische'],
            ['code' => 'erdnuss', 'label' => 'Erdnüsse'],
            ['code' => 'soja', 'label' => 'Soja'],
            ['code' => 'milch', 'label' => 'Milch / Laktose'],
            ['code' => 'schalenfruechte', 'label' => 'Schalenfrüchte (Nüsse)'],
            ['code' => 'sellerie', 'label' => 'Sellerie'],
            ['code' => 'senf', 'label' => 'Senf'],
            ['code' => 'sesam', 'label' => 'Sesam'],
            ['code' => 'sulfite', 'label' => 'Schwefeldioxid / Sulfite'],
            ['code' => 'lupine', 'label' => 'Lupinen'],
            ['code' => 'weichtiere', 'label' => 'Weichtiere'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'menueplanung',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'allow_multi' => true,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'menueplanung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            // LMIV-Allergenkennzeichnung: vor Abschluss der Menüplanung müssen die
            // enthaltenen Allergene zugeordnet sein (mehrere zulässig).
            'entry_type_code' => 'menueplanung',
            'required_domain' => 'allergen',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'allow_multi' => true,
            'min_count' => 1,
            'note' => 'Allergenkennzeichnung nach LMIV (EU 1169/2011).',
        ],
        [
            'entry_type_code' => 'anlieferung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'aufbau',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'service',
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
    ],
    'procedure_templates' => [
        [
            'code' => 'PS_HACCP_KUEHLKETTE',
            'name' => 'HACCP-/Kühlkettenkontrolle',
            'domain' => 'partyservice',
            'risk_level' => 'high',
            'description' => 'Temperaturkontrolle der Kühlkette von Anlieferung bis Service mit Nachweis.',
            'steps' => [
                ['code' => 'tempAnlieferung', 'step_type' => 'number', 'label' => 'Kerntemperatur bei Anlieferung (°C) erfassen'],
                ['code' => 'kuehlungVorOrt', 'step_type' => 'confirm', 'label' => 'Kühlung/Lagerung vor Ort sicherstellen'],
                ['code' => 'tempVorService', 'step_type' => 'number', 'label' => 'Warmhalte-/Kühltemperatur vor Service (°C)'],
                ['code' => 'grenzwert', 'step_type' => 'choice', 'label' => 'Grenzwerte eingehalten?'],
                ['code' => 'abweichung', 'step_type' => 'confirm', 'label' => 'Abweichung melden (falls nötig)', 'required' => false, 'blocking' => false],
                ['code' => 'nachweis', 'step_type' => 'photo', 'label' => 'Temperatur-/Hygienenachweis ablegen', 'requires_proof_type' => 'photo'],
            ],
        ],
        [
            'code' => 'PS_ALLERGEN_KENNZEICHNUNG',
            'name' => 'Allergenkennzeichnung (LMIV)',
            'domain' => 'partyservice',
            'risk_level' => 'high',
            'description' => 'Kennzeichnung der 14 Hauptallergene je Gericht gemäß LMIV vor Auslieferung.',
            'steps' => [
                ['code' => 'gerichteErfassen', 'step_type' => 'choice', 'label' => 'Gerichte/Komponenten erfassen'],
                ['code' => 'allergeneZuordnen', 'step_type' => 'confirm', 'label' => '14 Hauptallergene je Gericht zuordnen'],
                ['code' => 'sonderkost', 'step_type' => 'confirm', 'label' => 'Sonderkost (vegan/halal/glutenfrei) prüfen', 'required' => false, 'blocking' => false],
                ['code' => 'kennzeichnung', 'step_type' => 'confirm', 'label' => 'Kennzeichnung am Buffet/Menükarte erstellen'],
                ['code' => 'freigabe', 'step_type' => 'freigabe', 'label' => 'Allergenfreigabe vor Auslieferung'],
            ],
        ],
        [
            'code' => 'PS_MISE_EN_PLACE',
            'name' => 'Mise en place / Vorbereitung',
            'domain' => 'partyservice',
            'risk_level' => 'normal',
            'description' => 'Vorbereitung von Speisen, Material und Equipment vor Anlieferung.',
            'steps' => [
                ['code' => 'mengenPruefen', 'step_type' => 'confirm', 'label' => 'Mengen gegen Gästezahl prüfen'],
                ['code' => 'material', 'step_type' => 'material', 'label' => 'Lebensmittel/Material erfassen', 'required' => false, 'blocking' => false],
                ['code' => 'equipment', 'step_type' => 'dienstmittel', 'label' => 'Equipment/Geschirr bereitstellen', 'required' => false, 'blocking' => false],
                ['code' => 'verladung', 'step_type' => 'confirm', 'label' => 'Verladung/Transport vorbereiten'],
            ],
        ],
        [
            'code' => 'PS_EVENT_ABNAHME',
            'name' => 'Event-Abnahme',
            'domain' => 'partyservice',
            'risk_level' => 'normal',
            'description' => 'Abnahme durch den Kunden nach Aufbau bzw. Service.',
            'steps' => [
                ['code' => 'aufbauPruefen', 'step_type' => 'confirm', 'label' => 'Aufbau/Buffet auf Vollständigkeit prüfen'],
                ['code' => 'fotos', 'step_type' => 'photo', 'label' => 'Aufbau dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'abnahme', 'step_type' => 'signature', 'label' => 'Kundenabnahme unterschreiben lassen', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'signature'],
            ],
        ],
        ['code' => 'PS_EINKAUF'],
        ['code' => 'PS_SERVICE'],
        ['code' => 'PS_ABBAU_RUECKNAHME'],
        ['code' => 'PS_REKLAMATION'],
    ],
    'room_requirement_templates_seed' => [
        ['code' => 'ps_kueche', 'kind' => 'hygieneLevel', 'label' => 'Küchenhygiene vor Ort', 'level' => 'standard', 'note' => 'Geforderte Hygienestufe für Vorbereitung/Anrichten am Veranstaltungsort.'],
        ['code' => 'ps_kuehlung', 'kind' => 'other', 'label' => 'Kühlmöglichkeit vorhanden', 'note' => 'Kühlung/Kühlraum für die Kühlkette am Veranstaltungsort.'],
        ['code' => 'ps_strom', 'kind' => 'other', 'label' => 'Stromanschluss Küche/Warmhaltung', 'note' => 'Ausreichende Stromversorgung für Warmhaltung und Geräte.'],
        ['code' => 'ps_zugang', 'kind' => 'accessRestriction', 'label' => 'Anlieferzugang/Zufahrt', 'note' => 'Zufahrt/Zugang für Anlieferung klären.'],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'PS_ANGEBOT'],
        ['code' => 'PS_MENUEKARTE'],
        ['code' => 'PS_HACCP_PROTOKOLL'],
        ['code' => 'PS_LIEFERSCHEIN'],
        ['code' => 'PS_ABNAHME'],
        ['code' => 'PS_REKLAMATIONSBERICHT'],
    ],
    'asset_categories' => [
        'chafingDish',
        'thermoport',
        'kuehlanhaenger',
        'geschirrset',
        'glaeserset',
        'mobiliar',
        'zelt',
        'kaffeemaschine',
    ],
    'tags_seed' => [
        '#catering',
        '#buffet',
        '#service',
        '#haccp',
        '#allergene',
        '#kuehlkette',
        '#event',
        '#reklamation',
    ],
];

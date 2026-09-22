<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : handwerk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'handwerk',
    'label' => 'Handwerk / Service allgemein',
    'description' => 'Handwerk und Service allgemein: Service, Wartung, Reparatur, Installation, Inspektion und Aufmaß als Grundausstattung für gemischte Betriebe.',
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
        'module.fuhrpark',
        'module.lager',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'service', 'label' => 'Service'],
            ['code' => 'maintenance', 'label' => 'Wartung'],
            ['code' => 'repair', 'label' => 'Reparatur'],
            ['code' => 'installation', 'label' => 'Installation'],
            ['code' => 'inspection', 'label' => 'Inspektion'],
            ['code' => 'advice', 'label' => 'Beratung'],
            ['code' => 'aufmass', 'label' => 'Aufmaß'],
        ],
        'activity' => [
            ['code' => 'install', 'label' => 'Montieren'],
            ['code' => 'dismantle', 'label' => 'Demontieren'],
            ['code' => 'repair', 'label' => 'Reparieren'],
            ['code' => 'measure', 'label' => 'Messen'],
            ['code' => 'document', 'label' => 'Dokumentieren'],
            ['code' => 'cleanUp', 'label' => 'Baustelle reinigen'],
        ],
        'defect_type' => [
            ['code' => 'mechanical', 'label' => 'Mechanisch'],
            ['code' => 'electrical', 'label' => 'Elektrisch'],
            ['code' => 'plumbing', 'label' => 'Sanitär'],
            ['code' => 'surface', 'label' => 'Oberfläche'],
            ['code' => 'wear', 'label' => 'Verschleiß'],
            ['code' => 'accidental', 'label' => 'Unfallschaden'],
        ],
        'root_cause' => [
            ['code' => 'wear', 'label' => 'Verschleiß'],
            ['code' => 'misuse', 'label' => 'Fehlbedienung'],
            ['code' => 'defect', 'label' => 'Defekt'],
            ['code' => 'installation', 'label' => 'Installationsfehler'],
            ['code' => 'externalDamage', 'label' => 'Externer Schaden'],
        ],
        'result' => [
            ['code' => 'resolved', 'label' => 'Gelöst'],
            ['code' => 'partialResolved', 'label' => 'Teilweise gelöst'],
            ['code' => 'materialMissing', 'label' => 'Material fehlt'],
            ['code' => 'customerDecided', 'label' => 'Kundenentscheidung'],
            ['code' => 'escalated', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'heater', 'label' => 'Heizung'],
            ['code' => 'boiler', 'label' => 'Boiler'],
            ['code' => 'sanitary', 'label' => 'Sanitär'],
            ['code' => 'lighting', 'label' => 'Beleuchtung'],
            ['code' => 'switchgear', 'label' => 'Schaltanlage'],
            ['code' => 'surface', 'label' => 'Oberfläche'],
            ['code' => 'door', 'label' => 'Tür'],
            ['code' => 'window', 'label' => 'Fenster'],
        ],
        'dienstmittel_type' => [
            ['code' => 'tool', 'label' => 'Werkzeug'],
            ['code' => 'ladder', 'label' => 'Leiter'],
            ['code' => 'vehicle', 'label' => 'Fahrzeug'],
            ['code' => 'lift', 'label' => 'Hebebühne'],
            ['code' => 'instrument', 'label' => 'Messgerät'],
        ],
        // Gewerke / Nachunternehmer-Kategorien (Auswahl je Eintrag; konkreter
        // Betrieb liegt im Lieferanten-/Nachunternehmer-Stamm).
        'trade' => [
            ['code' => 'elektro', 'label' => 'Elektro'],
            ['code' => 'sanitaer_heizung', 'label' => 'Sanitär / Heizung'],
            ['code' => 'maler', 'label' => 'Maler'],
            ['code' => 'fliesenleger', 'label' => 'Fliesenleger'],
            ['code' => 'schreiner', 'label' => 'Schreiner / Tischler'],
            ['code' => 'metallbau', 'label' => 'Metallbau'],
            ['code' => 'dachdecker', 'label' => 'Dachdecker'],
            ['code' => 'trockenbau', 'label' => 'Trockenbau'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'repair',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'repair',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'repair',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'installation',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'installation',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'maintenance',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'maintenance',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'aufmass',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'service',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        [
            'code' => 'HW_SERVICE_CALL',
            'name' => 'Serviceeinsatz',
            'domain' => 'handwerk',
            'risk_level' => 'normal',
            'description' => 'Serviceeinsatz beim Kunden: Aufnahme, Diagnose, Durchführung, Material.',
            'steps' => [
                ['code' => 'problem', 'step_type' => 'text', 'label' => 'Problem/Auftrag aufnehmen'],
                ['code' => 'diagnose', 'step_type' => 'choice', 'label' => 'Diagnose/Ursache wählen'],
                ['code' => 'arbeit', 'step_type' => 'confirm', 'label' => 'Arbeit durchführen'],
                ['code' => 'material', 'step_type' => 'material', 'label' => 'Verbrauchtes Material erfassen', 'required' => false, 'blocking' => false],
            ],
        ],
        [
            'code' => 'HW_INSTALL_DEVICE',
            'name' => 'Geräteinstallation',
            'domain' => 'handwerk',
            'risk_level' => 'normal',
            'description' => 'Installation/Inbetriebnahme eines Geräts mit Funktionstest und Einweisung.',
            'steps' => [
                ['code' => 'geraetPruefen', 'step_type' => 'confirm', 'label' => 'Gerät/Lieferumfang prüfen'],
                ['code' => 'montage', 'step_type' => 'confirm', 'label' => 'Montage/Anschluss durchführen'],
                ['code' => 'funktionstest', 'step_type' => 'confirm', 'label' => 'Funktionstest durchführen'],
                ['code' => 'einweisung', 'step_type' => 'confirm', 'label' => 'Kunden einweisen'],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Installation dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
            ],
        ],
        [
            'code' => 'HW_HANDOVER_CUSTOMER',
            'name' => 'Kundenübergabe',
            'domain' => 'handwerk',
            'risk_level' => 'normal',
            'description' => 'Übergabe der Leistung an den Kunden mit Unterschrift.',
            'steps' => [
                ['code' => 'leistungZeigen', 'step_type' => 'confirm', 'label' => 'Erbrachte Leistung zeigen'],
                ['code' => 'restpunkte', 'step_type' => 'confirm', 'label' => 'Offene Punkte vermerken (falls vorhanden)', 'required' => false, 'blocking' => false],
                ['code' => 'unterschrift', 'step_type' => 'signature', 'label' => 'Abnahme durch Kunden', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'HW_MAINTENANCE',
            'name' => 'Wartung',
            'domain' => 'handwerk',
            'risk_level' => 'normal',
            'description' => 'Wartung nach Wartungsplan mit Verschleißteilen, Funktionsprüfung und Kundenbestätigung.',
            'steps' => [
                ['code' => 'geraet', 'step_type' => 'confirm', 'label' => 'Gerät/Anlage und Seriennummer prüfen'],
                ['code' => 'wartungsplan', 'step_type' => 'confirm', 'label' => 'Arbeiten nach Wartungsplan/Herstellervorgabe ausführen'],
                ['code' => 'verschleiss', 'step_type' => 'material', 'label' => 'Verschleißteile erfassen', 'required' => false, 'blocking' => false],
                ['code' => 'funktion', 'step_type' => 'confirm', 'label' => 'Funktionsprüfung durchführen'],
                ['code' => 'protokoll', 'step_type' => 'link_protocol', 'label' => 'Wartungsprotokoll verknüpfen', 'required' => false, 'blocking' => false],
                ['code' => 'unterschrift', 'step_type' => 'signature', 'label' => 'Wartung bestätigen lassen', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'HW_REPAIR',
            'name' => 'Reparatur',
            'domain' => 'handwerk',
            'risk_level' => 'normal',
            'description' => 'Reparatur mit Fehlerbild, Ursache, Ersatzteilen und Funktionstest.',
            'steps' => [
                ['code' => 'fehler', 'step_type' => 'text', 'label' => 'Fehlerbild aufnehmen'],
                ['code' => 'ursache', 'step_type' => 'choice', 'label' => 'Ursache einstufen'],
                ['code' => 'reparatur', 'step_type' => 'confirm', 'label' => 'Reparatur durchführen'],
                ['code' => 'ersatzteile', 'step_type' => 'material', 'label' => 'Ersatzteile erfassen', 'required' => false, 'blocking' => false],
                ['code' => 'test', 'step_type' => 'confirm', 'label' => 'Funktionstest nach Reparatur'],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Reparatur dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
            ],
        ],
        [
            'code' => 'HW_INSPECTION',
            'name' => 'Inspektion/Prüfung',
            'domain' => 'handwerk',
            'risk_level' => 'normal',
            'description' => 'Inspektion mit Prüfumfang, Sichtprüfung, Messwerten und Bewertung.',
            'steps' => [
                ['code' => 'umfang', 'step_type' => 'choice', 'label' => 'Prüfumfang wählen'],
                ['code' => 'sichtpruefung', 'step_type' => 'confirm', 'label' => 'Sichtprüfung durchführen'],
                ['code' => 'messwerte', 'step_type' => 'messreihe', 'label' => 'Messwerte erfassen', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'measure'],
                ['code' => 'bewertung', 'step_type' => 'choice', 'label' => 'Ergebnis bewerten'],
                ['code' => 'maengel', 'step_type' => 'text', 'label' => 'Mängel/Empfehlungen festhalten', 'required' => false, 'blocking' => false],
                ['code' => 'protokoll', 'step_type' => 'link_protocol', 'label' => 'Prüfprotokoll verknüpfen', 'required' => false, 'blocking' => false],
            ],
        ],
        [
            'code' => 'HW_AUFMASS',
            'name' => 'Aufmaß',
            'domain' => 'handwerk',
            'risk_level' => 'normal',
            'description' => 'Aufmaß vor Ort mit Maßen, Skizze und Bestätigung.',
            'steps' => [
                ['code' => 'bereich', 'step_type' => 'choice', 'label' => 'Bereich/Gewerk wählen'],
                ['code' => 'masse', 'step_type' => 'messreihe', 'label' => 'Maße erfassen', 'requires_proof_type' => 'measure'],
                ['code' => 'skizze', 'step_type' => 'photo', 'label' => 'Skizze/Foto ablegen', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'besonderheiten', 'step_type' => 'text', 'label' => 'Besonderheiten vermerken', 'required' => false, 'blocking' => false],
                ['code' => 'bestaetigung', 'step_type' => 'signature', 'label' => 'Aufmaß bestätigen lassen', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'signature'],
            ],
        ],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'HW_SERVICEBERICHT'],
        ['code' => 'HW_WARTUNGSPROTOKOLL'],
        ['code' => 'HW_ABNAHMEPROTOKOLL'],
        ['code' => 'HW_INSPEKTION'],
        ['code' => 'HW_AUFMASS'],
        ['code' => 'HW_GERAETEUEBERGABE'],
    ],
    'asset_categories' => [
        'heater',
        'boiler',
        'sanitary',
        'lighting',
        'switchgear',
        'surface',
        'door',
        'window',
        'tool',
        'ladder',
        'vehicle',
        'lift',
    ],
    'tags_seed' => [
        '#wartung',
        '#stoerung',
        '#kundendienst',
        '#baustelle',
        '#notdienst',
        '#ersatzteil',
        '#material',
        '#abnahme',
    ],
];

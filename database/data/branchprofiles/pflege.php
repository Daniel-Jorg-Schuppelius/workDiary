<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : pflege.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

/*
 * Branchenprofil „Ambulante Pflege" (Feature 042).
 *
 * Deklaratives Datenprofil — vom BranchProfileInstaller idempotent installiert.
 * Bildet die pflegefachliche Taxonomie (Grundpflege SGB XI / Behandlungspflege
 * SGB V, hauswirtschaftliche Versorgung, Betreuung, Beratungsbesuch §37.3) auf
 * die vorhandenen Klassifikationsdomänen ab; pflegespezifische Abläufe (5-R-
 * Medikamentengabe, Sturz, Wundversorgung, Erstbesuch/Pflegeanamnese) laufen
 * bewusst über Prozedurvorlagen, nicht über neue Klassifikationsdomänen.
 */

return [
    'code' => 'pflege',
    'label' => 'Ambulante Pflege',
    // v2: Default-Eintragstypen (Struktur-Typen) ans Profil gekoppelt.
    'version' => 3,
    // Default-Struktur-Typen (EntryTypeSeeder::profiles()) — nicht die
    // Classification-Domäne entry_type.
    'entry_type_defaults' => ['general', 'care_visit'],
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
        'module.standorterfassung',
        'module.datenschutz',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'grundpflege', 'label' => 'Grundpflege (SGB XI)'],
            ['code' => 'behandlungspflege', 'label' => 'Behandlungspflege (SGB V)'],
            ['code' => 'hauswirtschaft', 'label' => 'Hauswirtschaftliche Versorgung'],
            ['code' => 'betreuung', 'label' => 'Betreuungsleistung (§45b)'],
            ['code' => 'beratungsbesuch', 'label' => 'Beratungsbesuch (§37.3)'],
            ['code' => 'erstbesuch', 'label' => 'Erstbesuch / Pflegeanamnese'],
            ['code' => 'pflegevisite', 'label' => 'Pflegevisite'],
            ['code' => 'reklamation', 'label' => 'Beschwerde / Reklamation'],
        ],
        'activity' => [
            ['code' => 'koerperpflege', 'label' => 'Körperpflege'],
            ['code' => 'medikamentengabe', 'label' => 'Medikamentengabe'],
            ['code' => 'wundversorgung', 'label' => 'Wundversorgung'],
            ['code' => 'vitalzeichen', 'label' => 'Vitalzeichenkontrolle'],
            ['code' => 'injektion', 'label' => 'Injektion (s.c./i.m.)'],
            ['code' => 'mobilisation', 'label' => 'Mobilisation'],
            ['code' => 'nahrungsaufnahme', 'label' => 'Hilfe bei der Nahrungsaufnahme'],
            ['code' => 'prophylaxe', 'label' => 'Prophylaxe (Dekubitus/Thrombose)'],
            ['code' => 'anleiten', 'label' => 'Anleitung Angehöriger'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'sturz', 'label' => 'Sturz'],
            ['code' => 'medikationsfehler', 'label' => 'Medikationsfehler'],
            ['code' => 'wundeVerschlechtert', 'label' => 'Wundverschlechterung'],
            ['code' => 'klientNichtAngetroffen', 'label' => 'Klient nicht angetroffen'],
            ['code' => 'dokumentationsluecke', 'label' => 'Dokumentationslücke'],
            ['code' => 'hygienemangel', 'label' => 'Hygienemangel'],
            ['code' => 'terminverzug', 'label' => 'Terminverzug'],
            ['code' => 'kommunikationsproblem', 'label' => 'Kommunikationsproblem'],
        ],
        'root_cause' => [
            ['code' => 'personalengpass', 'label' => 'Personalengpass'],
            ['code' => 'zugang', 'label' => 'Wohnungszugang / Schlüssel'],
            ['code' => 'fehlendeAnordnung', 'label' => 'Fehlende ärztliche Anordnung'],
            ['code' => 'materialmangel', 'label' => 'Materialmangel'],
            ['code' => 'tourenplanung', 'label' => 'Tourenplanung'],
            ['code' => 'klientVerweigerung', 'label' => 'Ablehnung durch Klient'],
            ['code' => 'angehoerige', 'label' => 'Angehörige'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'teilErledigt', 'label' => 'Teilweise erledigt'],
            ['code' => 'abgelehnt', 'label' => 'Vom Klienten abgelehnt'],
            ['code' => 'nichtAngetroffen', 'label' => 'Nicht angetroffen'],
            ['code' => 'arztInformiert', 'label' => 'Arzt informiert'],
            ['code' => 'angehoerigeInformiert', 'label' => 'Angehörige informiert'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
    ],
    'classification_requirements' => [
        // Behandlungspflege (SGB V) braucht die konkrete Maßnahme und ein Ergebnis.
        [
            'entry_type_code' => 'behandlungspflege',
            'required_domain' => 'activity',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'behandlungspflege',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        // Grundpflege braucht ein dokumentiertes Ergebnis (Leistungsnachweis).
        [
            'entry_type_code' => 'grundpflege',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        // Beratungsbesuch §37.3 ist nachweispflichtig → Ergebnis erforderlich.
        [
            'entry_type_code' => 'beratungsbesuch',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        // Beschwerde braucht Vorkommnisart (bei Anlage) und Ursache (vor Abschluss).
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
            'code' => 'PF_MEDIKAMENTENGABE',
            'name' => 'Medikamentengabe (5-R-Regel)',
            'domain' => 'pflege',
            'risk_level' => 'high',
            'description' => 'Sichere Medikamentengabe nach 5-R (richtiger Klient, Medikament, Dosis, Zeitpunkt, Applikationsform) mit Handzeichen; BtM-Bestand im Vier-Augen-Prinzip.',
            'steps' => [
                ['code' => 'klientIdentifizieren', 'step_type' => 'confirm', 'label' => 'Richtigen Klienten identifizieren'],
                ['code' => 'medikamentPruefen', 'step_type' => 'choice', 'label' => 'Medikament laut Medikationsplan wählen'],
                ['code' => 'dosisZeitpunkt', 'step_type' => 'confirm', 'label' => 'Richtige Dosis und Zeitpunkt bestätigen'],
                ['code' => 'applikationsform', 'step_type' => 'confirm', 'label' => 'Richtige Applikationsform bestätigen'],
                ['code' => 'btmKontrolle', 'step_type' => 'confirm', 'label' => 'BtM-Bestandskontrolle (Vier-Augen-Prinzip)', 'required' => false, 'blocking' => false, 'requires_second_person' => true],
                ['code' => 'handzeichen', 'step_type' => 'signature', 'label' => 'Handzeichen dokumentieren', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'PF_STURZPROTOKOLL',
            'name' => 'Sturzereignis dokumentieren',
            'domain' => 'pflege',
            'risk_level' => 'high',
            'description' => 'Sturz erfassen, Vitalzeichen prüfen, Verletzung bewerten, Arzt/Angehörige informieren und nachweisen.',
            'steps' => [
                ['code' => 'hergang', 'step_type' => 'confirm', 'label' => 'Sturzhergang erfassen'],
                ['code' => 'vitalzeichen', 'step_type' => 'confirm', 'label' => 'Vitalzeichen prüfen'],
                ['code' => 'verletzung', 'step_type' => 'choice', 'label' => 'Verletzung/Schweregrad bewerten'],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Sichtbare Verletzung fotografieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'arztInfo', 'step_type' => 'confirm', 'label' => 'Arzt/Angehörige informieren'],
                ['code' => 'unterschrift', 'step_type' => 'signature', 'label' => 'Dokumentation bestätigen', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'PF_WUNDVERSORGUNG',
            'name' => 'Wundversorgung',
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'description' => 'Wundversorgung nach ärztlicher Anordnung mit Materialnachweis und Wunddokumentation.',
            'steps' => [
                ['code' => 'anordnung', 'step_type' => 'confirm', 'label' => 'Ärztliche Anordnung beachten'],
                ['code' => 'wundstatus', 'step_type' => 'choice', 'label' => 'Wundstatus beurteilen'],
                ['code' => 'material', 'step_type' => 'material', 'label' => 'Verbandmaterial erfassen', 'required' => false, 'blocking' => false],
                ['code' => 'wundfoto', 'step_type' => 'photo', 'label' => 'Wunddokumentation (Foto)', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'nachweis', 'step_type' => 'signature', 'label' => 'Durchführung dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'PF_ERSTBESUCH',
            'name' => 'Erstbesuch / Pflegeanamnese',
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'description' => 'Erstbesuch mit Pflegeanamnese, Medikationsplan-Abgleich und Einwilligung.',
            'steps' => [
                ['code' => 'pflegegrad', 'step_type' => 'choice', 'label' => 'Pflegegrad erfassen'],
                ['code' => 'anamnese', 'step_type' => 'confirm', 'label' => 'Pflegeanamnese erheben'],
                ['code' => 'medikationsplan', 'step_type' => 'confirm', 'label' => 'Medikationsplan abgleichen'],
                ['code' => 'einwilligung', 'step_type' => 'signature', 'label' => 'Einwilligung dokumentieren', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'PF_BERATUNGSBESUCH_37_3',
            'name' => 'Beratungsbesuch (§37.3 SGB XI)',
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'description' => 'Nachweispflichtiger Beratungsbesuch bei Pflegegeld-Empfängern mit Empfehlungen und Bestätigung.',
            'steps' => [
                ['code' => 'situation', 'step_type' => 'confirm', 'label' => 'Pflegesituation erheben'],
                ['code' => 'empfehlungen', 'step_type' => 'choice', 'label' => 'Empfehlungen festhalten'],
                ['code' => 'bestaetigung', 'step_type' => 'signature', 'label' => 'Beratung bestätigen lassen', 'requires_proof_type' => 'signature'],
            ],
        ],
    ],
    'room_requirement_templates_seed' => [
        ['code' => 'pf_hygiene', 'kind' => 'hygieneLevel', 'label' => 'Hygienestufe', 'level' => 'standard', 'note' => 'Geforderte Hygienestufe beim Klienten.'],
        ['code' => 'pf_zutritt', 'kind' => 'accessRestriction', 'label' => 'Schlüssel/Schlüsseltresor', 'note' => 'Zugang zur Wohnung nur mit Schlüssel/Tresorcode.'],
        ['code' => 'pf_infektion', 'kind' => 'other', 'label' => 'Infektionsstatus (z. B. MRSA)', 'note' => 'Bekannter Infektionsstatus – Schutzmaßnahmen beachten.'],
        ['code' => 'pf_dokupflicht', 'kind' => 'other', 'label' => 'Dokumentationspflicht', 'note' => 'Leistungen mit Handzeichen/Nachweis dokumentieren.'],
    ],
    'tags_seed' => [
        '#grundpflege',
        '#behandlungspflege',
        '#sturz',
        '#wunde',
        '#medikation',
        '#beratungsbesuch',
        '#hygiene',
        '#dokumentation',
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'PF_PFLEGEBERICHT'],
        ['code' => 'PF_STURZBERICHT'],
        ['code' => 'PF_WUNDDOKUMENTATION'],
        ['code' => 'PF_MEDIKATIONSPLAN'],
        ['code' => 'PF_BERATUNGSNACHWEIS'],
        ['code' => 'PF_PFLEGEANAMNESE'],
    ],
    'asset_categories' => [
        'pflegetasche',
        'blutdruckmessgeraet',
        'blutzuckermessgeraet',
        'verbandwagen',
        'desinfektionsspender',
        'psaPflege',
    ],
    // Datenschutz-Anforderungsvorlagen (Nachtrag 043c): Gesundheitsdaten →
    // alle Prüfungen aktiv, DSFA-Pflicht betont.
    'dataprotection_requirements_seed' => [
        ['key' => 'avv_required'],
        ['key' => 'avv_current'],
        ['key' => 'gvv_required'],
        ['key' => 'dpia_required', 'label' => 'DSFA bei Gesundheitsdaten (Art. 9) zwingend'],
        ['key' => 'tom_assigned'],
        ['key' => 'tom_proof_current'],
    ],

    // Schulungsvorschläge (Feature 145): Pflichtschulungen der Pflege — ohne
    // Gesundheitsdaten, reine Teilnahme-/Fälligkeitsverwaltung.
    'training_suggestions' => [
        ['code' => 'hygiene', 'title' => 'Hygieneschulung', 'legal_basis' => '§ 4 IfSG / § 43 IfSG', 'validity_months' => 12, 'duration_minutes' => 120, 'roles' => ['aussendienst', 'teamleitung']],
        ['code' => 'kinaesthetik', 'title' => 'Rückengerechtes Arbeiten / Kinästhetik', 'legal_basis' => 'DGUV I 207-022', 'validity_months' => 24, 'duration_minutes' => 240, 'roles' => ['aussendienst']],
        ['code' => 'unterweisung-arbschg', 'title' => 'Jährliche Unterweisung Arbeitssicherheit', 'legal_basis' => '§ 12 ArbSchG / DGUV V1 § 4', 'validity_months' => 12, 'duration_minutes' => 60, 'roles' => ['aussendienst', 'teamleitung']],
    ],
];

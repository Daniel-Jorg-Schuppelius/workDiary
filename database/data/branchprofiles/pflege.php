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
 * Inhalte nach der Profilseite branchenprofil-ambulante-pflege.md §3–§8 (v6,
 * MVP-1509). `supersedes` überführt Codes älterer Versionen: der Nachfolger
 * entsteht, der Vorgänger wird deaktiviert, nie umbenannt.
 */

$hard = ClassificationRequirementSeverity::Hard->value;
$onCreate = ClassificationRequirementPhase::OnCreate->value;
$beforeComplete = ClassificationRequirementPhase::BeforeComplete->value;

return [
    'code' => 'pflege',
    'label' => 'Ambulante Pflege',
    'description' => 'Ambulanter Pflegedienst (SGB V / SGB XI): Tour-Einsätze, Grund- und Behandlungspflege, Beratungsbesuche nach § 37 Abs. 3 SGB XI als Kundenfrist, Pflegevisiten, 5-R-Regel und Vier-Augen-Prinzip bei BtM.',
    // v2: Default-Eintragstypen; v6: Inhalte nach der Profilseite (MVP-1509).
    // v7: Eintragsart Beratungsbesuch mit Pflicht-Periode Halbjahr (Feature 198, MVP-1634).
    // v8: Beratungsbesuch als Kundenfrist aus dem Regelsatz ambulante Pflege statt Vertragspflicht
    //     „alle drei Monate“, „Quartalsraster“ berichtigt (§ 37 Abs. 3 SGB XI, Stand 2026; Feature 197, MVP-1673).
    // v9: Eintragsart Pflegevisite und Leistungsvorlagen Beratungsbesuch und Pflegevisite je Halbjahr (Feature 199, MVP-1674).
    // v10: Prüfpaket Pflege-Erstaufnahme der Aufnahmeakte eingeschaltet (Feature 200, MVP-1675).
    'version' => 10,
    // MVP-1053: Arbeitskosten nach § 35a EStG bei Privatkunden ausweisen; eigene Einstellungen bleiben.
    'settings' => ['invoicing.labour_cost_disclosure' => 'private_customers'],
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
        // Pflegevertrag (contract_templates) mit Pflegevisite; Beratungsbesuche laufen als Kundenfrist (Feature 197).
        'module.contracts',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'tourEinsatz', 'label' => 'Tour-Einsatz'],
            ['code' => 'grundpflege', 'label' => 'Grundpflege (SGB XI)'],
            ['code' => 'behandlungspflege', 'label' => 'Behandlungspflege (SGB V)'],
            ['code' => 'betreuung', 'label' => 'Betreuungsleistung (§45b)'],
            ['code' => 'hauswirtschaft', 'label' => 'Hauswirtschaftliche Versorgung'],
            ['code' => 'beratungsbesuch', 'label' => 'Beratungsbesuch (§37.3)'],
            ['code' => 'pflegevisite', 'label' => 'Pflegevisite'],
            ['code' => 'vorfall', 'label' => 'Vorfall'],
            ['code' => 'qualitaetspruefung', 'label' => 'Qualitätsprüfung (Medizinischer Dienst)'],
            ['code' => 'erstbesuch', 'label' => 'Erstbesuch / Pflegeanamnese'],
            ['code' => 'reklamation', 'label' => 'Beschwerde / Reklamation'],
        ],
        'activity' => [
            ['code' => 'koerperpflege', 'label' => 'Körperpflege'],
            ['code' => 'mobilisation', 'label' => 'Mobilisation'],
            ['code' => 'lagerung', 'label' => 'Lagerung'],
            ['code' => 'nahrungsgabe', 'label' => 'Nahrungsgabe', 'supersedes' => ['nahrungsaufnahme']],
            ['code' => 'medikamentenstellen', 'label' => 'Medikamente stellen'],
            ['code' => 'medikamentengabe', 'label' => 'Medikamentengabe'],
            ['code' => 'injektion', 'label' => 'Injektion (s.c./i.m.)'],
            ['code' => 'wundversorgung', 'label' => 'Wundversorgung'],
            ['code' => 'blutzucker', 'label' => 'Blutzuckermessung'],
            ['code' => 'vitalwerte', 'label' => 'Vitalwerte', 'supersedes' => ['vitalzeichen']],
            ['code' => 'dokumentation', 'label' => 'Dokumentation', 'supersedes' => ['dokumentieren']],
            ['code' => 'anleitung', 'label' => 'Anleitung Angehöriger', 'supersedes' => ['anleiten']],
            ['code' => 'hauswirtschaftlich', 'label' => 'Hauswirtschaftliche Hilfe'],
            ['code' => 'prophylaxe', 'label' => 'Prophylaxe (Dekubitus/Thrombose)'],
        ],
        'defect_type' => [
            ['code' => 'hautlaesion', 'label' => 'Hautläsion'],
            ['code' => 'dekubitusVerdacht', 'label' => 'Verdacht auf Dekubitus'],
            ['code' => 'sturz', 'label' => 'Sturz'],
            ['code' => 'schmerz', 'label' => 'Schmerz'],
            ['code' => 'infektVerdacht', 'label' => 'Verdacht auf Infektion'],
            ['code' => 'medikamentenproblem', 'label' => 'Medikamentenproblem', 'supersedes' => ['medikationsfehler']],
            ['code' => 'mangelernaehrung', 'label' => 'Mangelernährung'],
            ['code' => 'dehydration', 'label' => 'Dehydration'],
            ['code' => 'kommunikation', 'label' => 'Kommunikationsproblem', 'supersedes' => ['kommunikationsproblem']],
            ['code' => 'wundeVerschlechtert', 'label' => 'Wundverschlechterung'],
            ['code' => 'klientNichtAngetroffen', 'label' => 'Klient nicht angetroffen'],
            ['code' => 'dokumentationsluecke', 'label' => 'Dokumentationslücke'],
            ['code' => 'hygienemangel', 'label' => 'Hygienemangel'],
            ['code' => 'terminverzug', 'label' => 'Terminverzug'],
        ],
        'root_cause' => [
            ['code' => 'grunderkrankung', 'label' => 'Grunderkrankung'],
            ['code' => 'neueMedikation', 'label' => 'Neue Medikation'],
            ['code' => 'immobilitaet', 'label' => 'Immobilität'],
            ['code' => 'sturzAnamnese', 'label' => 'Sturzanamnese'],
            ['code' => 'externerArzt', 'label' => 'Externe Ärztin / externer Arzt'],
            ['code' => 'angehoerige', 'label' => 'Angehörige'],
            ['code' => 'geraeteausfall', 'label' => 'Geräteausfall'],
            ['code' => 'personalengpass', 'label' => 'Personalengpass'],
            ['code' => 'zugang', 'label' => 'Wohnungszugang / Schlüssel'],
            ['code' => 'fehlendeAnordnung', 'label' => 'Fehlende ärztliche Anordnung'],
            ['code' => 'materialmangel', 'label' => 'Materialmangel'],
            ['code' => 'tourenplanung', 'label' => 'Tourenplanung'],
            ['code' => 'klientVerweigerung', 'label' => 'Ablehnung durch Klient'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'teilErledigt', 'label' => 'Teilweise erledigt'],
            ['code' => 'abgelehntVomKlienten', 'label' => 'Vom Klienten abgelehnt', 'supersedes' => ['abgelehnt']],
            ['code' => 'nichtAngetroffen', 'label' => 'Nicht angetroffen'],
            ['code' => 'abgebrochen', 'label' => 'Abgebrochen'],
            ['code' => 'eskaliertArzt', 'label' => 'An Ärztin/Arzt eskaliert'],
            ['code' => 'eskaliertLeitung', 'label' => 'An Pflegedienstleitung eskaliert'],
            ['code' => 'arztInformiert', 'label' => 'Arzt informiert'],
            ['code' => 'angehoerigeInformiert', 'label' => 'Angehörige informiert'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'wundauflage', 'label' => 'Wundauflagen'],
            ['code' => 'kompressionsstrumpf', 'label' => 'Kompressionsstrümpfe'],
            ['code' => 'inkontinenzmaterial', 'label' => 'Inkontinenzmaterial'],
            ['code' => 'hilfsmittel', 'label' => 'Pflegehilfsmittel'],
            ['code' => 'medikament', 'label' => 'Medikamente'],
            ['code' => 'pen', 'label' => 'Pen (Insulin u. a.)'],
            ['code' => 'sondennahrung', 'label' => 'Sondennahrung'],
            ['code' => 'sauerstoff', 'label' => 'Sauerstoff'],
        ],
        'leistungsart' => [
            ['code' => 'sgbVBehandlungspflege', 'label' => 'Behandlungspflege (SGB V)'],
            ['code' => 'sgbXIGrundpflege', 'label' => 'Grundpflege (SGB XI)'],
            ['code' => 'sgbXIBetreuung', 'label' => 'Betreuung (SGB XI)'],
            ['code' => 'sgbXIHauswirtschaft', 'label' => 'Hauswirtschaftliche Versorgung (SGB XI)'],
            ['code' => 'sgbXVerhinderungspflege', 'label' => 'Verhinderungspflege'],
            ['code' => 'selbstzahler', 'label' => 'Selbstzahler'],
        ],
        'pflegegrad' => [
            ['code' => 'pg1', 'label' => 'Pflegegrad 1'],
            ['code' => 'pg2', 'label' => 'Pflegegrad 2'],
            ['code' => 'pg3', 'label' => 'Pflegegrad 3'],
            ['code' => 'pg4', 'label' => 'Pflegegrad 4'],
            ['code' => 'pg5', 'label' => 'Pflegegrad 5'],
        ],
        'kommunikation' => [
            ['code' => 'uebergabe', 'label' => 'Übergabe'],
            ['code' => 'telefonHausarzt', 'label' => 'Telefonat Hausärztin/Hausarzt'],
            ['code' => 'telefonAngehoerige', 'label' => 'Telefonat Angehörige'],
            ['code' => 'faxApotheke', 'label' => 'Fax an Apotheke'],
            ['code' => 'klinikbericht', 'label' => 'Klinikbericht'],
            ['code' => 'mdkAnfrage', 'label' => 'Anfrage Medizinischer Dienst'],
        ],
    ],
    // §4 der Profilseite, soweit über Klassifikationen abbildbar; Klientenbezug,
    // Vier-Augen-Prinzip, Unterschriften und Fotopflicht tragen die Prozedurvorlagen.
    // Feature 198 (MVP-1634): Beratungsbesuch nach § 37 Abs. 3 SGB XI je Halbjahr; nur fehlende Eintragsarten entstehen.
    // Feature 197 (MVP-1673): Quartal zusätzlich — Pflegegrad 4–5 auf Wunsch vierteljährlich.
    'entry_type_rules' => [
        ['slug' => 'beratungsbesuch', 'label' => 'Beratungsbesuch (§ 37 Abs. 3 SGB XI)', 'icon' => 'support_agent', 'requires_period' => ['half_year', 'quarter']],
        // Feature 199 (MVP-1674): Pflegevisite als Eintragsart für den Leistungsplan (ohne Periodenpflicht).
        ['slug' => 'pflegevisite', 'label' => 'Pflegevisite', 'icon' => 'medical_services'],
    ],
    // Feature 197 (MVP-1673): Kundenfristen aus dem Regelsatz ambulante Pflege; ergänzt nur.
    'deadline_rule_sets' => ['home_care'],
    // Feature 200 (MVP-1675): Aufnahmeakte mit Prüfpaket Pflege-Erstaufnahme (Katalog database/data/intake_packages.php);
    // Pflegegrad und Leistungsbezug werden bei der Umwandlung Merkmale des Regelsatzes `home_care`. Ergänzt nur.
    'intake_packages' => [['code' => 'care_admission', 'enabled' => true]],
    // Feature 199 (MVP-1674): Leistungsvorlagen des Leistungsplans; die gesetzliche Frist rechnet der Regelsatz.
    'service_catalog' => [
        ['entry_type' => 'beratungsbesuch', 'period_kind' => 'half_year', 'mandatory' => true, 'label' => 'Beratungsbesuch (§ 37 Abs. 3 SGB XI)',
            'label_i18n' => ['en' => 'Counselling visit (§ 37(3) SGB XI)', 'es' => 'Visita de asesoramiento (§ 37.3 SGB XI)', 'fr' => 'Visite de conseil (§ 37 al. 3 SGB XI)', 'it' => 'Visita di consulenza (§ 37 c. 3 SGB XI)']],
        ['entry_type' => 'pflegevisite', 'period_kind' => 'half_year', 'mandatory' => true, 'label' => 'Pflegevisite',
            'label_i18n' => ['en' => 'Care review visit', 'es' => 'Visita de control de cuidados', 'fr' => 'Visite de contrôle des soins', 'it' => 'Visita di controllo dell’assistenza']],
    ],
    'classification_requirements' => [
        ['entry_type_code' => 'tourEinsatz', 'required_domain' => 'leistungsart', 'enforce_phase' => $onCreate, 'severity' => $hard, 'min_count' => 1],
        ['entry_type_code' => 'tourEinsatz', 'required_domain' => 'result', 'enforce_phase' => $beforeComplete, 'severity' => $hard, 'min_count' => 1],
        ['entry_type_code' => 'behandlungspflege', 'required_domain' => 'activity', 'enforce_phase' => $onCreate, 'severity' => $hard, 'allow_multi' => true, 'min_count' => 1],
        ['entry_type_code' => 'behandlungspflege', 'required_domain' => 'result', 'enforce_phase' => $beforeComplete, 'severity' => $hard, 'min_count' => 1],
        ['entry_type_code' => 'grundpflege', 'required_domain' => 'pflegegrad', 'enforce_phase' => $onCreate, 'severity' => $hard, 'min_count' => 1, 'note' => 'Pflegegrad aus den Stammdaten des Klienten übernehmen.'],
        ['entry_type_code' => 'grundpflege', 'required_domain' => 'result', 'enforce_phase' => $beforeComplete, 'severity' => $hard, 'min_count' => 1],
        ['entry_type_code' => 'vorfall', 'required_domain' => 'defect_type', 'enforce_phase' => $onCreate, 'severity' => $hard, 'min_count' => 1],
        ['entry_type_code' => 'vorfall', 'required_domain' => 'result', 'enforce_phase' => $beforeComplete, 'severity' => $hard, 'min_count' => 1, 'note' => 'Regelmäßig „An Ärztin/Arzt eskaliert“ oder „An Pflegedienstleitung eskaliert“.'],
        ['entry_type_code' => 'vorfall', 'required_domain' => 'kommunikation', 'enforce_phase' => $beforeComplete, 'severity' => ClassificationRequirementSeverity::Soft->value, 'allow_multi' => true, 'min_count' => 1],
        ['entry_type_code' => 'pflegevisite', 'required_domain' => 'result', 'enforce_phase' => $beforeComplete, 'severity' => $hard, 'min_count' => 1],
        ['entry_type_code' => 'beratungsbesuch', 'required_domain' => 'result', 'enforce_phase' => $beforeComplete, 'severity' => $hard, 'min_count' => 1],
        ['entry_type_code' => 'reklamation', 'required_domain' => 'defect_type', 'enforce_phase' => $onCreate, 'severity' => $hard, 'min_count' => 1],
        ['entry_type_code' => 'reklamation', 'required_domain' => 'root_cause', 'enforce_phase' => $beforeComplete, 'severity' => $hard, 'min_count' => 1],
    ],
    'procedure_templates' => [
        [
            'code' => 'AP_TOUR_EINSATZ',
            'name' => 'Einsatz auf der Tour',
            'name_i18n' => ['en' => 'Visit on the round', 'es' => 'Visita en la ruta', 'fr' => 'Intervention en tournée', 'it' => 'Intervento nel giro'],
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'description' => 'Standard-Einsatz beim Klienten: Ankunft, Tätigkeiten, Besonderheiten und Unterschrift.',
            'steps' => [
                ['code' => 'ankunft', 'step_type' => 'confirm', 'label' => 'Ankunft beim Klienten bestätigen'],
                ['code' => 'befinden', 'step_type' => 'confirm', 'label' => 'Klient identifiziert, Befinden erfragt'],
                ['code' => 'taetigkeiten', 'step_type' => 'choice', 'label' => 'Erbrachte Tätigkeiten wählen'],
                ['code' => 'material', 'step_type' => 'material', 'label' => 'Verbrauchsmaterial erfassen', 'required' => false, 'blocking' => false],
                ['code' => 'besonderheiten', 'step_type' => 'text', 'label' => 'Besonderheiten vermerken', 'required' => false, 'blocking' => false],
                ['code' => 'unterschrift', 'step_type' => 'signature', 'label' => 'Einsatz unterschreiben', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'AP_MEDIKAMENTENGABE',
            'name' => 'Medikamentengabe (5-R-Regel mit Quittierung)',
            'name_i18n' => ['en' => 'Medication administration (5 rights, acknowledged)', 'es' => 'Administración de medicamentos (regla de los 5 correctos, con acuse)', 'fr' => 'Administration de médicaments (règle des 5 B, avec quittance)', 'it' => 'Somministrazione di farmaci (regola delle 5 G, con conferma)'],
            'domain' => 'pflege',
            'risk_level' => 'high',
            'supersedes' => ['PF_MEDIKAMENTENGABE'],
            'description' => 'Gabe nach 5-R (Klient, Medikament, Dosis, Zeitpunkt, Applikationsform) mit Abgleich am Medikationsplan und Quittierung je Gabe.',
            'steps' => [
                ['code' => 'klientIdentifizieren', 'step_type' => 'confirm', 'label' => 'Richtigen Klienten identifizieren'],
                ['code' => 'medikationsplan', 'step_type' => 'confirm', 'label' => 'Abgleich mit dem Medikationsplan (Wirkstoff, Dosis, Uhrzeit)'],
                ['code' => 'dosisZeitpunkt', 'step_type' => 'confirm', 'label' => 'Richtige Dosis und Zeitpunkt bestätigen'],
                ['code' => 'applikationsform', 'step_type' => 'confirm', 'label' => 'Richtige Applikationsform bestätigen'],
                ['code' => 'gabe', 'step_type' => 'confirm', 'label' => 'Gabe durchgeführt'],
                ['code' => 'quittierung', 'step_type' => 'signature', 'label' => 'Gabe quittieren', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'AP_BTM_GABE',
            'name' => 'Betäubungsmittelgabe (Vier-Augen-Prinzip)',
            'name_i18n' => ['en' => 'Controlled drug administration (dual control)', 'es' => 'Administración de estupefacientes (principio de doble control)', 'fr' => 'Administration de stupéfiants (double contrôle)', 'it' => 'Somministrazione di stupefacenti (doppio controllo)'],
            'domain' => 'pflege',
            'risk_level' => 'high',
            'description' => 'BtM-Gabe nach ärztlicher Verordnung mit Bestandskontrolle, Gegenkontrolle durch eine zweite Pflegekraft und BtM-Buch-Eintrag.',
            'steps' => [
                ['code' => 'verordnung', 'step_type' => 'confirm', 'label' => 'Ärztliche Verordnung und Medikationsplan prüfen'],
                ['code' => 'bestand', 'step_type' => 'number', 'label' => 'BtM-Bestand vor der Gabe (Einheiten)'],
                ['code' => 'gabe', 'step_type' => 'confirm', 'label' => 'Gabe nach 5-R-Regel durchgeführt'],
                ['code' => 'gegenkontrolle', 'step_type' => 'confirm', 'label' => 'Gegenkontrolle durch zweite Pflegekraft', 'requires_second_person' => true],
                ['code' => 'btmBuch', 'step_type' => 'confirm', 'label' => 'Eintrag im BtM-Buch vorgenommen'],
                ['code' => 'quittierung', 'step_type' => 'signature', 'label' => 'Gabe quittieren', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'AP_WUNDVERSORGUNG',
            'name' => 'Wundversorgung mit Fotodokumentation',
            'name_i18n' => ['en' => 'Wound care with photo documentation', 'es' => 'Cura de heridas con documentación fotográfica', 'fr' => 'Soins des plaies avec documentation photo', 'it' => 'Medicazione con documentazione fotografica'],
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'supersedes' => ['PF_WUNDVERSORGUNG'],
            'description' => 'Wundversorgung nach Anordnung mit Fotos vorher und nachher, Lokalisation und Wundbericht; ohne Einwilligung zur Fotodokumentation bleibt der Ablauf stehen.',
            'steps' => [
                ['code' => 'einwilligung', 'step_type' => 'confirm', 'label' => 'Einwilligung zur Fotodokumentation liegt vor'],
                ['code' => 'anordnung', 'step_type' => 'confirm', 'label' => 'Ärztliche Anordnung beachten'],
                ['code' => 'fotoVorher', 'step_type' => 'photo', 'label' => 'Foto vor der Versorgung', 'requires_proof_type' => 'photo'],
                ['code' => 'lokalisation', 'step_type' => 'text', 'label' => 'Lokalisation am Körperdiagramm'],
                ['code' => 'versorgung', 'step_type' => 'confirm', 'label' => 'Wunde laut Anordnung versorgt'],
                ['code' => 'material', 'step_type' => 'material', 'label' => 'Verbandmaterial erfassen', 'required' => false, 'blocking' => false],
                ['code' => 'fotoNachher', 'step_type' => 'photo', 'label' => 'Foto nach der Versorgung', 'requires_proof_type' => 'photo'],
                ['code' => 'wundbericht', 'step_type' => 'confirm', 'label' => 'Wundbericht aktualisiert'],
            ],
        ],
        [
            'code' => 'AP_VITALWERTE',
            'name' => 'Vitalwerte erfassen',
            'name_i18n' => ['en' => 'Record vital signs', 'es' => 'Registrar constantes vitales', 'fr' => 'Relever les signes vitaux', 'it' => 'Rilevare i parametri vitali'],
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'description' => 'Blutdruck, Puls, Sauerstoffsättigung, Temperatur und Blutzucker mit Grenzwertprüfung und Meldung bei Abweichung.',
            'steps' => [
                ['code' => 'rrSystolisch', 'step_type' => 'number', 'label' => 'Blutdruck systolisch (mmHg)'],
                ['code' => 'rrDiastolisch', 'step_type' => 'number', 'label' => 'Blutdruck diastolisch (mmHg)'],
                ['code' => 'puls', 'step_type' => 'number', 'label' => 'Puls (Schläge/min)'],
                ['code' => 'spo2', 'step_type' => 'number', 'label' => 'Sauerstoffsättigung (%)', 'required' => false, 'blocking' => false],
                ['code' => 'temperatur', 'step_type' => 'number', 'label' => 'Temperatur (°C)', 'required' => false, 'blocking' => false],
                ['code' => 'blutzucker', 'step_type' => 'number', 'label' => 'Blutzucker (mg/dl)', 'required' => false, 'blocking' => false],
                ['code' => 'grenzwerte', 'step_type' => 'choice', 'label' => 'Grenzwerte eingehalten?'],
                ['code' => 'meldung', 'step_type' => 'confirm', 'label' => 'Bei Abweichung Ärztin/Arzt bzw. Pflegedienstleitung informiert', 'required' => false, 'blocking' => false],
            ],
        ],
        [
            'code' => 'AP_STURZ_PROTOKOLL',
            'name' => 'Sturzereignis dokumentieren',
            'name_i18n' => ['en' => 'Document fall incident', 'es' => 'Documentar una caída', 'fr' => 'Documenter une chute', 'it' => 'Documentare una caduta'],
            'domain' => 'pflege',
            'risk_level' => 'high',
            'supersedes' => ['PF_STURZPROTOKOLL'],
            'description' => 'Hergang, Verletzung, Eskalation an Ärztin/Arzt, Angehörige und Leitung sowie Gegenzeichnung im Vier-Augen-Prinzip.',
            'steps' => [
                ['code' => 'hergang', 'step_type' => 'text', 'label' => 'Sturzhergang erfassen'],
                ['code' => 'vitalwerte', 'step_type' => 'confirm', 'label' => 'Vitalwerte geprüft'],
                ['code' => 'verletzung', 'step_type' => 'choice', 'label' => 'Verletzung und Schweregrad bewerten'],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Sichtbare Verletzung fotografieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'eskalation', 'step_type' => 'choice', 'label' => 'Eskalation (Ärztin/Arzt, Angehörige, Leitung)'],
                ['code' => 'gegenzeichnung', 'step_type' => 'confirm', 'label' => 'Gegenzeichnung durch zweite Person', 'requires_second_person' => true],
                ['code' => 'unterschrift', 'step_type' => 'signature', 'label' => 'Dokumentation bestätigen', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'AP_DEKUBITUS_RISIKO',
            'name' => 'Dekubitusrisiko einschätzen (Braden)',
            'name_i18n' => ['en' => 'Pressure ulcer risk assessment (Braden)', 'es' => 'Valoración del riesgo de úlceras por presión (Braden)', 'fr' => 'Évaluation du risque d’escarres (Braden)', 'it' => 'Valutazione del rischio di decubito (Braden)'],
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'description' => 'Wiederkehrende Risikoeinschätzung nach der Braden-Skala mit Hautinspektion und Prophylaxemaßnahmen.',
            'steps' => [
                ['code' => 'braden', 'step_type' => 'number', 'label' => 'Braden-Summe (6–23)'],
                ['code' => 'risiko', 'step_type' => 'choice', 'label' => 'Risikostufe festlegen'],
                ['code' => 'haut', 'step_type' => 'confirm', 'label' => 'Hautinspektion durchgeführt'],
                ['code' => 'massnahmen', 'step_type' => 'text', 'label' => 'Prophylaktische Maßnahmen festlegen', 'required' => false, 'blocking' => false],
                ['code' => 'wiedervorlage', 'step_type' => 'confirm', 'label' => 'Nächste Einschätzung terminiert'],
            ],
        ],
        [
            'code' => 'AP_PFLEGEVISITE',
            'name' => 'Pflegevisite',
            'name_i18n' => ['en' => 'Care review', 'es' => 'Visita de supervisión', 'fr' => 'Visite de supervision', 'it' => 'Visita di supervisione'],
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'description' => 'Pflegevisite durch die Pflegedienstleitung mit Bewertung, Maßnahmen und Unterschrift.',
            'steps' => [
                ['code' => 'planung', 'step_type' => 'confirm', 'label' => 'Pflegeplanung mit dem Klienten besprechen'],
                ['code' => 'dokumentation', 'step_type' => 'confirm', 'label' => 'Pflegedokumentation geprüft'],
                ['code' => 'bewertung', 'step_type' => 'choice', 'label' => 'Pflegequalität bewerten'],
                ['code' => 'massnahmen', 'step_type' => 'text', 'label' => 'Maßnahmen festlegen', 'required' => false, 'blocking' => false],
                ['code' => 'unterschriftPdl', 'step_type' => 'signature', 'label' => 'Unterschrift Pflegedienstleitung', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'AP_BERATUNGSBESUCH_37_3',
            'name' => 'Beratungsbesuch nach § 37 Abs. 3 SGB XI',
            'name_i18n' => ['en' => 'Counselling visit (§ 37(3) SGB XI)', 'es' => 'Visita de asesoramiento (§ 37.3 SGB XI)', 'fr' => 'Visite de conseil (§ 37, al. 3 SGB XI)', 'it' => 'Visita di consulenza (§ 37 c. 3 SGB XI)'],
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'supersedes' => ['PF_BERATUNGSBESUCH_37_3'],
            'description' => 'Nachweispflichtiger Beratungsbesuch je Halbjahr (Pflegegrad 4–5 auf Wunsch je Quartal) bei Pflegegeld, mit Ergebnisbewertung 1–3, Empfehlungen und Bestätigung.',
            'steps' => [
                ['code' => 'termin', 'step_type' => 'confirm', 'label' => 'Termin in der fälligen Periode (Halbjahr bzw. Quartal) bestätigen'],
                ['code' => 'situation', 'step_type' => 'confirm', 'label' => 'Pflegesituation erheben'],
                ['code' => 'bewertung', 'step_type' => 'choice', 'label' => 'Ergebnis bewerten (1 sichergestellt, 2 Verbesserung empfohlen, 3 nicht sichergestellt)'],
                ['code' => 'empfehlungen', 'step_type' => 'text', 'label' => 'Empfehlungen festhalten', 'required' => false, 'blocking' => false],
                ['code' => 'bestaetigung', 'step_type' => 'signature', 'label' => 'Beratung bestätigen lassen', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'AP_NEUAUFNAHME_KLIENT',
            'name' => 'Neuaufnahme eines Klienten',
            'name_i18n' => ['en' => 'New client admission', 'es' => 'Alta de un nuevo cliente', 'fr' => 'Admission d’un nouveau client', 'it' => 'Presa in carico di un nuovo cliente'],
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'supersedes' => ['PF_ERSTBESUCH'],
            'description' => 'Neuaufnahme mit Stammdaten, Pflegegrad, Verträgen und Einwilligungen, Medikationsplan und Risikoassessments.',
            'steps' => [
                ['code' => 'stammdaten', 'step_type' => 'confirm', 'label' => 'Stammdaten und Ansprechpartner erfasst'],
                ['code' => 'pflegegrad', 'step_type' => 'choice', 'label' => 'Pflegegrad erfassen'],
                ['code' => 'vertrag', 'step_type' => 'confirm', 'label' => 'Pflegevertrag und Einwilligungen (auch Fotodokumentation) vorgelegt'],
                ['code' => 'medikationsplan', 'step_type' => 'confirm', 'label' => 'Medikationsplan abgeglichen'],
                ['code' => 'assessments', 'step_type' => 'confirm', 'label' => 'Risikoassessments (Sturz, Dekubitus, Ernährung) durchgeführt'],
                ['code' => 'schluessel', 'step_type' => 'confirm', 'label' => 'Schlüsselübergabe dokumentiert', 'required' => false, 'blocking' => false],
                ['code' => 'einwilligung', 'step_type' => 'signature', 'label' => 'Einwilligung unterschreiben lassen', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'AP_UEBERGABE_SCHICHT',
            'name' => 'Schichtübergabe',
            'name_i18n' => ['en' => 'Shift handover', 'es' => 'Relevo de turno', 'fr' => 'Transmission entre équipes', 'it' => 'Passaggio di consegne'],
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'description' => 'Übergabe an die nachfolgende Schicht mit Kurzbericht je Klient.',
            'steps' => [
                ['code' => 'klienten', 'step_type' => 'confirm', 'label' => 'Klientenliste der Tour durchgegangen'],
                ['code' => 'kurzbericht', 'step_type' => 'text', 'label' => 'Kurzbericht je Klient'],
                ['code' => 'offen', 'step_type' => 'text', 'label' => 'Offene Aufgaben und Auffälligkeiten', 'required' => false, 'blocking' => false],
                ['code' => 'uebernahme', 'step_type' => 'confirm', 'label' => 'Übernahme durch die nachfolgende Schicht bestätigt'],
            ],
        ],
        [
            'code' => 'AP_HYGIENE_BEGEHUNG',
            'name' => 'Hygienebegehung',
            'name_i18n' => ['en' => 'Hygiene inspection', 'es' => 'Inspección de higiene', 'fr' => 'Visite d’hygiène', 'it' => 'Ispezione igienica'],
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'description' => 'Interne Hygienebegehung als Auditcheckliste mit Mängeln und Abschluss.',
            'steps' => [
                ['code' => 'bereich', 'step_type' => 'choice', 'label' => 'Bereich (Dienststelle, Fahrzeug, Klientenhaushalt)'],
                ['code' => 'haendehygiene', 'step_type' => 'confirm', 'label' => 'Händehygiene und Desinfektionsmittel geprüft'],
                ['code' => 'flaechen', 'step_type' => 'confirm', 'label' => 'Flächen- und Materialhygiene geprüft'],
                ['code' => 'abfall', 'step_type' => 'confirm', 'label' => 'Abfall und Wäsche geprüft'],
                ['code' => 'maengel', 'step_type' => 'text', 'label' => 'Festgestellte Mängel', 'required' => false, 'blocking' => false],
                ['code' => 'abschluss', 'step_type' => 'freigabe', 'label' => 'Begehung abschließen'],
            ],
        ],
        [
            'code' => 'AP_QM_REVIEW',
            'name' => 'QM-Review (§ 113 SGB XI)',
            'name_i18n' => ['en' => 'Quality management review (§ 113 SGB XI)', 'es' => 'Revisión de gestión de calidad (§ 113 SGB XI)', 'fr' => 'Revue qualité (§ 113 SGB XI)', 'it' => 'Riesame della qualità (§ 113 SGB XI)'],
            'domain' => 'pflege',
            'risk_level' => 'normal',
            'description' => 'Wiederkehrende Durchsicht des internen Qualitätsmanagements: Kennzahlen, Vorfälle, fällige Visiten und Beratungsbesuche.',
            'steps' => [
                ['code' => 'kennzahlen', 'step_type' => 'confirm', 'label' => 'Qualitätskennzahlen und Vorfälle des Zeitraums gesichtet'],
                ['code' => 'faelligkeiten', 'step_type' => 'confirm', 'label' => 'Pflegevisiten und Beratungsbesuche auf Fälligkeit geprüft'],
                ['code' => 'massnahmen', 'step_type' => 'text', 'label' => 'Maßnahmen und Verantwortliche festlegen'],
                ['code' => 'termin', 'step_type' => 'confirm', 'label' => 'Nächstes Review terminiert'],
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
        '#btm',
        '#wunde',
        '#sturzgefaehrdet',
        '#palliativ',
        '#dekubitusrisiko',
        '#diabetes',
        '#mrsa',
        '#hausnotruf',
        '#allein-lebend',
        '#schluesselverwaltung',
        '#beratungsbesuch-faellig',
        '#mdk-pruefung',
    ],
    // 'protocol_templates': Codes aus database/data/protocol_templates.php (MVP-902).
    // 'asset_categories': Codes aus database/data/asset_categories.php (MVP-1508).
    'protocol_templates' => [
        ['code' => 'PF_PFLEGEANAMNESE'],
        ['code' => 'AP_LEISTUNGSNACHWEIS'],
        ['code' => 'AP_PFLEGEBERICHT'],
        ['code' => 'AP_WUNDBERICHT', 'supersedes' => ['PF_WUNDDOKUMENTATION']],
        ['code' => 'AP_STURZBERICHT', 'supersedes' => ['PF_STURZBERICHT']],
        ['code' => 'AP_BTM_BUCH_EINTRAG'],
        ['code' => 'AP_PFLEGEVISITE'],
        ['code' => 'AP_BERATUNG_37_3', 'supersedes' => ['PF_BERATUNGSNACHWEIS']],
        ['code' => 'AP_AERZTLICHE_ANORDNUNG'],
        ['code' => 'AP_VORFALL_MELDUNG'],
    ],
    'asset_categories' => [
        'dienstwagen',
        'dienstkleidung',
        'dienstschluessel',
        'mobilesGeraet',
        'tabletPflege',
        'pflegekoffer',
        'bzMessgeraet',
        'blutdruckmessgeraet',
        'pulsoximeter',
        'infusionspumpe',
        'btmSchrank',
        'notfallrucksack',
        'hygieneSet',
    ],
    // Pflegevertrag je Klient (§12 AK5) mit der wiederkehrenden Pflegevisite. Der
    // Beratungsbesuch nach § 37 Abs. 3 SGB XI ist seit v8 keine Vertragspflicht
    // mehr (sie galt pauschal alle drei Monate für jeden Kunden): Pflicht ist er
    // nur bei Pflegegeld, je Halbjahr — die Frist rechnet der Regelsatz
    // `home_care` (deadline_rule_sets). Der Name bleibt, damit vorhandene
    // Vorlagen nicht verdoppelt werden; deren Pflichten ändert die Installation nie.
    'contract_templates' => [
        [
            'name' => 'Pflegevertrag mit Beratungsbesuch (§ 37 Abs. 3 SGB XI)',
            'kind' => 'service',
            'title' => 'Ambulante Pflege und Beratungseinsatz',
            'term_kind' => 'open_ended',
            'notice_period_days' => 14,
            'value_period' => 'monthly',
            'obligations' => [
                ['kind' => 'review', 'title' => 'Pflegevisite', 'offset_months' => 6, 'warn_days_before' => 14, 'recurring' => true, 'recurrence_months' => 6],
            ],
        ],
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

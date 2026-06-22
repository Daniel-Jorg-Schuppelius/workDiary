<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : veranstalter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

/*
 * Branchenprofil Veranstalter / Event-Organisator (Agentur).
 *
 * Schwerpunkt: organisatorische Abwicklung vom Briefing bis zur Abrechnung.
 * Abgegrenzt von "veranstaltungstechnik" (Ton/Licht/Rigging) – hier steht die
 * Koordination von Genehmigungen, Dienstleistern und Sicherheit im Vordergrund.
 *
 * Branchenspezifika ohne eigene Klassifikations-Domäne (Genehmigungsarten,
 * Gewerke, Sicherheitskonzept) werden über die Prozedurvorlagen erzwungen –
 * die Kern-Domänen sind durch ClassificationDomain fest umrissen. "product_group"
 * trägt hier den Eventtyp, "priority" die Risiko-/Dringlichkeitsstufe.
 */
return [
    'code' => 'veranstalter',
    'label' => 'Veranstalter / Event-Organisation',
    'version' => 1,
    'classifications' => [
        'entry_type' => [
            ['code' => 'briefing', 'label' => 'Kunden-Briefing'],
            ['code' => 'konzept', 'label' => 'Konzept'],
            ['code' => 'budgetierung', 'label' => 'Budgetierung'],
            ['code' => 'locationScouting', 'label' => 'Location-Scouting'],
            ['code' => 'genehmigung', 'label' => 'Genehmigungen'],
            ['code' => 'dienstleisterBuchung', 'label' => 'Dienstleister-Buchung'],
            ['code' => 'ticketing', 'label' => 'Ticketing'],
            ['code' => 'aufbauKoordination', 'label' => 'Aufbau-Koordination'],
            ['code' => 'durchfuehrung', 'label' => 'Durchführung'],
            ['code' => 'abbauKoordination', 'label' => 'Abbau-Koordination'],
            ['code' => 'nachbereitung', 'label' => 'Nachbereitung / Abrechnung'],
            ['code' => 'zwischenfall', 'label' => 'Zwischenfall'],
        ],
        'activity' => [
            ['code' => 'konzipieren', 'label' => 'Konzipieren'],
            ['code' => 'kalkulieren', 'label' => 'Kalkulieren'],
            ['code' => 'scouten', 'label' => 'Scouten'],
            ['code' => 'beantragen', 'label' => 'Beantragen'],
            ['code' => 'beauftragen', 'label' => 'Beauftragen'],
            ['code' => 'koordinieren', 'label' => 'Koordinieren'],
            ['code' => 'betreuen', 'label' => 'Betreuen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
            ['code' => 'abrechnen', 'label' => 'Abrechnen'],
        ],
        'defect_type' => [
            ['code' => 'genehmigungFehlt', 'label' => 'Genehmigung fehlt'],
            ['code' => 'dienstleisterAusfall', 'label' => 'Dienstleister-Ausfall'],
            ['code' => 'ueberbelegung', 'label' => 'Überbelegung / Kapazität'],
            ['code' => 'technikAusfall', 'label' => 'Technik-Ausfall'],
            ['code' => 'sicherheitsvorfall', 'label' => 'Sicherheitsvorfall'],
            ['code' => 'wetterabbruch', 'label' => 'Wetterabbruch'],
            ['code' => 'zeitverzug', 'label' => 'Zeitverzug'],
            ['code' => 'budgetueberschreitung', 'label' => 'Budgetüberschreitung'],
        ],
        'root_cause' => [
            ['code' => 'planung', 'label' => 'Planung'],
            ['code' => 'behoerde', 'label' => 'Behörde'],
            ['code' => 'dienstleister', 'label' => 'Dienstleister'],
            ['code' => 'kommunikation', 'label' => 'Kommunikation'],
            ['code' => 'wetter', 'label' => 'Wetter'],
            ['code' => 'technik', 'label' => 'Technik'],
            ['code' => 'besucher', 'label' => 'Besucher'],
            ['code' => 'budget', 'label' => 'Budget'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'teilErledigt', 'label' => 'Teilweise erledigt'],
            ['code' => 'verschoben', 'label' => 'Verschoben'],
            ['code' => 'abgesagt', 'label' => 'Abgesagt'],
            ['code' => 'freigegeben', 'label' => 'Freigegeben'],
            ['code' => 'kundeInformiert', 'label' => 'Kunde informiert'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'priority' => [
            ['code' => 'niedrig', 'label' => 'Niedriges Risiko'],
            ['code' => 'mittel', 'label' => 'Mittleres Risiko'],
            ['code' => 'hoch', 'label' => 'Hohes Risiko'],
            ['code' => 'kritisch', 'label' => 'Kritisches Risiko'],
        ],
        'product_group' => [
            ['code' => 'konzert', 'label' => 'Konzert'],
            ['code' => 'festival', 'label' => 'Festival'],
            ['code' => 'messe', 'label' => 'Messe'],
            ['code' => 'firmenevent', 'label' => 'Firmenevent'],
            ['code' => 'konferenz', 'label' => 'Konferenz / Tagung'],
            ['code' => 'gala', 'label' => 'Gala'],
            ['code' => 'sportevent', 'label' => 'Sportevent'],
            ['code' => 'strassenfest', 'label' => 'Straßenfest'],
        ],
        'dienstmittel_type' => [
            ['code' => 'funkgeraet', 'label' => 'Funkgerät'],
            ['code' => 'absperrung', 'label' => 'Absperrung'],
            ['code' => 'beschilderung', 'label' => 'Beschilderung'],
            ['code' => 'kassensystem', 'label' => 'Kassen-/Ticketsystem'],
            ['code' => 'zeltpavillon', 'label' => 'Zelt / Pavillon'],
        ],
        // Gewerke = Kategorien der gebuchten Dienstleister (Auswahl/Filter je
        // Dienstleister-Buchung). Der konkrete Dienstleister liegt im Supplier-Stamm.
        'trade' => [
            ['code' => 'catering', 'label' => 'Catering'],
            ['code' => 'technik', 'label' => 'Veranstaltungstechnik'],
            ['code' => 'security', 'label' => 'Security'],
            ['code' => 'buehne', 'label' => 'Bühne / Rigging'],
            ['code' => 'kuenstler', 'label' => 'Künstler / Acts'],
            ['code' => 'ticketing', 'label' => 'Ticketing'],
            ['code' => 'sanitaet', 'label' => 'Sanitätsdienst'],
            ['code' => 'reinigung', 'label' => 'Reinigung'],
            ['code' => 'transport', 'label' => 'Transport / Logistik'],
            ['code' => 'dekoration', 'label' => 'Dekoration'],
        ],
        // Genehmigungsarten = Auswahl/Gate je Genehmigungs-Eintrag. Status,
        // Frist und Nachweis werden im Genehmigungs-Register (Permit) geführt.
        'permit_type' => [
            ['code' => 'sondernutzung', 'label' => 'Sondernutzung öffentl. Raum'],
            ['code' => 'sperrzeit', 'label' => 'Sperrzeitverkürzung'],
            ['code' => 'gema', 'label' => 'GEMA-Anmeldung'],
            ['code' => 'schankerlaubnis', 'label' => 'Schankerlaubnis'],
            ['code' => 'sicherheitskonzept', 'label' => 'Sicherheitskonzept'],
            ['code' => 'brandschutz', 'label' => 'Brandschutz'],
            ['code' => 'laermschutz', 'label' => 'Lärmschutz / Ausnahme'],
            ['code' => 'lebensmittel', 'label' => 'Lebensmittel / Gaststätte'],
        ],
    ],
    'classification_requirements' => [
        [
            // Welche Genehmigungsart wird hier bearbeitet? (mehrere zulässig)
            'entry_type_code' => 'genehmigung',
            'required_domain' => 'permit_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'allow_multi' => true,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'genehmigung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            // Welches Gewerk wird gebucht? (mehrere zulässig)
            'entry_type_code' => 'dienstleisterBuchung',
            'required_domain' => 'trade',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'allow_multi' => true,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'durchfuehrung',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'durchfuehrung',
            'required_domain' => 'priority',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'durchfuehrung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'nachbereitung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'zwischenfall',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'zwischenfall',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        [
            'code' => 'VA_GENEHMIGUNGEN',
            'name' => 'Genehmigungs- / Behördencheck',
            'domain' => 'veranstalter',
            'risk_level' => 'high',
            'description' => 'Erforderliche Genehmigungen ermitteln, beantragen und Nachweise ablegen.',
            'steps' => [
                ['code' => 'bedarfPruefen', 'step_type' => 'choice', 'label' => 'Genehmigungsbedarf ermitteln (Sondernutzung/Sperrzeit/GEMA/Schank/Lärm)'],
                ['code' => 'antraege', 'step_type' => 'confirm', 'label' => 'Anträge fristgerecht stellen'],
                ['code' => 'fristen', 'step_type' => 'confirm', 'label' => 'Bearbeitungsfristen überwachen'],
                ['code' => 'nachweise', 'step_type' => 'file', 'label' => 'Genehmigungsbescheide ablegen', 'requires_proof_type' => 'file'],
                ['code' => 'freigabe', 'step_type' => 'freigabe', 'label' => 'Genehmigungslage freigeben'],
            ],
        ],
        [
            'code' => 'VA_SICHERHEITSKONZEPT',
            'name' => 'Sicherheitskonzept',
            'domain' => 'veranstalter',
            'risk_level' => 'critical',
            'description' => 'Sicherheitskonzept inkl. Kapazität, Flucht-/Rettungswegen, Sanitäts- und Sicherheitsdienst – Freigabe im Vier-Augen-Prinzip.',
            'steps' => [
                ['code' => 'gefaehrdung', 'step_type' => 'confirm', 'label' => 'Gefährdungsbeurteilung durchführen'],
                ['code' => 'kapazitaet', 'step_type' => 'number', 'label' => 'Maximale Besucherkapazität festlegen'],
                ['code' => 'fluchtwege', 'step_type' => 'confirm', 'label' => 'Flucht-/Rettungswege prüfen'],
                ['code' => 'sanitaet', 'step_type' => 'confirm', 'label' => 'Sanitätsdienst dimensionieren/beauftragen'],
                ['code' => 'security', 'step_type' => 'confirm', 'label' => 'Sicherheitsdienst dimensionieren/beauftragen'],
                ['code' => 'dokument', 'step_type' => 'file', 'label' => 'Sicherheitskonzept ablegen', 'requires_proof_type' => 'file'],
                ['code' => 'freigabe', 'step_type' => 'signature', 'label' => 'Sicherheitskonzept freigeben', 'requires_proof_type' => 'signature', 'requires_second_person' => true],
            ],
        ],
        [
            'code' => 'VA_AUFBAU_KOORDINATION',
            'name' => 'Aufbau-Koordination',
            'domain' => 'veranstalter',
            'risk_level' => 'normal',
            'description' => 'Koordination der Gewerke beim Aufbau gegen den Ablaufplan.',
            'steps' => [
                ['code' => 'gewerkeCheck', 'step_type' => 'confirm', 'label' => 'Anwesenheit/Status der Gewerke prüfen'],
                ['code' => 'ablaufplan', 'step_type' => 'confirm', 'label' => 'Aufbau gegen Ablaufplan abgleichen'],
                ['code' => 'technikabnahme', 'step_type' => 'confirm', 'label' => 'Technik-/Bühnenabnahme bestätigen', 'required' => false, 'blocking' => false],
                ['code' => 'fotos', 'step_type' => 'photo', 'label' => 'Aufbau dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
            ],
        ],
        [
            'code' => 'VA_NACHBEREITUNG',
            'name' => 'Nachbereitung / Abrechnung',
            'domain' => 'veranstalter',
            'risk_level' => 'normal',
            'description' => 'Abbau-Abnahme, Dienstleister-Abrechnung und Kundenabnahme.',
            'steps' => [
                ['code' => 'abbauPruefen', 'step_type' => 'confirm', 'label' => 'Abbau/Rückgabe der Location prüfen'],
                ['code' => 'dienstleisterAbrechnung', 'step_type' => 'confirm', 'label' => 'Dienstleister-Leistungen abrechnen'],
                ['code' => 'kundenAbnahme', 'step_type' => 'signature', 'label' => 'Kundenabnahme dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'signature'],
            ],
        ],
        ['code' => 'VA_KONZEPT'],
        ['code' => 'VA_LOCATION_SCOUTING'],
        ['code' => 'VA_DIENSTLEISTER_BUCHUNG'],
        ['code' => 'VA_ZWISCHENFALL'],
    ],
    'room_requirement_templates_seed' => [
        ['code' => 'va_kapazitaet', 'kind' => 'other', 'label' => 'Besucherkapazität', 'note' => 'Zugelassene Höchstkapazität der Location.'],
        ['code' => 'va_fluchtwege', 'kind' => 'operatorDuty', 'label' => 'Flucht-/Rettungswege', 'note' => 'Betreiberpflicht: freie Flucht- und Rettungswege.'],
        ['code' => 'va_brandschutz', 'kind' => 'technicalInspection', 'label' => 'Brandschutzabnahme', 'note' => 'Brandschutztechnische Abnahme der Location.'],
        ['code' => 'va_zugang', 'kind' => 'accessRestriction', 'label' => 'Zutrittskontrolle', 'note' => 'Zutrittskontrolle/Akkreditierung erforderlich.'],
        ['code' => 'va_sanitaer', 'kind' => 'other', 'label' => 'Sanitärversorgung', 'note' => 'Sanitär-/Toilettenversorgung nach Besucherzahl.'],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'VA_KONZEPT'],
        ['code' => 'VA_GENEHMIGUNGSLISTE'],
        ['code' => 'VA_SICHERHEITSKONZEPT'],
        ['code' => 'VA_ABLAUFPLAN'],
        ['code' => 'VA_DIENSTLEISTERLISTE'],
        ['code' => 'VA_ABNAHME'],
        ['code' => 'VA_ZWISCHENFALLBERICHT'],
    ],
    'asset_categories' => [
        'funkgeraet',
        'absperrgitter',
        'buehnenelement',
        'zeltpavillon',
        'beschilderung',
        'kassensystem',
    ],
    'tags_seed' => [
        '#event',
        '#genehmigung',
        '#sicherheit',
        '#dienstleister',
        '#location',
        '#ticketing',
        '#durchfuehrung',
        '#zwischenfall',
    ],
];

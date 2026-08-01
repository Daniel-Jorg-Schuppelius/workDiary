<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : druck-kopiershop.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

/**
 * Branchenprofil Druckerzeugnisse / Kopiershop (MVP-459, Issue #75).
 * Fokus „Lager & Fertigung": Beratung/Datenannahme → Kalkulation →
 * Preflight/Druckfreigabe → Produktion/Weiterverarbeitung → QK →
 * Ausgabe/Versand. Die Fachakte (`print_orders`) mit Produktions-Snapshot,
 * Datei-Hash-Bindung und Preflight-Gates liegt im Code (PrintOrderService) —
 * dieses Profil liefert Klassifikationen, Pflichtfelder und Prozedurvorlagen.
 */
return [
    'code' => 'druck-kopiershop',
    'label' => 'Druckerzeugnisse / Kopiershop',
    'version' => 1,
    'description' => 'Druckereien, Anbieter von Druckerzeugnissen und Kopiershops: Datenannahme, Preflight/Druckfreigabe, Produktion mit Snapshot- und Hash-Bindung, Weiterverarbeitung, Qualitätskontrolle, Tresen-Ausgabe und Versand.',

    'modules_recommended' => [
        'module.planung',
        'module.documents',
        'module.forms',
        'module.knowledge',
        'module.lager',
        'module.versand',
        'module.claims',
    ],

    'classifications' => [
        'entry_type' => [
            ['code' => 'datenpruefung', 'label' => 'Datenprüfung'],
            ['code' => 'druckauftrag', 'label' => 'Druckauftrag'],
            ['code' => 'kopierauftrag', 'label' => 'Kopier-/Scanauftrag'],
            ['code' => 'grossformat', 'label' => 'Großformatauftrag'],
            ['code' => 'weiterverarbeitung', 'label' => 'Weiterverarbeitung'],
            ['code' => 'versandauftrag', 'label' => 'Versandauftrag'],
            ['code' => 'tresenverkauf', 'label' => 'Tresenverkauf'],
            ['code' => 'reklamation', 'label' => 'Reklamation'],
        ],
        'activity' => [
            ['code' => 'beraten', 'label' => 'Beraten'],
            ['code' => 'datenAnnehmen', 'label' => 'Daten annehmen'],
            ['code' => 'pruefen', 'label' => 'Prüfen (Preflight)'],
            ['code' => 'kalkulieren', 'label' => 'Kalkulieren'],
            ['code' => 'freigeben', 'label' => 'Freigeben'],
            ['code' => 'drucken', 'label' => 'Drucken'],
            ['code' => 'kopieren', 'label' => 'Kopieren / Scannen'],
            ['code' => 'weiterverarbeiten', 'label' => 'Weiterverarbeiten'],
            ['code' => 'kontrollieren', 'label' => 'Qualität kontrollieren'],
            ['code' => 'ausgeben', 'label' => 'Ausgeben / Übergeben'],
            ['code' => 'versenden', 'label' => 'Versenden'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'product_group' => [
            ['code' => 'visitenkarten', 'label' => 'Visitenkarten'],
            ['code' => 'flyer', 'label' => 'Flyer'],
            ['code' => 'plakate', 'label' => 'Plakate'],
            ['code' => 'broschueren', 'label' => 'Broschüren'],
            ['code' => 'geschaeftsdrucksachen', 'label' => 'Geschäftsdrucksachen'],
            ['code' => 'etiketten', 'label' => 'Etiketten'],
            ['code' => 'banner', 'label' => 'Banner / Großformat'],
            ['code' => 'fotodruck', 'label' => 'Fotodruck'],
            ['code' => 'kopienScans', 'label' => 'Kopien / Scans'],
            ['code' => 'bindungen', 'label' => 'Bindungen'],
            ['code' => 'mailings', 'label' => 'Personalisierte Mailings'],
        ],
        'defect_type' => [
            ['code' => 'datenFehlerhaft', 'label' => 'Druckdaten fehlerhaft'],
            ['code' => 'aufloesungZuGering', 'label' => 'Auflösung zu gering'],
            ['code' => 'beschnittFehlt', 'label' => 'Beschnitt/Anschnitt fehlt'],
            ['code' => 'farbabweichung', 'label' => 'Farbabweichung'],
            ['code' => 'schriftenNichtEingebettet', 'label' => 'Schriften nicht eingebettet'],
            ['code' => 'falschesMaterial', 'label' => 'Falsches Material'],
            ['code' => 'schneidfehler', 'label' => 'Schneid-/Falzfehler'],
            ['code' => 'bindungDefekt', 'label' => 'Bindung defekt'],
            ['code' => 'mengeFalsch', 'label' => 'Menge falsch'],
            ['code' => 'maschinenstoerung', 'label' => 'Maschinenstörung'],
            ['code' => 'terminUeberschritten', 'label' => 'Termin überschritten'],
        ],
        'root_cause' => [
            ['code' => 'kundendaten', 'label' => 'Kundendaten'],
            ['code' => 'datenpruefung', 'label' => 'Datenprüfung'],
            ['code' => 'bedienung', 'label' => 'Bedienung'],
            ['code' => 'maschine', 'label' => 'Maschine / Gerät'],
            ['code' => 'material', 'label' => 'Material / Bedruckstoff'],
            ['code' => 'kalibrierung', 'label' => 'Kalibrierung / Farbprofil'],
            ['code' => 'weiterverarbeitung', 'label' => 'Weiterverarbeitung'],
            ['code' => 'transport', 'label' => 'Transport / Versand'],
            ['code' => 'planung', 'label' => 'Planung / Termin'],
        ],
        'result' => [
            ['code' => 'datenOk', 'label' => 'Daten in Ordnung'],
            ['code' => 'datenKorrigiert', 'label' => 'Daten korrigiert'],
            ['code' => 'freigegeben', 'label' => 'Freigegeben'],
            ['code' => 'produziert', 'label' => 'Produziert'],
            ['code' => 'nacharbeit', 'label' => 'Nacharbeit erforderlich'],
            ['code' => 'ausgegeben', 'label' => 'Ausgegeben / Übergeben'],
            ['code' => 'versendet', 'label' => 'Versendet'],
            ['code' => 'storniert', 'label' => 'Storniert'],
        ],
        'priority' => [
            ['code' => 'standard', 'label' => 'Standard'],
            ['code' => 'express', 'label' => 'Express'],
            ['code' => 'overnight', 'label' => 'Overnight'],
            ['code' => 'sofort', 'label' => 'Sofort (Tresen)'],
        ],
        'rework_reason' => [
            ['code' => 'farbkorrektur', 'label' => 'Farbkorrektur'],
            ['code' => 'neudruck', 'label' => 'Neudruck'],
            ['code' => 'nachschnitt', 'label' => 'Nachschnitt'],
            ['code' => 'neubindung', 'label' => 'Neubindung'],
            ['code' => 'mengenergaenzung', 'label' => 'Mengenergänzung'],
        ],
        'dienstmittel_type' => [
            ['code' => 'digitaldruckmaschine', 'label' => 'Digitaldruckmaschine'],
            ['code' => 'offsetmaschine', 'label' => 'Offsetmaschine'],
            ['code' => 'grossformatdrucker', 'label' => 'Großformatdrucker'],
            ['code' => 'kopierer', 'label' => 'Kopierer / MFP'],
            ['code' => 'schneidemaschine', 'label' => 'Schneidemaschine'],
            ['code' => 'falzmaschine', 'label' => 'Falzmaschine'],
            ['code' => 'bindegeraet', 'label' => 'Bindegerät'],
            ['code' => 'laminiergeraet', 'label' => 'Laminiergerät'],
            ['code' => 'plotter', 'label' => 'Schneidplotter'],
        ],
    ],

    'classification_requirements' => [
        [
            'entry_type_code' => 'druckauftrag',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
            'note' => 'Produktgruppe bestimmt Kalkulation, Material und Weiterverarbeitung.',
        ],
        [
            'entry_type_code' => 'grossformat',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'datenpruefung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
            'note' => 'Datenprüfung endet immer mit dokumentiertem Ergebnis (ok/korrigiert).',
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
            'entry_type_code' => 'weiterverarbeitung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],

    'procedure_templates' => [
        [
            'code' => 'DR_DATEICHECK',
            'name' => 'Dateicheck / Preflight',
            'domain' => 'druck-kopiershop',
            'risk_level' => 'high',
            'description' => 'Produktionsdatei annehmen, Prüfsumme sichern, Preflight ausführen und Befunde (Fehler/Warnungen) dokumentieren.',
            'steps' => [
                ['code' => 'annahme', 'step_type' => 'confirm', 'label' => 'Datei annehmen und Prüfsumme sichern'],
                ['code' => 'preflight', 'step_type' => 'confirm', 'label' => 'Preflight ausführen (Format, Beschnitt, Auflösung, Farben, Schriften)'],
                ['code' => 'befunde', 'step_type' => 'confirm', 'label' => 'Befunde dokumentieren und Kunde informieren'],
            ],
        ],
        [
            'code' => 'DR_DRUCKFREIGABE',
            'name' => 'Proof / Druckfreigabe',
            'domain' => 'druck-kopiershop',
            'risk_level' => 'high',
            'description' => 'Freigabe bindet Person, Zeitpunkt, Datei-Hash und Produktions-Snapshot unveränderlich zusammen.',
            'steps' => [
                ['code' => 'proof', 'step_type' => 'confirm', 'label' => 'Proof/Vorschau mit Kunde abstimmen'],
                ['code' => 'parameter', 'step_type' => 'confirm', 'label' => 'Produktionsparameter (Format, Material, Menge, Farbigkeit) bestätigen'],
                ['code' => 'freigabe', 'step_type' => 'confirm', 'label' => 'Druckfreigabe dokumentieren (Hash-Bindung)'],
            ],
        ],
        [
            'code' => 'DR_PRODUKTIONSSTART',
            'name' => 'Produktionsstart / Andruck',
            'domain' => 'druck-kopiershop',
            'risk_level' => 'high',
            'description' => 'Maschinen-/Kalibrierstatus prüfen, Andruck gegen Freigabestand kontrollieren, Produktion starten.',
            'steps' => [
                ['code' => 'maschine', 'step_type' => 'dienstmittel', 'label' => 'Maschine zuweisen (Sperre/Prüfung/Kalibrierung beachten)'],
                ['code' => 'andruck', 'step_type' => 'confirm', 'label' => 'Andruck gegen Freigabestand prüfen'],
                ['code' => 'start', 'step_type' => 'confirm', 'label' => 'Produktionsstart dokumentieren'],
            ],
        ],
        [
            'code' => 'DR_WEITERVERARBEITUNG',
            'name' => 'Weiterverarbeitung',
            'domain' => 'druck-kopiershop',
            'risk_level' => 'normal',
            'description' => 'Schneiden, Falzen, Binden, Laminieren oder Konfektionieren gemäß Produktions-Snapshot.',
            'steps' => [
                ['code' => 'einrichten', 'step_type' => 'confirm', 'label' => 'Maschine/Werkzeug einrichten'],
                ['code' => 'verarbeiten', 'step_type' => 'confirm', 'label' => 'Weiterverarbeitung gemäß Snapshot ausführen'],
                ['code' => 'mengen', 'step_type' => 'confirm', 'label' => 'Gutmenge und Makulatur erfassen'],
            ],
        ],
        [
            'code' => 'DR_QUALITAETSKONTROLLE',
            'name' => 'Qualitätskontrolle',
            'domain' => 'druck-kopiershop',
            'risk_level' => 'high',
            'description' => 'Ergebnis gegen Freigabestand und Auftragsparameter prüfen; Gutbogen-, Foto- oder Messnachweis optional.',
            'steps' => [
                ['code' => 'vergleich', 'step_type' => 'confirm', 'label' => 'Ergebnis gegen Freigabestand/Parameter vergleichen'],
                ['code' => 'nachweis', 'step_type' => 'photo', 'label' => 'Gutbogen-/Fotonachweis aufnehmen', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'entscheidung', 'step_type' => 'confirm', 'label' => 'Freigabe, Sperre oder Nacharbeit dokumentieren'],
            ],
        ],
        [
            'code' => 'DR_AUSGABE_VERSAND',
            'name' => 'Ausgabe / Versand',
            'domain' => 'druck-kopiershop',
            'risk_level' => 'normal',
            'description' => 'Abholung mit Übergabenachweis oder Versand über die vorhandene Sendungslogik; Tresen-Ausgabe datensparsam.',
            'steps' => [
                ['code' => 'pruefung', 'step_type' => 'confirm', 'label' => 'Menge und Verpackung prüfen'],
                ['code' => 'uebergabe', 'step_type' => 'confirm', 'label' => 'Übergabe/Abholung oder Versandübergabe dokumentieren'],
                ['code' => 'abrechnung', 'step_type' => 'confirm', 'label' => 'Rechnung/Kasse übergeben'],
            ],
        ],
        [
            'code' => 'DR_REKLAMATION',
            'name' => 'Reklamation / Nacharbeit',
            'domain' => 'druck-kopiershop',
            'risk_level' => 'high',
            'description' => 'Reklamation mit Bezug auf Auftrag, freigegebene Datei, Snapshot, Qualitätsnachweis und betroffene Menge aufnehmen.',
            'steps' => [
                ['code' => 'aufnahme', 'step_type' => 'confirm', 'label' => 'Fehlerbild und betroffene Menge dokumentieren'],
                ['code' => 'ursache', 'step_type' => 'confirm', 'label' => 'Ursache gegen Snapshot/Freigabestand prüfen'],
                ['code' => 'massnahme', 'step_type' => 'confirm', 'label' => 'Nacharbeit/Neudruck/Gutschrift festlegen'],
            ],
        ],
    ],

    'tags_seed' => [
        '#druck', '#kopiershop', '#preflight', '#druckfreigabe', '#andruck',
        '#grossformat', '#weiterverarbeitung', '#express', '#tresen',
        '#mailing', '#neudruck',
    ],
];

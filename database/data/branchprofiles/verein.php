<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : verein.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

/*
 * Branchenprofil Sportverein (Feature 159, MVP-848).
 *
 * Die Vereinsbasis (Mitglieder, Gruppen, Termine, Anwesenheit, Beiträge,
 * Mitgliedersicht) und die Sportschnitte (Graduierung, Mannschaften,
 * Sportstätten, Reitbetrieb, Wettkampf) liegen im Modul Vereinsverwaltung;
 * die Sportarten kommen als Startpakete (database/data/club/sportpacks) über
 * die Seite „Sportarten“ — Konfiguration, kein Baustein je Sportart.
 * Klassifikationen hier betreffen das Arbeitstagebuch der Geschäftsstelle
 * und der Übungsleitung (Aufträge, Tätigkeiten, Störungen), nicht die
 * Vereinsdaten selbst.
 */
return [
    'code' => 'verein',
    'label' => 'Sportverein',
    'description' => 'Sport- und Freizeitverein: Mitglieder ohne Loginpflicht, Gruppen, Training und Termine, Anwesenheit, Beiträge mit Familienkonten, optional Graduierungen, Mannschaften, Sportstätten, Reitbetrieb und Wettkämpfe über Startpakete je Sportart.',
    'version' => 1,
    'modules_recommended' => [
        'module.club',
        'module.planung',
        'module.documents',
        'module.forms',
        'module.knowledge',
        'module.auswertungen_team',
    ],
    'nav_focus_default' => 'club',
    'classifications' => [
        'entry_type' => [
            ['code' => 'training', 'label' => 'Trainingsbetrieb'],
            ['code' => 'veranstaltung', 'label' => 'Veranstaltung / Sportfest'],
            ['code' => 'wettkampfbetreuung', 'label' => 'Wettkampfbetreuung'],
            ['code' => 'geschaeftsstelle', 'label' => 'Geschäftsstelle / Verwaltung'],
            ['code' => 'sportstaette', 'label' => 'Sportstätte / Platzpflege'],
            ['code' => 'gremium', 'label' => 'Vorstand / Gremium'],
            ['code' => 'zwischenfall', 'label' => 'Zwischenfall / Unfall'],
        ],
        'activity' => [
            ['code' => 'trainieren', 'label' => 'Trainieren / Anleiten'],
            ['code' => 'betreuen', 'label' => 'Betreuen'],
            ['code' => 'organisieren', 'label' => 'Organisieren'],
            ['code' => 'verwalten', 'label' => 'Verwalten / Abrechnen'],
            ['code' => 'pflegen', 'label' => 'Pflegen / Instandhalten'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'hallenausfall', 'label' => 'Halle / Platz nicht nutzbar'],
            ['code' => 'geraetedefekt', 'label' => 'Gerätedefekt'],
            ['code' => 'trainerausfall', 'label' => 'Übungsleitung ausgefallen'],
            ['code' => 'verletzung', 'label' => 'Verletzung'],
            ['code' => 'beitragsrueckstand', 'label' => 'Beitragsrückstand'],
        ],
        'root_cause' => [
            ['code' => 'witterung', 'label' => 'Witterung'],
            ['code' => 'wartung', 'label' => 'Wartung / Verschleiß'],
            ['code' => 'personal', 'label' => 'Personal / Ehrenamt'],
            ['code' => 'kommunikation', 'label' => 'Kommunikation'],
            ['code' => 'organisation', 'label' => 'Organisation'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'verschoben', 'label' => 'Verschoben'],
            ['code' => 'abgesagt', 'label' => 'Abgesagt'],
            ['code' => 'ersatz', 'label' => 'Ersatz organisiert'],
            ['code' => 'gemeldet', 'label' => 'Gemeldet / weitergeleitet'],
        ],
        'priority' => [
            ['code' => 'niedrig', 'label' => 'Niedrig'],
            ['code' => 'mittel', 'label' => 'Mittel'],
            ['code' => 'hoch', 'label' => 'Hoch'],
            ['code' => 'sicherheitsrelevant', 'label' => 'Sicherheitsrelevant'],
        ],
        'product_group' => [
            ['code' => 'mitgliedschaft', 'label' => 'Mitgliedschaft'],
            ['code' => 'kurs', 'label' => 'Kurs / Lehrgang'],
            ['code' => 'veranstaltung', 'label' => 'Veranstaltung'],
            ['code' => 'ausruestung', 'label' => 'Ausrüstung / Leihgeräte'],
        ],
        'dienstmittel_type' => [
            ['code' => 'sportgeraet', 'label' => 'Sportgerät'],
            ['code' => 'erstehilfe', 'label' => 'Erste-Hilfe-Ausstattung'],
            ['code' => 'fahrzeug', 'label' => 'Vereinsfahrzeug'],
            ['code' => 'schluessel', 'label' => 'Schlüssel / Zutritt'],
        ],
    ],
    'classification_requirements' => [
        [
            // Zwischenfälle brauchen eine Ursache und ein Ergebnis — weich, die Übungsleitung entscheidet.
            'entry_type_code' => 'zwischenfall',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'allow_multi' => false,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'zwischenfall',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'allow_multi' => false,
            'min_count' => 1,
        ],
    ],
    'tags_seed' => [
        '#training',
        '#sportfest',
        '#mitgliederverwaltung',
        '#hallenbelegung',
        '#jugend',
        '#ehrenamt',
    ],
    'procedure_templates' => [
        [
            'code' => 'VE_MITGLIEDSAUFNAHME',
            'name' => 'Mitgliedsaufnahme',
            'domain' => 'verein',
            'risk_level' => 'low',
            'description' => 'Aufnahmeantrag prüfen, Mitglied und Beitragskonto anlegen, Gruppe zuordnen, Willkommensinformation versenden.',
            'steps' => [
                ['code' => 'antrag', 'step_type' => 'confirm', 'label' => 'Aufnahmeantrag und Einwilligungen prüfen'],
                ['code' => 'mitglied', 'step_type' => 'confirm', 'label' => 'Mitglied im Verein anlegen (ohne Login, ggf. Vertretung)'],
                ['code' => 'beitrag', 'step_type' => 'choice', 'label' => 'Tarif wählen und Beitragskonto zuordnen'],
                ['code' => 'gruppe', 'step_type' => 'confirm', 'label' => 'Gruppe/Mannschaft zuordnen'],
                ['code' => 'willkommen', 'step_type' => 'confirm', 'label' => 'Willkommensinformation versenden'],
            ],
        ],
        [
            'code' => 'VE_SPORTFEST',
            'name' => 'Sportfest / Vereinsveranstaltung',
            'domain' => 'verein',
            'risk_level' => 'normal',
            'description' => 'Sportstätten belegen, Helfer und Terminrollen einteilen, Sicherheit und Erste Hilfe sicherstellen, Nachbereitung.',
            'steps' => [
                ['code' => 'belegung', 'step_type' => 'confirm', 'label' => 'Sportstätten und Ressourcen belegen'],
                ['code' => 'helfer', 'step_type' => 'confirm', 'label' => 'Helfer und Terminrollen einteilen'],
                ['code' => 'sicherheit', 'step_type' => 'confirm', 'label' => 'Erste Hilfe und Sicherheit sicherstellen'],
                ['code' => 'durchfuehrung', 'step_type' => 'confirm', 'label' => 'Durchführung dokumentieren'],
                ['code' => 'nachbereitung', 'step_type' => 'freigabe', 'label' => 'Nachbereitung und Abrechnung freigeben'],
            ],
        ],
    ],
];

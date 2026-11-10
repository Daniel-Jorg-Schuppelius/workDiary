<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : demo.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Inhalte der Demo-Blöcke (MVP-1532/1533). Sie werden beim Seed in der Sprache des Auslösers gespeichert.
return [
    'schedule' => [
        'shift_type' => [
            'early' => 'Frühdienst',
            'late' => 'Spätdienst',
            'office' => 'Bürodienst',
        ],
        'plan_current' => 'Dienstplan laufende und nächste Woche (Demo)',
        'plan_next' => 'Dienstplan übernächste Woche (Demo)',
        'coverage_note' => 'Im Frühdienst sind werktags mindestens zwei Personen eingeplant.',
        'exchange_reason' => 'Privater Termin am Nachmittag, Tausch mit dem Frühdienst abgesprochen.',
    ],
    'forms' => [
        'report' => [
            'name' => 'Einsatzbericht (Demo)',
            'description' => 'Kurzbericht nach jedem Einsatz beim Kunden.',
            'work_done' => 'Durchgeführte Arbeiten',
            'work_done_value' => 'Wartung nach Checkliste durchgeführt, keine Mängel festgestellt.',
            'hours_on_site' => 'Zeit vor Ort',
            'result' => 'Ergebnis',
            'result_done' => 'Erledigt',
            'result_follow_up' => 'Folgeeinsatz nötig',
            'result_material' => 'Material fehlt',
            'briefed' => 'Kunde eingewiesen',
            'satisfaction' => 'Zufriedenheit des Kunden',
        ],
        'safety' => [
            'name' => 'Gefährdungsbeurteilung vor Arbeitsbeginn (Demo)',
            'description' => 'Kurzprüfung vor Ort, bevor die Arbeit beginnt.',
            'ppe_complete' => 'Schutzausrüstung vollständig',
            'area_secured' => 'Arbeitsbereich abgesichert',
            'hazards' => 'Besondere Gefährdungen',
            'hazards_value' => 'Laufender Kundenbetrieb, Arbeitsbereich mit Absperrband gesichert.',
        ],
        'handover' => [
            'name' => 'Übergabe vor dem Urlaub (Demo)',
            'description' => 'Entwurf: offene Aufgaben und Ansprechpartner für die Vertretung.',
            'open_tasks' => 'Offene Aufgaben',
            'contact' => 'Ansprechpartner beim Kunden',
        ],
    ],
    'knowledge' => [
        'guide_title' => 'Leitfaden: :subject',
        'guide_solution' => 'Vorgehen bei der Abnahme, Punkt für Punkt mit dem Kunden durchgehen:',
        'issue_solution' => 'Termin mit dem Kunden abstimmen, Prüfung durchführen und das Ergebnis im Auftrag dokumentieren. Danach den offenen Punkt schließen.',
        'onboarding_title' => 'Erste Woche für neue Kolleginnen und Kollegen',
        'onboarding_problem' => 'Neue Mitarbeitende wissen nicht, wo Aufträge, Zeiten und Unterlagen stehen.',
        'onboarding_solution' => 'Entwurf: Rundgang durch Aufträge, Zeiterfassung und Wissensbasis; feste Ansprechperson für die ersten zwei Wochen.',
    ],
    'inventory' => [
        'warehouse_main' => 'Hauptlager (Demo)',
        'warehouse_main_note' => 'Regalreihe A–C im Erdgeschoss',
        'warehouse_vehicle' => 'Servicefahrzeug 1 (Demo)',
    ],
    'travel' => [
        'vendor' => [
            'parking' => 'Parkhaus Zentrum',
            'lodging' => 'Hotel am Markt',
            'supplies' => 'Baumarkt Muster',
            'hospitality' => 'Restaurant Beispiel',
        ],
        'expense' => [
            'parking' => 'Parken beim Kundentermin in :city',
            'hotel' => 'Übernachtung für den Einsatz in :city',
            'material' => 'Befestigungsmaterial für den Einsatz',
            'hospitality' => 'Arbeitsessen mit dem Kunden',
        ],
        'from_address' => 'Firmensitz',
        'trip_purpose' => 'Kundentermin bei :customer',
    ],
    'location' => [
        'zone_label' => ':customer (Demo-Einsatzort)',
    ],
    'contracts' => [
        'maintenance_title' => 'Wartungsvertrag :customer (Demo)',
        'maintenance_notes' => 'Monatliche Pauschale, Verlängerung um zwölf Monate ohne Kündigung.',
        'review_title' => 'Jahresgespräch zum Leistungsumfang',
        'license_title' => 'Softwarelizenz Büro (Demo)',
        'license_partner' => 'Muster-Software GmbH',
        'payment_title' => 'Jahresrechnung der Lizenz prüfen',
        'rent_title' => 'Mietvertrag Lagerfläche (Demo)',
        'rent_partner' => 'Gewerbepark Muster KG',
    ],
    'facility' => [
        'site_name' => ':customer – Hauptstandort',
        'street' => 'Musterstraße 1',
        'building' => 'Hauptgebäude',
        'floor' => [
            'ground' => 'Erdgeschoss',
            'first' => '1. Obergeschoss',
        ],
        'room' => [
            'reception' => 'Empfang',
            'technical' => 'Technikraum',
            'storage' => 'Lager',
            'office' => 'Büro',
            'meeting' => 'Besprechungsraum',
            'social' => 'Sozialraum',
        ],
        'access_note' => 'Zutritt nur mit Schlüssel vom Empfang.',
    ],
    'bau' => [
        'boq_name' => 'Leistungsverzeichnis :project (Demo)',
        'section_supply' => 'Lieferung',
        'section_labour' => 'Montage und Arbeitsleistung',
        'item_assembly' => 'Montage und Inbetriebnahme',
        'item_assembly_long' => 'Montage aller Lieferpositionen einschließlich Funktionsprüfung und Einweisung.',
        'item_hourly' => 'Stundenlohnarbeiten nach Nachweis',
        'progress_note' => 'Erstes Aufmaß nach Lieferung',
    ],
    'team' => [
        'description' => 'Arbeit im Projekt :project',
    ],
];

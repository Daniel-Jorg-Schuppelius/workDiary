<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : branch_profile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Branchenprofile: kundenspezifische Varianten (MVP-933).
return [
    'variant' => [
        'title' => 'Profilvariante',
        'list' => 'Kundenspezifische Varianten',
        'create' => 'Variante anlegen',
        'subtitle' => 'Überlagerung von „:base“ · Fassung :version',
        'empty' => 'Noch keine Varianten. Eine Variante übernimmt ein Branchenprofil, lässt Bausteine weg und ergänzt eigene.',
        'edit' => 'Bearbeiten',
        'save' => 'Variante speichern',
        'install' => 'Installieren',
        'force' => 'Bestehende Einträge aktualisieren',
        'export' => 'Als JSON exportieren',
        'delete' => 'Löschen',
        'confirm_delete' => 'Variante löschen? Bereits installierte Einträge bleiben erhalten.',
        'installed' => 'Installiert: Variante :code, Fassung :version.',
        'not_installed' => 'Für dieses Basisprofil ist noch keine Variante installiert.',
        'removals' => 'Bausteine weglassen',
        'additions' => 'Ergänzungen',
        'field' => [
            'base_code' => 'Basisprofil',
            'code' => 'Kürzel',
            'label' => 'Bezeichnung',
            'description' => 'Beschreibung',
            'additions' => 'Profilausschnitt (JSON)',
        ],
        'hint' => [
            'code' => 'Kleinbuchstaben, Ziffern und Bindestriche.',
            'removals' => 'Angehakte Bausteine des Basisprofils werden bei der Installation nicht angelegt. Bereits vorhandene Einträge bleiben unberührt.',
            'additions' => 'Aufbau wie ein Branchenprofil, z. B. {"tags_seed": ["#eigen"]}. Gleichnamige Bausteine ersetzen die des Basisprofils.',
        ],
        'section' => [
            'entry_type_defaults' => 'Standard-Eintragsarten',
            'modules_recommended' => 'Modul-Empfehlungen',
            'classifications' => 'Klassifikationen',
            'classification_requirements' => 'Pflichtregeln',
            'procedure_templates' => 'Prozedurvorlagen',
            'protocol_templates' => 'Protokollvorlagen',
            'asset_categories' => 'Objektkategorien',
            'tags_seed' => 'Tags',
            'maintenance_plans_seed' => 'Wartungspläne',
            'sla_contracts_seed' => 'SLA-Verträge',
            'dataprotection_requirements_seed' => 'Datenschutzanforderungen',
            'contract_templates' => 'Vertragsvorlagen',
            'training_suggestions' => 'Schulungsvorschläge',
            'room_requirement_templates_seed' => 'Raumanforderungen',
            'qualifications_seed' => 'Qualifikationen',
            'custom_fields' => 'Eigene Felder',
            'cleaning_profiles_seed' => 'Reinigungsprofile',
            'software_seed' => 'Software',
        ],
        'flash' => [
            'created' => 'Variante angelegt.',
            'saved' => 'Variante gespeichert.',
            'installed' => 'Variante „:label“ installiert.',
            'deleted' => 'Variante gelöscht.',
        ],
        'error' => [
            'json' => 'Die Ergänzungen müssen ein JSON-Objekt sein.',
        ],
    ],
];

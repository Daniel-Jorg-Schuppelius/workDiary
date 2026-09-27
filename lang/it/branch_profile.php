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
        'title' => 'Variante del profilo',
        'list' => 'Varianti specifiche del cliente',
        'create' => 'Crea variante',
        'subtitle' => 'Sovrapposizione di «:base» · versione :version',
        'empty' => 'Nessuna variante. Una variante riprende un profilo di settore, omette elementi e ne aggiunge di propri.',
        'edit' => 'Modifica',
        'save' => 'Salva variante',
        'install' => 'Installa',
        'force' => 'Aggiorna le voci esistenti',
        'export' => 'Esporta come JSON',
        'delete' => 'Elimina',
        'confirm_delete' => 'Eliminare la variante? Le voci già installate restano.',
        'installed' => 'Installata: variante :code, versione :version.',
        'not_installed' => 'Nessuna variante di questo profilo base è installata.',
        'removals' => 'Omettere elementi',
        'additions' => 'Aggiunte',
        'field' => [
            'base_code' => 'Profilo base',
            'code' => 'Codice',
            'label' => 'Denominazione',
            'description' => 'Descrizione',
            'additions' => 'Estratto di profilo (JSON)',
        ],
        'hint' => [
            'code' => 'Minuscole, cifre e trattini.',
            'removals' => 'Gli elementi selezionati del profilo base non vengono creati all\'installazione. Le voci esistenti restano invariate.',
            'additions' => 'Stessa struttura di un profilo di settore, ad es. {"tags_seed": ["#proprio"]}. Gli elementi con lo stesso codice sostituiscono quelli del profilo base.',
        ],
        'section' => [
            'entry_type_defaults' => 'Tipi di voce predefiniti',
            'modules_recommended' => 'Moduli consigliati',
            'classifications' => 'Classificazioni',
            'classification_requirements' => 'Regole obbligatorie',
            'procedure_templates' => 'Modelli di procedura',
            'protocol_templates' => 'Modelli di verbale',
            'asset_categories' => 'Categorie di beni',
            'tags_seed' => 'Tag',
            'maintenance_plans_seed' => 'Piani di manutenzione',
            'sla_contracts_seed' => 'Contratti SLA',
            'dataprotection_requirements_seed' => 'Requisiti di protezione dei dati',
            'contract_templates' => 'Modelli di contratto',
            'training_suggestions' => 'Proposte di formazione',
            'room_requirement_templates_seed' => 'Requisiti dei locali',
            'qualifications_seed' => 'Qualifiche',
            'custom_fields' => 'Campi personalizzati',
            'cleaning_profiles_seed' => 'Profili di pulizia',
            'software_seed' => 'Software',
        ],
        'flash' => [
            'created' => 'Variante creata.',
            'saved' => 'Variante salvata.',
            'installed' => 'Variante «:label» installata.',
            'deleted' => 'Variante eliminata.',
        ],
        'error' => [
            'json' => 'Le aggiunte devono essere un oggetto JSON.',
        ],
    ],
];

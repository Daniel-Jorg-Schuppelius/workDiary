<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : search.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Ricerca',
    'subtitle' => 'Trovare attività, clienti e oggetti — cosa è stato fatto, quando e per quale cliente?',
    'placeholder' => 'es. smtp exchange, "smtp relay", -test',

    'group' => [
        'activities' => 'Attività',
    ],

    'source' => [
        'time_entry' => 'Registrazione ore',
        'diary_entry' => 'Incarico',
        'timesheet' => 'Foglio ore',
        'service_ticket' => 'Ticket',
        'protocol' => 'Verbale',
        'open_issue' => 'Punto aperto',
        'communication_note' => 'Nota di comunicazione',
        'knowledge_article' => 'Articolo della knowledge base',
        'remote_session' => 'Teleassistenza (non assegnata)',
        'learning_course' => 'Corso di apprendimento',
    ],

    'field' => [
        'query' => 'Termine di ricerca',
        'type' => 'Fonte',
        'all_types' => 'Tutte le fonti',
        'person' => 'Persona',
        'all_persons' => 'Tutte le persone',
        'customer' => 'Cliente',
        'all_customers' => 'Tutti i clienti',
        'foreign_customer' => 'Cliente finale',
        'all_foreign_customers' => 'Tutti i clienti finali',
        'sort' => 'Ordinamento',
        'sort_relevance' => 'Migliori risultati prima',
        'sort_date' => 'Più recenti prima',
        'similar' => 'Grafie simili',
    ],

    'filter' => [
        'project' => 'Progetto: :name',
        'remove' => 'Rimuovi filtro',
    ],

    'notice' => [
        'corrections' => '«:word» non compare — cercato anche: :candidates.',
        'synonyms' => 'Cercato anche: :list',
        'ignored' => 'Non considerato: :words',
    ],

    'aggregate' => [
        'title' => 'Clienti e clienti finali',
        'without_customer' => 'senza cliente',
        'hits' => ':count risultato|:count risultati',
    ],

    'types' => [
        'title' => 'Fonti',
    ],

    'hits' => [
        'title' => 'Attività',
        'open' => 'Apri',
    ],

    'column' => [
        'date' => 'Data',
        'activity' => 'Attività',
        'customer' => 'Cliente › Cliente finale / Progetto',
        'person' => 'Persona',
        'duration' => 'Durata',
    ],

    'empty' => [
        'start' => 'Cosa stai cercando?',
        'start_hint' => 'Bastano parole chiave, es. «smtp exchange». Tutte le parole devono comparire — nella registrazione, nel progetto o nel cliente.',
        'none' => 'Nessun risultato.',
        'none_hint' => 'Prova con meno parole o attiva «Grafie simili».',
    ],

    'entities' => [
        'title' => 'Anagrafiche e oggetti',
        'more' => 'Tutti i risultati di questo gruppo →',
        'back' => '← Torna a tutti i risultati',
    ],

    'box' => [
        'title' => 'Cerca nelle attività',
        'label' => 'Termine di ricerca',
        'placeholder' => 'Parole chiave, es. smtp exchange',
        'placeholder_customer' => 'Cosa è stato fatto per questo cliente o i suoi clienti finali?',
        'placeholder_foreign_customer' => 'Cosa è stato fatto presso questo cliente finale?',
        'hint_customer' => 'Cerca in ore, incarichi, fogli ore, ticket, verbali e note del cliente e di tutti i suoi clienti finali. Senza termine di ricerca compaiono le attività più recenti.',
        'hint_foreign_customer' => 'Cerca in tutte le attività presso questo cliente finale. Senza termine di ricerca compaiono le più recenti.',
        'submit' => 'Cerca',
        'project_action' => 'Cerca nelle attività',
    ],

    'open' => [
        'range_set' => 'Periodo impostato su :date affinché la voce compaia nell’elenco.',
    ],

    'palette' => [
        'placeholder' => 'Cerca attività, clienti, progetti, oggetti …',
    ],

    'ai' => [
        'action' => 'Risposta IA',
        'source_hint' => 'Ricerca «:query» · :count risultati',
        'customer_alias' => 'Cliente :letter',
        'no_hits' => 'Nessun risultato da riassumere.',
    ],

    'synonyms' => [
        'title' => 'Sinonimi di ricerca',
        'subtitle' => 'Termini con lo stesso significato: chi ne cerca uno trova anche gli altri.',
        'notice' => 'Esempio: se «smtp, mailrelay, sendeconnector» formano un gruppo, la ricerca di «smtp» trova anche le registrazioni che citano solo «Sendeconnector». Vale per tutta l’organizzazione.',
        'legend' => 'Gruppo di sinonimi',
        'terms_help' => 'Un termine per riga (o separati da virgole), almeno due, al massimo 20. Sono ammessi termini di più parole come «send connector».',
        'empty' => 'Ancora nessun gruppo di sinonimi.',
        'delete_confirm' => 'Eliminare questo gruppo di sinonimi? La ricerca non troverà più i termini l’uno tramite l’altro.',
        'field' => [
            'terms' => 'Termini',
            'creator' => 'Creato da',
            'active' => 'Attivo',
            'enabled_yes' => 'Sì',
            'enabled_no' => 'No',
        ],
        'action' => [
            'new' => 'Crea gruppo',
            'edit' => 'Modifica gruppo',
            'submit' => 'Salva',
            'activate' => 'Attiva',
            'deactivate' => 'Disattiva',
            'delete' => 'Elimina',
            'preset_it' => 'Importa modello IT',
        ],
        'flash' => [
            'saved' => 'Gruppo di sinonimi creato.',
            'updated' => 'Gruppo di sinonimi aggiornato.',
            'deleted' => 'Gruppo di sinonimi eliminato.',
            'activated' => 'Gruppo di sinonimi attivato.',
            'deactivated' => 'Gruppo di sinonimi disattivato.',
            'preset_imported' => '{0} Tutti i gruppi del modello esistono già.|{1} :count gruppo importato dal modello.|[2,*] :count gruppi importati dal modello.',
        ],
        'validation' => [
            'min_terms' => 'Un gruppo richiede almeno due termini diversi.',
            'max_terms' => 'Al massimo :max termini per gruppo.',
            'term_length' => 'Un termine può avere al massimo :max caratteri.',
        ],
    ],
];

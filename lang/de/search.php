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
    'title' => 'Suche',
    'subtitle' => 'Tätigkeiten, Kunden und Objekte finden — was wurde wann bei welchem Kunden gemacht?',
    'placeholder' => 'z. B. smtp exchange, "smtp relay", -test',

    'group' => [
        'activities' => 'Tätigkeiten',
    ],

    'source' => [
        'time_entry' => 'Zeiteintrag',
        'diary_entry' => 'Auftrag',
        'timesheet' => 'Stundenzettel',
        'service_ticket' => 'Ticket',
        'protocol' => 'Protokoll',
        'open_issue' => 'Offener Punkt',
        'communication_note' => 'Kommunikationsnotiz',
        'knowledge_article' => 'Wissensartikel',
        'remote_session' => 'Fernwartung (nicht zugeordnet)',
        'learning_course' => 'Lernkurs',
    ],

    'field' => [
        'query' => 'Suchbegriff',
        'type' => 'Quelle',
        'all_types' => 'Alle Quellen',
        'person' => 'Person',
        'all_persons' => 'Alle Personen',
        'customer' => 'Kunde',
        'all_customers' => 'Alle Kunden',
        'foreign_customer' => 'Endkunde',
        'all_foreign_customers' => 'Alle Endkunden',
        'sort' => 'Sortierung',
        'sort_relevance' => 'Beste Treffer zuerst',
        'sort_date' => 'Neueste zuerst',
        'similar' => 'Ähnliche Schreibweisen',
        'collection' => 'Sammlung',
        'all_collections' => 'Alle Sammlungen',
    ],

    'filter' => [
        'project' => 'Projekt: :name',
        'remove' => 'Filter entfernen',
        'tag' => 'Schlagwort: :name',
        'tag_without_hits' => 'Schlagwort-Filter',
    ],

    'notice' => [
        'corrections' => '„:word“ kommt nicht vor — gesucht wurde auch nach: :candidates.',
        'synonyms' => 'Auch gesucht: :list',
        'ignored' => 'Nicht berücksichtigt: :words',
    ],

    'aggregate' => [
        'title' => 'Kunden & Endkunden',
        'without_customer' => 'ohne Kundenbezug',
        'hits' => ':count Treffer|:count Treffer',
    ],

    'types' => [
        'title' => 'Quellen',
    ],

    'facets' => [
        'tags' => 'Schlagwörter in den Treffern',
    ],

    'hits' => [
        'title' => 'Tätigkeiten',
        'open' => 'Öffnen',
    ],

    'column' => [
        'date' => 'Datum',
        'activity' => 'Tätigkeit',
        'customer' => 'Kunde › Endkunde / Projekt',
        'person' => 'Person',
        'duration' => 'Dauer',
    ],

    'empty' => [
        'start' => 'Wonach suchst du?',
        'start_hint' => 'Stichwörter reichen, z. B. „smtp exchange“. Alle Wörter müssen vorkommen — egal ob im Eintrag, im Projekt oder beim Kunden.',
        'none' => 'Keine Treffer.',
        'none_hint' => 'Weniger Wörter versuchen oder „Ähnliche Schreibweisen“ einschalten.',
    ],

    'entities' => [
        'title' => 'Stammdaten & Objekte',
        'more' => 'Alle Treffer dieser Gruppe →',
        'back' => '← Zurück zu allen Treffern',
    ],

    'box' => [
        'title' => 'Tätigkeiten durchsuchen',
        'label' => 'Suchbegriff',
        'placeholder' => 'Stichwörter, z. B. smtp exchange',
        'placeholder_customer' => 'Was wurde für diesen Kunden oder seine Endkunden gemacht?',
        'placeholder_foreign_customer' => 'Was wurde bei diesem Endkunden gemacht?',
        'hint_customer' => 'Durchsucht Zeiten, Aufträge, Stundenzettel, Tickets, Protokolle und Notizen des Kunden und aller seiner Endkunden. Ohne Suchwort erscheinen die neuesten Tätigkeiten.',
        'hint_foreign_customer' => 'Durchsucht alle Tätigkeiten bei diesem Endkunden. Ohne Suchwort erscheinen die neuesten.',
        'submit' => 'Suchen',
        'project_action' => 'Tätigkeiten durchsuchen',
    ],

    'open' => [
        'range_set' => 'Zeitraum auf den :date gesetzt, damit der Eintrag in der Liste steht.',
    ],

    'palette' => [
        'placeholder' => 'Suche nach Tätigkeiten, Kunden, Projekten, Objekten …',
    ],

    'ai' => [
        'action' => 'KI-Antwort',
        'source_hint' => 'Suche „:query“ · :count Treffer',
        'customer_alias' => 'Kunde :letter',
        'no_hits' => 'Es gibt keine Treffer, die sich zusammenfassen ließen.',
    ],

    'synonyms' => [
        'title' => 'Such-Synonyme',
        'subtitle' => 'Gleichbedeutende Begriffe: Wer einen davon sucht, findet auch die anderen.',
        'notice' => 'Beispiel: Stehen „smtp, mailrelay, sendeconnector“ in einer Gruppe, findet die Suche nach „smtp“ auch Einträge, in denen nur „Sendeconnector“ steht. Gilt für die ganze Organisation.',
        'legend' => 'Synonymgruppe',
        'terms_help' => 'Ein Begriff je Zeile (oder durch Komma getrennt), mindestens zwei, höchstens 20. Mehrwortbegriffe wie „send connector“ sind erlaubt.',
        'empty' => 'Noch keine Synonymgruppen.',
        'delete_confirm' => 'Diese Synonymgruppe löschen? Die Suche findet die Begriffe danach nicht mehr gegenseitig.',
        'field' => [
            'terms' => 'Begriffe',
            'creator' => 'Angelegt von',
            'active' => 'Aktiv',
            'enabled_yes' => 'Ja',
            'enabled_no' => 'Nein',
        ],
        'action' => [
            'new' => 'Gruppe anlegen',
            'edit' => 'Gruppe bearbeiten',
            'submit' => 'Speichern',
            'activate' => 'Aktivieren',
            'deactivate' => 'Deaktivieren',
            'delete' => 'Löschen',
            'preset_it' => 'IT-Vorlage übernehmen',
        ],
        'flash' => [
            'saved' => 'Synonymgruppe angelegt.',
            'updated' => 'Synonymgruppe aktualisiert.',
            'deleted' => 'Synonymgruppe gelöscht.',
            'activated' => 'Synonymgruppe aktiviert.',
            'deactivated' => 'Synonymgruppe deaktiviert.',
            'preset_imported' => '{0} Alle Gruppen der Vorlage sind bereits vorhanden.|{1} :count Gruppe aus der Vorlage übernommen.|[2,*] :count Gruppen aus der Vorlage übernommen.',
        ],
        'validation' => [
            'min_terms' => 'Eine Gruppe braucht mindestens zwei verschiedene Begriffe.',
            'max_terms' => 'Höchstens :max Begriffe je Gruppe.',
            'term_length' => 'Ein Begriff darf höchstens :max Zeichen lang sein.',
        ],
    ],
];

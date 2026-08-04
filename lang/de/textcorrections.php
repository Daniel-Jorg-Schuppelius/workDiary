<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : textcorrections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Wörterbuch',
        'subtitle' => 'Schreibfehler-Korrekturen (falsch → richtig), die automatisch auf generierte Positionstexte wirken — die erfassten Zeiteinträge bleiben unverändert.',
    ],

    'notice' => 'Einträge wirken automatisch beim Aufbau der Positionstexte in Übergaben und Rechnungsentwürfen (ganzes Wort, Groß-/Kleinschreibung wird übernommen). Die Originaltexte der Zeiteinträge werden nie verändert.',
    'search_placeholder' => 'Suchen (falsch/richtig) …',
    'legend' => 'Wörterbuch-Eintrag',
    'empty' => 'Keine Wörterbuch-Einträge vorhanden',
    'delete_confirm' => 'Diesen Wörterbuch-Eintrag löschen? Die Korrektur wirkt danach nicht mehr.',
    'wrong_placeholder' => 'z. B. serverwartunng',
    'wrong_help' => 'Falsch geschriebenes Wort oder Phrase — Treffer nur als ganzes Wort, Groß-/Kleinschreibung egal.',
    'correct_placeholder' => 'z. B. Serverwartung',
    'correct_help' => 'Richtige Schreibweise — sie ersetzt das Falschwort in allen generierten Positionstexten.',

    'field' => [
        'wrong' => 'Falsch',
        'correct' => 'Richtig',
        'origin' => 'Herkunft',
        'origin_manual' => 'Manuell',
        'origin_learned' => 'Gelernt',
        'usage' => 'Verwendet',
        'active' => 'Aktiv',
        'enabled_yes' => 'Ja',
        'enabled_no' => 'Nein',
    ],

    'action' => [
        'new' => 'Eintrag anlegen',
        'edit' => 'Eintrag bearbeiten',
        'submit' => 'Speichern',
        'activate' => 'Aktivieren',
        'deactivate' => 'Deaktivieren',
        'delete' => 'Löschen',
    ],

    'flash' => [
        'saved' => 'Wörterbuch-Eintrag angelegt.',
        'updated' => 'Wörterbuch-Eintrag aktualisiert.',
        'deleted' => 'Wörterbuch-Eintrag gelöscht.',
        'activated' => 'Wörterbuch-Eintrag aktiviert.',
        'deactivated' => 'Wörterbuch-Eintrag deaktiviert.',
        'learned' => 'Korrektur ins Wörterbuch übernommen.',
        'duplicate_updated' => 'Eintrag existierte bereits und wurde aktualisiert.',
        'invalid' => 'Falsch und richtig dürfen nicht identisch sein.',
    ],

    'validation' => [
        'duplicate' => 'Für dieses Falschwort existiert bereits ein Eintrag.',
    ],

    'learn' => [
        'title' => 'Korrektur merken?',
        'question' => 'In der Bearbeitung wurden Wortkorrekturen erkannt. Ins Wörterbuch übernehmen, damit sie künftig automatisch angewandt werden?',
        'confirm' => 'Merken',
        'dismiss' => 'Nicht merken',
    ],
];

<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : knowledge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Wissensbasis',
        'links' => 'Problemhistorie',
        'linked' => 'Verknüpfte Artikel',
        'suggestions' => 'Vorschläge',
    ],

    'subtitle' => 'Bekannte Probleme, Lösungsschritte und interne Hinweise aus dem Tagesgeschäft.',

    'field' => [
        'title' => 'Titel',
        'category' => 'Kategorie',
        'tags' => 'Tags',
        'status' => 'Status',
        'problem' => 'Problembeschreibung',
        'solution' => 'Lösungsschritte',
        'helpful' => 'Bewertung',
        'creator' => 'Erfasst von',
        'published_at' => 'Veröffentlicht am',
        'updated_at' => 'Zuletzt geändert',
    ],

    'action' => [
        'create' => 'Artikel anlegen',
        'create_from_subject' => 'Artikel hieraus erstellen',
        'edit' => 'Bearbeiten',
        'save' => 'Speichern',
        'show' => 'Ansehen',
        'publish' => 'Veröffentlichen',
        'archive' => 'Archivieren',
        'delete' => 'Löschen',
        'link' => 'Verknüpfen',
        'unlink' => 'Verknüpfung lösen',
        'back' => 'Zurück',
    ],

    'filter' => [
        'all' => 'Alle',
        'search' => 'Suche',
        'search_placeholder' => 'Titel, Problem oder Lösung durchsuchen',
        'sort' => 'Sortierung',
        'sort_newest' => 'Neueste zuerst',
        'sort_helpful' => 'Hilfreichste zuerst',
    ],

    'feedback' => [
        'title' => 'War dieser Artikel hilfreich?',
        'helpful' => 'Hat geholfen',
        'not_helpful' => 'Hat nicht geholfen',
        'already_voted' => 'Du hast bereits abgestimmt — eine erneute Wahl ändert deine Stimme.',
    ],

    'link_kind' => [
        'diary' => 'Auftrag',
        'asset' => 'Asset',
        'customer' => 'Kunde',
        'protocol' => 'Protokoll',
    ],

    'hint' => [
        'category' => 'z. B. Drucker, Netzwerk, Heizung …',
        'tags' => 'Komma-getrennt, z. B. firmware, modell-x',
        'problem' => 'Welches Fehlerbild/Problem tritt auf?',
        'solution' => 'Welche Schritte führen zur Lösung?',
    ],

    'flash' => [
        'created' => 'Artikel wurde angelegt.',
        'updated' => 'Artikel wurde aktualisiert.',
        'published' => 'Artikel wurde veröffentlicht.',
        'archived' => 'Artikel wurde archiviert.',
        'deleted' => 'Artikel wurde gelöscht.',
        'feedback_saved' => 'Danke für deine Bewertung.',
        'linked' => 'Artikel wurde verknüpft.',
        'unlinked' => 'Verknüpfung wurde gelöst.',
    ],

    'empty' => 'Noch keine Wissensartikel vorhanden.',
    'empty_title' => 'Keine Artikel gefunden',
    'empty_filtered' => 'Für die aktuellen Filter wurden keine Artikel gefunden.',
    'empty_links' => 'Noch keine Verknüpfungen vorhanden.',
    'empty_context' => 'Keine verknüpften Artikel und keine passenden Vorschläge.',
    'confirm_archive' => 'Artikel wirklich archivieren? Er fällt damit aus Suche und Vorschlägen.',
    'confirm_delete' => 'Artikel wirklich löschen?',
    'confirm_unlink' => 'Verknüpfung wirklich lösen?',
];

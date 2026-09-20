<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : collections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Sammlungen (MVP-809, Feature 155).
return [
    'title' => [
        'index' => 'Sammlungen',
        'tree' => 'Sammlungsbaum',
    ],
    'subtitle' => 'Notizen, Ideenkarten, Wissensartikel, Dokumente und Lerninhalte gemeinsam ordnen — ein Inhalt darf in mehreren Sammlungen liegen.',
    'action' => [
        'show_archived' => 'Archivierte einblenden',
        'hide_archived' => 'Archivierte ausblenden',
        'create' => 'Sammlung anlegen',
        'create_child' => 'Untersammlung anlegen',
        'edit' => 'Sammlung bearbeiten',
        'save' => 'Speichern',
        'archive' => 'Archivieren',
        'restore' => 'Wiederherstellen',
        'remove_item' => 'Aus der Sammlung entfernen',
        'add_to_collection' => 'Zur Sammlung hinzufügen',
        'add' => 'Hinzufügen',
    ],
    'empty' => [
        'tree' => 'Noch keine Sammlung angelegt.',
        'selection' => 'Keine Sammlung gewählt.',
        'items' => 'Diese Sammlung ist leer — oder enthält nur Inhalte, die Sie nicht sehen dürfen.',
    ],
    'help' => [
        'intro' => 'Eine Sammlung ordnet Inhalte, sie gibt keinen Zugriff: Jeder sieht darin nur, was er auch sonst sehen darf.',
        'add_from_detail' => 'Inhalte legen Sie über „Zur Sammlung hinzufügen“ auf deren Detailseite hinein.',
        'parent' => 'Höchstens :max Ebenen tief.',
        'visibility' => 'Privat sieht nur, wer die Sammlung angelegt hat.',
        'create_first' => 'Legen Sie zuerst unter „Sammlungen“ eine Sammlung an.',
        'multiple_membership' => 'Ein Inhalt darf in mehreren Sammlungen liegen, ohne Kopie.',
    ],
    'visibility' => [
        'organization' => 'Organisation',
        'private' => 'Privat',
    ],
    'badge' => [
        'archived' => 'Archiviert',
        'already_in' => 'bereits enthalten',
    ],
    'field' => [
        'type' => 'Art',
        'title' => 'Titel',
        'added_by' => 'Hinzugefügt',
        'actions' => 'Aktionen',
        'description' => 'Beschreibung',
        'parent' => 'Obersammlung',
        'no_parent' => '— oberste Ebene —',
        'visibility' => 'Sichtbarkeit',
        'subject' => 'Bezug',
        'customer' => 'Kunde',
        'collection' => 'Sammlung',
    ],
    'flash' => [
        'created' => 'Sammlung angelegt.',
        'updated' => 'Sammlung gespeichert.',
        'archived' => 'Sammlung archiviert.',
        'restored' => 'Sammlung wiederhergestellt.',
        'item_added' => 'In „:collection“ aufgenommen.',
        'item_removed' => 'Aus der Sammlung entfernt.',
        'items_added' => '{0} Keine Inhalte aufgenommen.|{1} Ein Inhalt in „:collection“ aufgenommen.|[2,*] :count Inhalte in „:collection“ aufgenommen.',
    ],
    'errors' => [
        'too_deep' => 'Sammlungen lassen sich höchstens :max Ebenen tief schachteln.',
        'cycle' => 'Eine Sammlung kann nicht unter sich selbst oder einer ihrer Untersammlungen liegen.',
        'type_not_allowed' => 'Diese Art von Inhalt lässt sich nicht in eine Sammlung legen.',
        'item_not_found' => 'Der Inhalt existiert nicht oder ist für Sie nicht sichtbar.',
        'parent_invalid' => 'Die gewählte Obersammlung gibt es nicht (mehr).',
    ],
    'type' => [
        'note' => 'Notiz',
        'idea_map' => 'Ideenlandkarte',
        'knowledge_article' => 'Wissensartikel',
        'document' => 'Dokument',
        'learning_course' => 'Lernkurs',
        'learning_path' => 'Lernpfad',
    ],
    'references' => [
        'title' => 'Verweise',
        'outgoing' => 'Verweist auf',
        'backlinks' => 'Hier erwähnt in',
        'action' => [
            'create' => 'Verweis setzen',
            'remove' => 'Verweis lösen',
        ],
        'field' => [
            'target' => 'Ziel',
            'search' => 'Inhalt suchen …',
        ],
        'kind' => [
            'linked' => 'verknüpft',
            'converted' => 'überführt',
            'mentioned' => 'erwähnt',
        ],
        'empty' => 'Noch keine Verweise – weder von hier noch hierher.',
        'empty_picker' => 'Keine weiteren Inhalte, auf die Sie verweisen können.',
        'help' => 'Ein Verweis verbindet zwei Inhalte, ohne Zugriff zu geben: Die Gegenseite sieht nur, wer sie auch sonst öffnen darf.',
        'confirm_remove' => 'Verweis lösen? Beide Inhalte bleiben erhalten.',
        'flash' => [
            'added' => 'Verweis auf „:title“ gesetzt.',
            'removed' => 'Verweis gelöst.',
        ],
        'errors' => [
            'self' => 'Ein Inhalt kann nicht auf sich selbst verweisen.',
            'not_removable' => 'Diesen Verweis pflegt das Fachmodul, nicht diese Liste.',
        ],
    ],
    'hub' => [
        'title' => 'Wissen',
        'subtitle' => 'Notizen, Ideenlandkarten, Wissensartikel, Dokumente und Lerninhalte an einer Stelle — geordnet nach Sammlungen und Schlagwörtern.',
        'search' => 'Titel durchsuchen …',
        'all_types' => 'Alle Arten',
        'all_customers' => 'Alle Kunden',
        'all_contents' => 'Alle Inhalte',
        'view_list' => 'Liste',
        'view_tiles' => 'Kacheln',
        'manage' => 'Sammlungen verwalten',
        'tag_filter' => 'Schlagwort-Filter',
        'selected' => ':n Inhalte ausgewählt',
        'select_all' => 'Alle auswählen',
        'select_item' => '„:title“ auswählen',
        'updated' => 'Geändert',
        'empty' => 'Keine Inhalte gefunden.',
        'empty_hint' => 'Filter lockern oder eine andere Sammlung wählen.',
        'add_hits' => 'In Sammlung legen',
    ],
    'import' => [
        'untitled' => 'Ohne Titel',
        'source' => 'Quelle',
        'target' => 'Übernehmen als',
        'root_title' => 'Name der Sammlung',
        'root_title_hint' => 'Leer: Name des Ordners bzw. Notizbuchs. Eine gleichnamige Sammlung wird mitbenutzt.',
        'rules' => 'Nur lesend und nur auf Anstoß: Schon übernommene Inhalte bleiben unverändert, es wird nichts zurückgeschrieben. Höchstens :max neue Inhalte je Lauf — ein weiterer Lauf übernimmt den Rest.',
        'origin' => 'Übernommen aus :source am :date',
        'action' => [
            'start' => 'Übernahme starten',
        ],
        'obsidian' => [
            'title' => 'Aus Obsidian übernehmen',
            'action' => 'Obsidian übernehmen',
            'intro' => 'Liest einen Obsidian-Ordner über eine vorhandene Ordner-Anbindung des Dokumenteingangs (Nextcloud, OneDrive, Dropbox, Google Drive). Unterordner werden Sammlungen, YAML-Schlagwörter und #schlagwörter wandern mit, [[Wikilinks]] werden Verweise.',
            'none' => 'Keine aktive Ordner-Anbindung. Richten Sie zuerst unter Administration › Cloud-Dokumenteingang eine Anbindung ein, die den Ordner mit dem Obsidian-Tresor erreicht.',
            'connection' => 'Ordner-Anbindung',
            'vault_path' => 'Pfad des Tresors',
            'vault_path_hint' => 'Relativ zum Stammordner der Anbindung; leer = der ganze Stammordner. .obsidian/ und .trash/ bleiben außen vor.',
        ],
        'onenote' => [
            'title' => 'Aus OneNote übernehmen',
            'action' => 'OneNote übernehmen',
            'intro' => 'Übernimmt ein Notizbuch über die lesende OneNote-Verbindung: Abschnittsgruppen und Abschnitte werden Sammlungen, jede Seite eine Notiz oder ein Artikel. Der Seiteninhalt wird als Text übernommen.',
            'none' => 'Keine Notizbücher gefunden.',
            'error' => 'OneNote ist gerade nicht erreichbar — bitte die Verbindung im Microsoft-365-Panel prüfen.',
            'notebook' => 'Notizbuch',
        ],
        'flash' => [
            'done' => 'Übernommen: :created neu, :skipped schon vorhanden, :collections Sammlungen angelegt, :links Verweise gesetzt.',
            'limited' => 'Obergrenze von :max neuen Inhalten erreicht — ein weiterer Lauf übernimmt den Rest.',
            'failed' => 'Die Übernahme ist fehlgeschlagen. Bereits übernommene Inhalte bleiben erhalten; ein weiterer Lauf setzt fort.',
            'source_unavailable' => 'Die Quelle ist nicht (mehr) verfügbar.',
            'notebook_invalid' => 'Das gewählte Notizbuch ist nicht (mehr) verfügbar.',
        ],
    ],
];

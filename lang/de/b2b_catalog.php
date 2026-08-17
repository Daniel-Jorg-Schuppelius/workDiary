<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : b2b_catalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// B2B-Katalogzugang (Feature 099): OCI-Punchout-Ausgang + openTRANS-Auftragseingang.
return [
    'title' => 'B2B-Katalogzugang',
    'intro' => 'Einkaufssysteme Ihrer B2B-Kunden springen per OCI 4.0 in den freigegebenen Artikelkatalog ab und senden Bestellungen als openTRANS 2.1 ORDER zurück.',
    'punchout_url' => 'Punchout-URL (für das Einkaufssystem des Kunden)',

    'access_new_heading' => 'Neuen Zugang ausstellen',
    'access_new_hint' => 'Je Kunde ein Zugang: Benutzername + Secret für den OCI-Absprung. Das Secret wird nur einmal angezeigt.',
    'access_heading' => 'Punchout-Zugänge',
    'access_empty' => 'Noch keine Zugänge ausgestellt.',
    'access_title' => 'Zugang: :label',

    'new_secret_heading' => 'Neues Punchout-Secret',
    'new_secret_hint' => 'Jetzt kopieren und im Einkaufssystem des Kunden hinterlegen — der Klartext wird nur dieses eine Mal angezeigt.',

    'items_heading' => 'Freigegebene Artikel',
    'items_hint' => 'Nur explizit freigegebene Artikel sind im Punchout sichtbar. Ohne Kundenpreis gilt der Standard-Verkaufspreis.',
    'items_empty' => 'Noch keine Artikel freigegeben.',

    'orders_heading' => 'openTRANS-Bestellungen',
    'orders_hint' => 'Bestellungen (Upload, Mail- oder Cloud-Eingang) erscheinen als Vorschlag in der Zuordnungs-Inbox; erst die Buchung erzeugt den Auftrag.',
    'orders_empty' => 'Noch keine Bestellungen eingegangen.',

    'field' => [
        'customer' => 'Kunde',
        'customer_placeholder' => '… Kunde auswählen',
        'label' => 'Bezeichnung',
        'username' => 'Benutzername',
        'items_count' => 'Artikel',
        'last_used' => 'Zuletzt genutzt',
        'status' => 'Status',
        'actions' => 'Aktionen',
        'article' => 'Artikel',
        'article_placeholder' => '… Artikel auswählen',
        'article_number' => 'Artikelnummer',
        'article_name' => 'Artikel',
        'default_price' => 'Standardpreis',
        'custom_price' => 'Kundenpreis',
        'custom_price_placeholder' => 'Standard',
        'order_id' => 'Bestellnummer',
        'source' => 'Kanal',
        'total_net' => 'Netto gesamt',
        'ordered_at' => 'Bestelldatum',
    ],

    'action' => [
        'datanorm' => 'DATPREIS exportieren',
        'issue' => 'Zugang ausstellen',
        'manage' => 'Verwalten',
        'revoke' => 'Deaktivieren',
        'rotate' => 'Secret rotieren',
        'back' => 'Zurück zur Übersicht',
        'release' => 'Artikel freigeben',
        'remove' => 'Entfernen',
        'upload_order' => 'Bestellung hochladen',
    ],

    'status' => [
        'active' => 'Aktiv',
        'revoked' => 'Deaktiviert',
        'order_open' => 'Offen (Inbox)',
        'order_booked' => 'Gebucht',
        'order_dismissed' => 'Verworfen',
    ],

    'flash' => [
        'datanorm_empty' => 'Keine freigegebenen Artikel mit Preis für diesen Zugang.',
        'datanorm_revoked' => 'Dieser Zugang ist widerrufen — es werden keine Kundenpreislisten mehr exportiert.',
        'access_issued' => 'Zugang ausgestellt.',
        'access_rotated' => 'Secret rotiert.',
        'access_revoked' => 'Zugang deaktiviert.',
        'item_released' => 'Artikel freigegeben.',
        'item_removed' => 'Freigabe entfernt.',
        'order_received' => 'Bestellung :id übernommen — Vorschlag liegt in der Zuordnungs-Inbox.',
        'order_duplicate' => 'Bestellung :id ist bereits erfasst (keine Änderung).',
    ],

    'error' => [
        'not_opentrans' => 'Die Datei ist keine lesbare openTRANS-2.1-ORDER: :reason',
        'customer_required' => 'Bitte einen Kunden auswählen.',
        'not_open' => 'Die Bestellung ist nicht mehr offen.',
    ],

    'order' => [
        'entry_title' => 'Bestellung :id',
        'entry_intro' => 'openTRANS-Bestellung :id (Kanal: :source).',
        'line_unmatched' => 'Artikel nicht zugeordnet',
    ],

    'copper_surcharge_position' => 'Kupferzuschlag (Tagespreis) zu Artikel :number',
    'copper_surcharge_label' => 'Kupferzuschlag je Einheit',
    'public' => [
        'title' => 'B2B-Katalog',
        'footer' => 'Punchout-Katalog — Warenkorb wird an Ihr Einkaufssystem übergeben, die Bestellung läuft über Ihr eigenes System.',
        'search_placeholder' => 'Artikelnummer oder Bezeichnung …',
        'search' => 'Suchen',
        'empty' => 'Keine freigegebenen Artikel gefunden.',
        'col_number' => 'Artikelnr.',
        'col_name' => 'Bezeichnung',
        'col_unit' => 'Einheit',
        'col_price' => 'Preis',
        'col_quantity' => 'Menge',
        'page_of' => 'Seite :current von :last',
        'prev' => 'Zurück',
        'next' => 'Weiter',
        'to_cart' => 'Warenkorb übergeben',
        'transfer_title' => 'Übergabe an das Einkaufssystem',
        'transfer_hint' => 'Der Warenkorb wird an Ihr Einkaufssystem übertragen. Falls die Weiterleitung nicht automatisch startet, nutzen Sie den Button.',
        'transfer_submit' => 'Warenkorb jetzt übertragen',
        'error_title' => 'Katalogzugang',
        'error_hook_url' => 'Ungültige HOOK_URL — es sind nur HTTPS-Adressen zulässig.',
        'error_credentials' => 'Zugangsdaten ungültig oder Zugang deaktiviert.',
        'error_session' => 'Die Katalog-Sitzung ist abgelaufen. Bitte erneut aus dem Einkaufssystem abspringen.',
        'error_empty_cart' => 'Keine Positionen mit Menge ausgewählt.',
    ],
];

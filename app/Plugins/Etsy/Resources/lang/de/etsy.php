<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : etsy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Etsy',
    'intro' => 'Direktanbindung des Etsy-Shops (Open API v3) — Bestellungen laufen Inbox-First in den Spiegel, Kundenzuordnung nie blind. Zugangsdaten der Org-eigenen Seller-App: Plugin-Karte.',
    'connection' => [
        'active' => 'Verbunden: :shop',
        'none' => 'Nicht verbunden',
        'connect' => 'Mit Etsy verbinden',
        'disconnect' => 'Trennen',
        'disconnect_confirm' => 'Etsy-Verbindung wirklich trennen? Spiegel und Zuordnungen bleiben erhalten.',
        'shop_pending' => 'Shop-Ermittlung offen',
        'shop_conflict' => 'Dieser Etsy-Shop ist bereits mit einer anderen Organisation verbunden.',
        'not_configured' => 'Zuerst Keystring/Shared Secret in der Plugin-Karte hinterlegen.',
    ],
    'setup' => [
        'callback' => 'Redirect-URI der Seller-App (exakt, HTTPS):',
        'webhook' => 'Webhook-URL fürs Etsy-Portal (Events order.*):',
    ],
    'field' => [
        'receipt' => 'Bestellung',
        'status' => 'Status',
        'buyer' => 'Käufer',
        'customer' => 'Kunde',
        'total' => 'Brutto',
        'ordered_at' => 'Bestellt am',
        'shipping' => 'Versand',
        'tracking_code' => 'Tracking-Nr.',
        'carrier' => 'Carrier',
    ],
    'filter' => [
        'all_statuses' => 'Alle Status',
        'apply' => 'Filtern',
    ],
    'action' => [
        'sync' => 'Jetzt synchronisieren',
        'ship' => 'Versand melden',
        'ship_submit' => 'Melden',
    ],
    'status' => [
        'open_assignment' => 'Zuordnung offen',
        'guest' => 'Gastkauf',
        'shipped' => 'Versendet',
    ],
    'flash' => [
        'synced' => 'Etsy-Sync abgeschlossen: :imported neu, :staged offene Zuordnungen.',
        'sync_failed' => 'Etsy-Sync fehlgeschlagen — Details im Protokoll.',
        'already_shipped' => 'Bestellung ist bereits als versendet gemeldet.',
        'ship_queued' => 'Versandmeldung eingereiht — Etsy wird benachrichtigt.',
    ],
    'ledger' => [
        'caption' => 'Gebühren und Auszahlungen der letzten 90 Tage (Etsy-Payment-Ledger).',
        'type' => 'Art',
        'amount' => 'Summe',
        'entries' => 'Einträge',
    ],
    'open_inbox' => ':count offene Zuordnungen',
    'last_sync' => 'Letzter Abgleich :at',
    'empty' => 'Noch keine Bestellungen gespiegelt.',
];

<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : billbee.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Billbee',
    'intro' => 'Multichannel-Bestellungen aus Billbee (Amazon, eBay, Otto, Kaufland, Shopify …) — Import läuft Inbox-First, Kundenzuordnung nie blind. Zugangsdaten: Plugin-Karte.',
    'field' => [
        'channel' => 'Kanal',
        'state' => 'Status',
        'order_number' => 'Bestellnr.',
        'buyer' => 'Käufer',
        'customer' => 'Kunde',
        'total' => 'Brutto',
        'ordered_at' => 'Bestellt am',
    ],
    'filter' => [
        'all_channels' => 'Alle Kanäle',
        'apply' => 'Filtern',
    ],
    'action' => [
        'sync' => 'Jetzt synchronisieren',
    ],
    'flash' => [
        'synced' => 'Billbee-Sync abgeschlossen: :imported neu, :staged offene Zuordnungen.',
        'sync_failed' => 'Billbee-Sync fehlgeschlagen — Details im Protokoll.',
    ],
    'open_inbox' => ':count offene Zuordnungen',
    'last_sync' => 'Letzter Abgleich :at',
    'status' => [
        'open_assignment' => 'Zuordnung offen',
    ],
    'empty' => 'Noch keine Bestellungen gespiegelt.',
];

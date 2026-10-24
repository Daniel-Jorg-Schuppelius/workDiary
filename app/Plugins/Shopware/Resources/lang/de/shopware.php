<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shopware.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Liest Bestellungen eines Shopware-6-Shops über eine Integration, spiegelt sie in WorkDiary und ordnet die Käufer vorhandenen Kunden zu; unklare Käufer werden Klärfälle. In den Shop wird nichts zurückgeschrieben.',
    ],
    'settings' => [
        'base_url' => 'Shop-Adresse',
        'base_url_help' => 'Adresse des Shops, z. B. https://shop.example.com (ohne /api oder /admin).',
        'client_id' => 'Zugangs-ID',
        'client_id_help' => 'In Shopware unter Einstellungen → System → Integrationen eine Integration anlegen. Sie braucht Leserecht auf Bestellungen, Bestellkunden, Kunden, Positionen, Zahlungen samt Captures und Erstattungen, Währungen und Status – oder die Option „Administrator“.',
        'client_secret' => 'Sicherheitsschlüssel',
    ],
    'search' => [
        'domain' => 'Shopware-Bestellungen',
    ],
    'cockpit' => [
        'title' => 'Shopware-Bestellungen',
    ],
    'health' => [
        'not_configured' => 'Shopware ist nicht eingerichtet (Shop-Adresse oder Integration fehlen).',
        'not_connected' => 'Shopware-Verbindung noch nicht geprüft.',
    ],
];

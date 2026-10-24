<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : invoiceninja.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Spiegelt Rechnungen, Gutschriften und Ausgaben aus Invoice Ninja in den Belegfluss, nur lesend und mit Link zum Original. Kunden und Lieferanten werden nur über vorhandene Zuordnungen, die Kundennummer bzw. den Lieferantennamen verknüpft.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Leer lassen für das gehostete Invoice Ninja; bei eigener Installation deren Adresse, z. B. https://rechnung.example.com.',
        'api_token' => 'API-Token',
        'api_token_help' => 'In Invoice Ninja unter Einstellungen → Kontoverwaltung → API-Token anlegen. Sein Benutzer braucht Administratorrechte oder das Recht, Rechnungen, Gutschriften und Ausgaben anzusehen – sonst sieht er nur eigene. Das gehostete Invoice Ninja gibt die API nur in bezahlten Tarifen frei.',
        'pull_vouchers' => 'Belege abrufen',
        'pull_vouchers_help' => 'Erst mit diesem Schalter liest der stündliche Abruf Belege. Er schreibt nichts nach Invoice Ninja.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Installation im eigenen Netz: erlaubt eine Adresse mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
    'health' => [
        'not_configured' => 'Invoice Ninja ist nicht eingerichtet (API-Token fehlt).',
    ],
];

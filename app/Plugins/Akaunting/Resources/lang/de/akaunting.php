<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : akaunting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Spiegelt Rechnungen und Lieferantenrechnungen aus Akaunting in den Belegfluss, nur lesend und mit Link zum Original. Lieferanten werden über vorhandene Zuordnungen oder den Namen verknüpft.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse der Akaunting-Installation, z. B. https://buchhaltung.example.com (ohne /api).',
        'email' => 'E-Mail',
        'email_help' => 'Anmeldung eines Akaunting-Benutzers mit dem Recht „API lesen“ und Leserecht auf Rechnungen und Lieferantenrechnungen.',
        'password' => 'Passwort',
        'company_id' => 'Firmen-ID',
        'company_id_help' => 'Nummer der Firma in Akaunting; sie steht in der Adresse der Oberfläche (z. B. /1/…).',
        'pull_vouchers' => 'Belege abrufen',
        'pull_vouchers_help' => 'Erst mit diesem Schalter liest der stündliche Abruf Belege. Er schreibt nichts nach Akaunting.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Installation im eigenen Netz: erlaubt eine Adresse mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
    'health' => [
        'not_configured' => 'Akaunting ist nicht eingerichtet (Adresse oder Zugang fehlen).',
    ],
];

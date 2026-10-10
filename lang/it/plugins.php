<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : plugins.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Fähigkeiten, die über eigene Registries laufen statt über das
    // Capability-Enum (Entscheid 2026-08-21 zum Audit-Befund W1.6):
    // nur für die Anzeige, kein Vertragsbestandteil.
    'capability' => [
        'facturation' => 'Trasmissione documenti',
        'mirror' => 'Mirroring dei file',
        'inventory' => 'Riscrittura delle giacenze',
    ],

    'compatibility' => [
        'incompatible' => 'Incompatibile',
        'range' => 'Core :min–:max',
        'range_hint' => 'Intervallo di versioni del core WorkDiary supportato.',
        'activation_blocked' => 'Il plugin non può essere attivato: :message',
    ],

    // Adressprüfung ausgehender Plugin-Ziele (UrlSafety, PluginHttpFactory).
    'url_guard' => [
        'invalid' => ':prefix: :subject non è un indirizzo http(s) valido.',
        'private' => ':prefix: :subject punta a un indirizzo privato/interno.',
        'subject' => [
            'base' => 'L’URL di base',
            'target' => 'L’URL di destinazione',
        ],
        'hint_default' => 'Gli indirizzi privati devono essere consentiti esplicitamente.',
        'hint_setting' => 'Per un’istanza nella propria rete attivi l’impostazione del plugin «:setting».',
        'hint_operator' => 'Solo il gestore della Sua installazione può consentire una destinazione nella propria rete (PLUGINS_PRIVATE_NETWORK_TARGETS).',
    ],
];

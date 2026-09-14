<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : search.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Tätigkeitsrecherche (Feature 153): Suchindex, Engine und Anfrage-Regeln.
 */

return [
    // Observer indizieren synchron nach Commit. Die Testsuite schaltet das ab
    // (phpunit), Suchtests schalten es gezielt ein.
    'indexing' => (bool) env('SEARCH_INDEXING', true),

    // auto: MySQL/MariaDB → Volltextindex, sonst LIKE. Volltext sieht keine
    // uncommitteten Zeilen — Tests laufen deshalb mit `like`.
    'engine' => env('SEARCH_ENGINE', 'auto'),

    'max_query_length' => 200,
    'per_page' => 25,
    'type_ahead_limit' => 5,
    'aggregate_limit' => 12,

    // Anzeige-Auszug je Dokument und Obergrenze des kodierten Suchtexts.
    'excerpt_length' => 2000,
    'text_max_length' => 60000,

    // Tippfehler-Toleranz gegen das Wortverzeichnis (search_terms).
    'fuzzy' => [
        'min_length' => 4,
        'max_candidates' => 3,
    ],

    // KI-Antwort: so viele Treffer gehen höchstens in den Prompt.
    'answer_max_hits' => 40,

    // Füllwörter (ASCII-gefaltet). Sie fallen nur, solange mindestens ein
    // Inhaltswort bleibt — „die die" bleibt suchbar.
    'stopwords' => [
        'wann', 'wer', 'was', 'wo', 'wie', 'warum', 'wieso', 'welche', 'welcher', 'welches', 'welchem', 'welchen',
        'haben', 'hat', 'hatte', 'hatten', 'habe', 'hast', 'habt', 'ist', 'sind', 'war', 'waren', 'wird', 'wurde', 'wurden',
        'wir', 'ihr', 'sie', 'er', 'es', 'ich', 'du', 'uns', 'euch', 'man', 'mir', 'mich', 'dir',
        'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'einer', 'eines',
        'am', 'an', 'im', 'in', 'bei', 'beim', 'mit', 'von', 'vom', 'zu', 'zum', 'zur', 'und', 'oder', 'auf', 'fuer', 'aus', 'ueber',
        'gemacht', 'irgendwas', 'etwas', 'mal', 'noch', 'schon', 'doch', 'denn', 'kannst', 'koennen', 'sagen', 'sag', 'sagmal',
        'the', 'and', 'what', 'when', 'who', 'where', 'did', 'we', 'with', 'for',
    ],

    // Vorlagen für die Synonympflege (übernehmen legt fehlende Gruppen an).
    'synonym_presets' => [
        'it' => [
            ['smtp', 'mailrelay', 'smtp-relay', 'sendeconnector', 'send connector', 'postfix'],
            ['exchange', 'exchange online', 'exo', 'm365', 'microsoft 365', 'office 365', 'o365'],
            ['outlook', 'mailclient', 'mailprogramm'],
            ['active directory', 'ad', 'domaenencontroller', 'domain controller'],
            ['vpn', 'openvpn', 'wireguard', 'ipsec'],
            ['firewall', 'utm', 'sophos', 'fortigate'],
            ['drucker', 'printer', 'mfp', 'multifunktionsgeraet', 'kopierer'],
            ['nas', 'synology', 'qnap', 'netzwerkspeicher'],
            ['backup', 'datensicherung', 'sicherung', 'veeam'],
            ['zertifikat', 'certificate', 'ssl', 'tls'],
            ['dns', 'nameserver', 'mx-record'],
            ['wlan', 'wifi', 'access point', 'unifi'],
            ['fernwartung', 'anydesk', 'teamviewer'],
            ['spam', 'phishing', 'spamfilter'],
        ],
    ],
];

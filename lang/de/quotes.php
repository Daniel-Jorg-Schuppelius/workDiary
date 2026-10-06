<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : quotes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Angebote (Feature 112, MVP-601: Nachfassen).
return [
    'follow_up' => [
        'title' => 'Angebote nachfassen',
        'subtitle' => 'Fällige Nachfasstermine, ablaufende Angebote und Versandtes ohne Termin',
        'action' => 'Nachfassen protokollieren',
        'submit' => 'Protokollieren',
        'recorded' => 'Nachfassen protokolliert.',
        'scheduled' => 'Nachfasstermin gesetzt.',
        'schedule_action' => 'Nachfasstermin setzen',
        'schedule_title' => 'Nachfasstermin für Angebot :number',
        'schedule_hint' => 'Setzt oder verschiebt den Termin, ohne ein Gesprächsergebnis zu protokollieren. Ein bereits abgeschlossenes Nachfassen wird damit wieder offen.',
        'schedule_submit' => 'Termin setzen',
        'empty' => 'Nichts nachzufassen.',
        'dialog_title' => 'Angebot :number nachfassen',
        'dialog_hint' => 'Das Ergebnis wird als Kommunikationsnotiz in der Kundenakte festgehalten.',
        'result' => 'Ergebnis des Gesprächs',
        'result_hint' => 'Was hat der Kunde gesagt? Diese Notiz ist später die Grundlage des nächsten Angebots.',
        'next_at' => 'Erneut nachfassen am',
        'next_at_hint' => 'Leer lassen, wenn das Nachfassen abgeschlossen ist.',
        'note_subject' => 'Nachfassen zu Angebot :number',
        'next_action' => 'Angebot :number erneut nachfassen',
        'wrong_status' => 'Nur versandte oder freigegebene Angebote lassen sich nachfassen.',
        'no_customer' => 'Das Angebot hat keinen Kunden — ohne Kunden gibt es keine Akte für den Vermerk.',
        'kpi' => [
            'due' => 'Fällig',
            'upcoming' => 'Anstehend',
            'expiring' => 'Läuft ab (:days Tage)',
            'expiring_hint' => 'Ohne Reaktion — danach muss das Angebot neu erstellt oder verlängert werden.',
            'untracked' => 'Ohne Termin',
            'untracked_hint' => 'Versandt, aber niemand hat einen Nachfasstermin gesetzt.',
        ],
        'section' => [
            'due' => 'Fällig',
            'upcoming' => 'Anstehend',
            'expiring' => 'Läuft ab ohne Reaktion',
            'untracked' => 'Versandt ohne Nachfasstermin',
        ],
        'column' => [
            'number' => 'Angebot',
            'customer' => 'Kunde',
            'owner' => 'Zuständig',
            'follow_up_at' => 'Nachfassen am',
            'valid_until' => 'Gültig bis',
            'total' => 'Summe',
        ],
        'filter' => ['mine' => 'Nur meine'],
    ],
    // Trefferquote (Feature 112, MVP-998).
    'win_rate' => [
        'title' => 'Trefferquote der Angebote',
        'subtitle' => 'Gewonnene, verlorene und abgelaufene Angebote nach Entscheidungstag bzw. Bindefrist',
        'group' => 'Gruppieren nach',
        'group_customer' => 'Kunde',
        'group_owner' => 'Zuständig',
        'rate' => 'Trefferquote (Anzahl)',
        'volume_rate' => 'Trefferquote (Volumen)',
        'won' => 'Gewonnen',
        'lost' => 'Verloren',
        'expired' => 'Abgelaufen',
        'won_volume' => 'Volumen gewonnen',
        'decided_volume' => 'Volumen entschieden',
        'open' => 'Offene Angebote',
        'open_hint' => 'Nicht entschieden und nicht abgelaufen — zählen nicht zur Quote.',
        'empty' => 'Im Zeitraum wurde kein Angebot entschieden.',
        'note' => 'Je Angebotsnummer zählt nur die jüngste Fassung; Volumen netto, bei Teilannahme der angenommene Teil.',
        'no_owner' => 'Ohne Zuständigkeit',
        'link' => 'Trefferquote',
    ],
];

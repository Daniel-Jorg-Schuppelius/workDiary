<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sustainability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Nachhaltigkeit: Standorte, Benchmarking, Auszug (MVP-929/930).
return [
    'site' => [
        'benchmark' => 'Standort-Vergleich',
        'subtitle' => 'Emissionen je Standort und Jahr aus den Aktivitätsdaten mit Bezug, dazu Intensitäten je m² und je beschäftigter Person. Aktivitäten ohne Faktor zählen nicht mit.',
        'back' => 'Nachhaltigkeit',
        'create' => 'Standort anlegen',
        'edit' => 'Bearbeiten',
        'save' => 'Speichern',
        'inactive' => 'inaktiv',
        'empty' => 'Noch keine Standorte — legen Sie Standorte an und erfassen Sie Aktivitäten mit Standortbezug.',
        'field' => [
            'site' => 'Standort',
            'code' => 'Kürzel',
            'year' => 'Jahr',
            'area_m2' => 'Fläche (m²)',
            'headcount' => 'Beschäftigte',
            'co2e_t' => 'CO₂e (t)',
            'per_m2' => 'kg CO₂e je m²',
            'per_head' => 'kg CO₂e je Person',
            'missing' => 'ohne Faktor',
            'active' => 'aktiv',
        ],
        'flash' => [
            'saved' => 'Standort gespeichert.',
        ],
    ],
    'excerpt' => [
        'title' => 'Öffentlicher Auszug',
        'intro' => 'Geben Sie einen eingefrorenen Berichts-Snapshot frei. Er erscheint über einen Link und als Hinweis im Kundenportal — ohne Konformitäts- oder Klimaneutralitätsbehauptung.',
        'snapshot' => 'Freigegebener Snapshot',
        'none' => '— nicht freigegeben —',
        'with_targets' => 'Ziele mit ausweisen',
        'publish' => 'Freigabe speichern',
        'token_once' => 'Link nur jetzt sichtbar — bitte kopieren.',
        'link' => 'Öffentlicher Link',
        'state_none' => 'nicht ausgestellt',
        'state_active' => 'aktiv',
        'state_paused' => 'pausiert',
        'pause' => 'Pausieren',
        'resume' => 'Fortsetzen',
        'revoke' => 'Widerrufen',
        'rotate' => 'Neuen Link ausstellen',
        'issue' => 'Link ausstellen',
        'public_title' => 'Nachhaltigkeitsauszug :org',
        'period' => 'Zeitraum :from – :to',
        'emissions' => 'Treibhausgasemissionen',
        'scope' => 'Scope :scope',
        'targets' => 'Ziele',
        'disclaimer' => 'Eingefrorene Kennzahlen aus den erfassten Aktivitätsdaten; keine Konformitäts- oder Klimaneutralitätsbehauptung.',
        'factors' => 'Faktorsätze: :sets.',
        'portal_subject' => 'Nachhaltigkeitsauszug :from – :to',
        'portal_body' => 'Treibhausgasemissionen im Zeitraum: :tonnes t CO₂e.',
        'flash' => [
            'published' => 'Freigabe gespeichert.',
            'issued' => 'Link ausgestellt.',
            'revoked' => 'Link widerrufen.',
            'saved' => 'Einstellung gespeichert.',
        ],
    ],
    // Vergleich nach Kundengruppe (MVP-949).
    'customer_group' => [
        'title' => 'Emissionen nach Kundengruppe :year',
        'group' => 'Kundengruppe',
        'customers' => 'Kunden',
        'per_customer' => 'je Kunde',
        'none' => 'Ohne Kundengruppe',
        'customer' => 'Kunde (für den Vergleich nach Kundengruppe)',
        'empty' => 'Keine Aktivitäten mit Kundenbezug in diesem Jahr.',
    ],
];

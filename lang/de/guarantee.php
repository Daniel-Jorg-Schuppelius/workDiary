<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : guarantee.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Bürgschaftsregister (Feature 114, MVP-603).
return [
    'title' => 'Bürgschaften',
    'subtitle' => 'Gestellte und erhaltene Bürgschaften mit Befristung und Rückgabe-Nachweis',
    'empty' => 'Noch keine Bürgschaft erfasst.',
    'unlimited' => 'unbefristet',
    'created' => 'Bürgschaft erfasst.',
    'updated' => 'Bürgschaft aktualisiert.',
    'returned' => 'Rückgabe protokolliert.',
    'drawn' => 'Ziehung protokolliert.',
    'secured' => 'Sicherheitseinbehalt durch die Bürgschaft abgelöst.',
    'not_active' => 'Diese Bürgschaft ist nicht mehr aktiv.',
    'retention_not_open' => 'Dieser Sicherheitseinbehalt ist nicht mehr offen.',
    'foreign_organization' => 'Bürgschaft und Einbehalt gehören zu verschiedenen Organisationen.',
    'amount_too_low' => 'Die Bürgschaft deckt den Einbehalt nicht — eine kleinere Bürgschaft löst ihn nicht ab.',
    'issuer_hint' => 'Bank oder Versicherer laut Urkunde; alternativ einen Lieferanten-Stammsatz wählen.',
    'issuer_supplier' => 'Bürge aus den Stammdaten',
    'action' => [
        'create' => 'Bürgschaft erfassen',
        'edit' => 'Bürgschaft bearbeiten',
        'returned' => 'Urkunde zurückerhalten',
    ],
    'kpi' => [
        'issued' => 'Gestellt (aktiv)',
        'issued_hint' => 'Solange sie nicht zurückkommt, läuft die Avalprovision weiter.',
        'received' => 'Erhalten (aktiv)',
        'received_hint' => 'Läuft sie unbemerkt ab, ist die Sicherheit weg.',
        'expiring' => 'Läuft in 90 Tagen ab',
        'return_due' => 'Rückgabe fällig',
        'return_due_hint' => 'Der abgelöste Einbehalt ist freigegeben — die Urkunde gehört zurück.',
    ],
    'column' => [
        'reference' => 'Bürgschaftsnr.',
        'direction' => 'Richtung',
        'kind' => 'Art',
        'issuer' => 'Bürge',
        'party' => 'Gegenpartei',
        'amount' => 'Betrag',
        'issued_on' => 'Ausgestellt am',
        'expires_on' => 'Befristet bis',
        'status' => 'Status',
        'customer' => 'Kunde',
        'supplier' => 'Lieferant',
        'project' => 'Projekt',
        'responsible' => 'Zuständig',
        'note' => 'Notiz',
    ],
    'filter' => [
        'direction' => 'Richtung',
        'status' => 'Status',
    ],
];

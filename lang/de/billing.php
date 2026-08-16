<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : billing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'feed' => [
        'title' => 'Belegfluss',
        'subtitle' => 'Angebote, Rechnungen, Belege und Auslagen im Zeitraum :range — über den Datumsfilter im Kopf anpassbar.',
        'empty' => 'Keine Belege im gewählten Zeitraum',
        'search_placeholder' => 'Nummer, Kunde, Lieferant …',
        'days_short' => 'T',
        'dunning_level' => 'Mahnstufe :level',
        'action' => [
            'dun' => 'Mahnen',
            'dun_confirm' => 'Mahnung in der Buchhaltung anlegen?',
        ],
        'tab' => [
            'all' => 'Alle',
            'quotes' => 'Angebote',
            'outgoing' => 'Ausgangsrechnungen',
            'incoming' => 'Eingangsrechnungen',
            'credits' => 'Gutschriften',
            'expenses' => 'Auslagen',
            'other' => 'Weitere',
        ],
        'kpi' => [
            'revenue' => 'Erlöse',
            'expense' => 'Aufwand (extern)',
            'balance' => 'Saldo',
            'internal_mine' => 'Meine Auslagen',
            'internal_all' => 'Auslagen (alle)',
            'internal_pending' => 'davon in Prüfung: :amount',
            'open' => 'Offen',
            'overdue' => 'Überfällig',
            'overdue_count' => '{0} kein Beleg|{1} :count Beleg|[2,*] :count Belege',
            'neutral' => 'Ohne Geldwirkung',
            'neutral_hint' => 'Angebote, Auftragsbestätigungen und Lieferscheine zählen nur als Anzahl.',
        ],
        'filter' => [
            'direction' => 'Richtung',
            'origin' => 'Herkunft',
            'only_overdue' => 'Nur überfällige',
            'only_unlinked' => 'Nur ohne Buchungsbeleg',
            'with_archived' => 'Archivierte einbeziehen',
        ],
        'state' => [
            'draft' => 'Entwurf',
            'open' => 'Offen',
            'paid' => 'Abgeschlossen',
            'cancelled' => 'Storniert',
        ],
        'scope' => [
            'mine' => 'Meine',
            'all' => 'Alle',
        ],
        'column' => [
            'kind' => 'Art',
            'origin' => 'Herkunft',
            'due' => 'Fällig',
            'open' => 'Offen',
        ],
    ],
];

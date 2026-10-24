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
            'open_original' => 'Im Original öffnen',
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
            'overdue' => 'davon überfällig',
            'open_count' => '{0} kein offener Beleg|{1} :count offener Beleg|[2,*] :count offene Belege',
            'overdue_count' => ':count von :total Belegen',
            'neutral' => 'Ohne Geldwirkung',
            'neutral_hint' => 'Angebote, Auftragsbestätigungen und Lieferscheine zählen nur als Anzahl.',
        ],
        'filter' => [
            'direction' => 'Richtung',
            'project' => 'Projekt',
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
            'deleted' => 'In Quelle gelöscht',
        ],
        'foreign_amount' => 'Betrag in zweiter Währung',
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
    'relations' => [
        'title' => 'Zugehörige Belege (:count)',
        'column' => [
            'document' => 'Beleg',
            'number' => 'Nummer',
            'date' => 'Datum',
            'state' => 'Status',
            'system' => 'System',
            'relation' => 'Bezug',
        ],
        'via' => 'über :document :number',
        'schedule' => 'Abrechnungsplan',
        'quote_version' => 'Angebot (Version :version)',
        'manufacturing_order' => 'Fertigungsauftrag',
        'transfer' => 'Übergabe (:channel)',
        'draft' => 'Entwurf',
    ],
    // Lieferschein als eigener Beleg (Phase 145, MVP-1163).
    'delivery_note' => [
        'create' => 'Lieferschein erstellen',
        'edit' => 'Mengen bearbeiten',
        'edit_hint' => 'Für eine Teillieferung die Menge anpassen; 0 streicht die Position aus diesem Lieferschein.',
        'details' => 'Lieferung',
        'deliver' => 'Ausliefern',
        'cancel' => 'Stornieren',
        'cancel_confirm' => 'Lieferschein stornieren? Im Buchhaltungsprogramm bleibt er bestehen.',
        'delivered_on' => 'Geliefert am :date',
        'to_order' => 'Fertigungsauftrag :number',
        'number_pending' => 'Die Nummer vergibt :program beim Ausliefern; bis dahin trägt der Lieferschein eine Entwurfsnummer.',
        'flash' => [
            'updated' => 'Lieferschein gespeichert.',
            'delivered' => 'Lieferschein :number ausgeliefert.',
            'cancelled' => 'Lieferschein storniert.',
            'issue_failed' => 'Ausgeliefert, aber der Lieferschein :number bleibt Entwurf: :error',
        ],
        'error' => [
            'not_confirmed' => 'Ein Lieferschein entsteht erst aus einer bestätigten Auftragsbestätigung.',
            'fully_delivered' => 'Alle Positionen der Auftragsbestätigung sind schon geliefert.',
            'not_editable' => 'Nur Entwürfe ohne Lagerauslieferung lassen sich bearbeiten.',
            'not_draft' => 'Nur Entwürfe lassen sich ausliefern.',
            'empty' => 'Der Lieferschein hat keine Positionen.',
            'not_cancellable' => 'Dieser Lieferschein lässt sich nicht stornieren. Eine Lagerauslieferung korrigieren Sie über den Lagerabgang.',
            'not_delivered' => 'Der Lieferschein ist noch nicht ausgeliefert.',
        ],
    ],
];

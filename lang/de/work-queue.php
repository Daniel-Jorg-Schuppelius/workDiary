<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : work-queue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Arbeitsvorrat',
    'subtitle' => 'Alles, was Sie gerade bearbeiten sollten — aus Freigaben, Eingängen, Aufgaben und Erinnerungen. Bearbeitet wird auf der jeweiligen Seite.',
    'filter' => [
        'category' => 'Bereich',
        'all_categories' => 'Alle Bereiche',
        'source' => 'Quelle',
        'all_sources' => 'Alle Quellen',
        'mine' => 'Nur mir zugewiesen',
    ],
    'column' => [
        'urgency' => 'Dringlichkeit',
        'item' => 'Vorgang',
        'source' => 'Quelle',
        'due' => 'Fällig',
        'count' => 'Anzahl',
        'assignment' => 'Zuständigkeit',
    ],
    'assignment' => [
        'personal' => 'Ihnen zugewiesen',
        'shared' => 'Über Ihre Rolle',
    ],
    'empty' => [
        'title' => 'Nichts zu tun',
        'message' => 'Zurzeit wartet kein Vorgang auf Sie.',
    ],
    'source' => [
        'approval' => 'Freigabeketten',
        'vacation_approval' => 'Urlaubsanträge',
        'expense_approval' => 'Spesen',
        'month_closure' => 'Monatsabschlüsse',
        'price_change' => 'Preisänderungen',
        'learning_time' => 'Lernzeiten',
        'integration_inbox' => 'Zuordnungs-Inbox',
        'invoice_inquiry' => 'Rechnungsnachfragen',
        'incoming_invoice' => 'Rechnungseingang',
        'open_issue' => 'Offene Punkte',
        'assigned_entry' => 'Aufträge',
        'task' => 'Projektaufgaben',
        'service_ticket' => 'Service-Tickets',
        'draft_reminder' => 'Eigene Entwürfe',
        'chat_reminder' => 'Chat-Erinnerungen',
    ],
    'item' => [
        'vacation' => 'Urlaubsantrag von :name',
        'expense' => 'Spese von :name',
        'month_closure' => 'Monat :period von :name',
        'price_change' => 'Preisänderung: :article',
        'learning_time' => 'Lernzeit von :name',
        'integration_inbox' => 'Offene Zuordnungen: :source',
        'invoice_inquiry' => 'Nachfrage zu Rechnung :number',
        'incoming_invoice' => [
            'assign' => 'Eingangsrechnungen zuordnen',
            'review' => 'Eingangsrechnungen prüfen',
            'failed' => 'Fehlgeschlagene Übergaben',
            'duplicates' => 'Mögliche Doppel-Eingänge',
        ],
        'entry_untitled' => 'Auftrag ohne Titel',
        'chat_reminder' => 'Chat-Erinnerung',
    ],
    'detail' => [
        'days_open' => ':days Tage ohne Abschluss',
    ],
    'widget' => [
        'label' => 'Arbeitsvorrat',
        'description' => 'Die dringendsten offenen Vorgänge aus allen Bereichen.',
        'all' => 'Alle anzeigen',
        'empty' => 'Zurzeit wartet kein Vorgang auf Sie.',
    ],
];

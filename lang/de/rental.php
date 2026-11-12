<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : rental.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'terms' => [
        'title' => 'Mietbedingungen',
        'signed' => 'Vertrag :contract, Fassung :revision, unterschrieben am :date',
        'missing' => 'Für diesen Kunden liegen keine unterschriebenen Mietbedingungen vor.',
        'missing_required' => 'Keine unterschriebenen Mietbedingungen — die Übergabe ist erst danach möglich.',
        'create_agreement' => 'Mietbedingungen anlegen',
        'required' => 'Übergabe erst mit unterschriebenen Mietbedingungen des Kunden (Einstellung der Organisation).',
    ],
    // Direktbuchung und Preisangabe im Portal (MVP-916).
    'portal' => [
        'price' => 'Preis (netto)',
        'price_estimate' => 'ca. :amount',
        'direct_intro' => 'Freie Geräte buchen Sie direkt; alternativ fragen Sie Gerät oder Gerätegruppe an, und wir bestätigen verbindlich. Die Preisangabe richtet sich nach der Preisliste und ist netto.',
        'direct_book' => 'Direkt buchen',
        'direct_hint' => 'Reserviert das gewählte Gerät sofort verbindlich, sofern es im Zeitraum frei ist.',
        'direct_booked' => 'Gerät verbindlich reserviert — Sie erhalten die Unterlagen zur Übergabe von uns.',
        'direct_status' => 'Direkt gebucht',
        'direct_disabled' => 'Die Direktbuchung ist nicht freigeschaltet.',
        'direct_case_note' => 'Direktbuchung aus dem Kundenportal.',
        'direct_notification' => 'Direktbuchung von :customer',
    ],
    // Online-Vorauszahlung der Direktbuchung (MVP-1515).
    'prepayment' => [
        'hint' => 'Bei Direktbuchung ist eine Online-Vorauszahlung von :percent % des Bruttopreises fällig. Die Reservierung hält :minutes Minuten; bestätigt ist die Buchung mit dem Zahlungseingang.',
        'amount' => 'Vorauszahlung :amount',
        'due' => 'Zahlung bis :until',
        'pay_now' => 'Jetzt bezahlen',
        'unavailable' => 'Die Direktbuchung ist gerade nicht möglich, weil keine Online-Zahlung eingerichtet ist. Bitte senden Sie eine Anfrage.',
        'not_due' => 'Für diese Buchung ist keine Zahlung offen.',
        'expired_error' => 'Die Zahlungsfrist ist abgelaufen; die Reservierung wurde freigegeben.',
        'checkout_failed' => 'Die Bezahlseite ist gerade nicht erreichbar. Ihre Reservierung bleibt bis :until bestehen — bitte versuchen Sie es über „Jetzt bezahlen“ erneut.',
        'description' => 'Vorauszahlung :subject ab :from',
        'confirmed' => 'Zahlung eingegangen — Ihre Buchung ist bestätigt.',
        'pending' => 'Die Zahlung ist noch nicht bestätigt. Sobald der Zahlungsanbieter sie meldet, ist Ihre Buchung bestätigt.',
        'late_notification' => 'Vorauszahlung nach Fristablauf von :customer',
        'late_message' => ':subject: Die Reservierung war bereits verfallen. Bitte neu buchen oder die Zahlung erstatten.',
        'expired_reason' => 'Online-Vorauszahlung nicht fristgerecht eingegangen.',
        'withdrawn_reason' => 'Unbezahlte Direktbuchung vom Kunden zurückgenommen.',
        'on_case' => 'Online-Vorauszahlung :amount bezahlt — wird auf die Rechnung angerechnet.',
        'credited_on_case' => 'Online-Vorauszahlung :amount mit den Mietpositionen an das Buchhaltungsprogramm übergeben — dort auf die Rechnung anrechnen.',
        'profile_percent' => 'Vorauszahlung bei Direktbuchung (%)',
        'profile_percent_hint' => 'Leer = Vorgabe der Organisation, 0 = keine Vorauszahlung für dieses Mietobjekt.',
        'badge' => 'Vorauszahlung :percent %',
        'hint_list' => 'Bei Direktbuchung ist die in der Liste genannte Online-Vorauszahlung fällig. Die Reservierung hält :minutes Minuten; bestätigt ist die Buchung mit dem Zahlungseingang.',
    ],
    // Mietpreisregeln (MVP-950).
    'rule' => [
        'title' => 'Mietpreisregeln',
        'empty' => 'Keine Regeln: Es gilt der Tagessatz.',
        'add' => 'Regel ergänzen',
        'line' => ':label (:percent %)',
        'from_utilization' => 'ab :percent % Auslastung',
        'kind' => [
            'season' => 'Saison',
            'weekday' => 'Wochentage',
            'utilization' => 'Auslastung',
        ],
        'field' => [
            'kind' => 'Art',
            'label' => 'Bezeichnung',
            'valid_from' => 'Gültig ab',
            'valid_until' => 'Gültig bis',
            'weekdays' => 'Wochentage',
            'utilization_min_percent' => 'Ab Auslastung (%)',
            'adjust_percent' => 'Auf-/Abschlag (%)',
        ],
        'weekday' => [
            '1' => 'Mo',
            '2' => 'Di',
            '3' => 'Mi',
            '4' => 'Do',
            '5' => 'Fr',
            '6' => 'Sa',
            '7' => 'So',
        ],
        'flash' => [
            'saved' => 'Mietpreisregel gespeichert.',
            'deleted' => 'Mietpreisregel entfernt.',
        ],
    ],
    // Vormerkhinweis, Protokollpositionen, Mindestzustand, Reinigung (MVP-1604).
    'condition' => [
        'below_minimum' => ':asset liegt mit dem Zustand „:current“ unter dem Mindestzustand „:minimum“ des Verleihprofils und kann nicht vergeben werden.',
        'below_badge' => 'unter Mindestzustand',
    ],
    'soft' => [
        'title' => 'Im Zeitraum bestehen Vormerkungen für diese Geräte',
        'hint' => 'Vormerkungen sperren nicht. Bitte klären Sie mit den genannten Vorgängen, wer das Gerät erhält.',
        'reserved_warning' => 'Im Zeitraum besteht :count Vormerkung eines anderen Vorgangs. Die betroffenen Vorgänge stehen in der Akte.|Im Zeitraum bestehen :count Vormerkungen anderer Vorgänge. Die betroffenen Vorgänge stehen in der Akte.',
    ],
    'cleaning' => [
        'not_open' => 'Dieses Fenster ist kein offenes Reinigungsfenster.',
        'done' => 'Reinigung erledigt — das Gerät ist wieder vergebbar.',
        'mark_done' => 'Reinigung erledigt',
        'form_hint' => 'Legt ein Reinigungsfenster an; bis zur erledigten Reinigung ist das Gerät nicht vergebbar.',
    ],
    'report' => [
        'checklist' => 'Checkliste',
        'accessories' => 'Zubehör',
        'quantity_of' => 'Menge :label',
        'accessories_hint' => 'Haken entfernen, wenn ein Teil fehlt.',
        'accessory_missing' => 'fehlt',
        'condition_items' => 'Zustandspositionen',
        'item_label' => 'Bauteil oder Prüfpunkt',
        'item_label_n' => 'Zustandsposition :n: Bezeichnung',
        'item_state_n' => 'Zustandsposition :n: Befund',
        'item_note' => 'Bemerkung',
        'item_note_n' => 'Zustandsposition :n: Bemerkung',
        'rows_hint' => 'Leere Zeilen werden nicht gespeichert.',
        'consumables' => 'Verbrauch',
        'consumable_label' => 'Verbrauchsmaterial',
        'consumable_label_n' => 'Verbrauch :n: Bezeichnung',
        'consumable_quantity_n' => 'Verbrauch :n: Menge',
        'consumable_unit_n' => 'Verbrauch :n: Einheit',
        'details' => 'Positionen anzeigen',
        'point_done' => 'erledigt',
        'point_open' => 'offen',
        'returns' => 'Rücknahmen',
    ],
    'profile' => [
        'home_site' => 'Heimatstandort',
        'min_condition' => 'Mindestzustand',
        'min_condition_hint' => 'Liegt der aktuelle Zustand darunter, ist das Gerät nicht vergebbar.',
        'no_minimum' => 'kein Mindestzustand',
        'current_condition' => 'Aktueller Zustand',
        'current_condition_hint' => 'Wird bei jeder Rücknahme aus dem Protokoll übernommen; nach einer Instandsetzung hier anpassen.',
        'condition_unknown' => 'nicht erfasst',
        'checklist' => 'Checkliste für Übergabe und Rücknahme (ein Prüfpunkt je Zeile)',
        'checklist_hint' => 'Die Prüfpunkte erscheinen zum Abhaken im Übergabe- und Rücknahmeprotokoll.',
    ],
    'deposit' => [
        'waive' => 'Erlassen',
        'waive_reason' => 'Begründung des Erlasses',
        'flash' => [
            'waived' => 'Kaution erlassen.',
        ],
        'error' => [
            'not_waivable' => 'Nur angeforderte, noch nicht erhaltene Kautionen lassen sich erlassen.',
            'reason_required' => 'Ein Kautionserlass braucht eine Begründung.',
        ],
    ],
];

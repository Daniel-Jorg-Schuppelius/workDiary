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
        'title' => 'Relance des devis',
        'subtitle' => 'Relances dues, devis expirant et devis envoyés sans date',
        'action' => 'Enregistrer la relance',
        'submit' => 'Enregistrer',
        'recorded' => 'Relance enregistrée.',
        'scheduled' => 'Date de relance définie.',
        'empty' => 'Rien à relancer.',
        'dialog_title' => 'Relancer le devis :number',
        'dialog_hint' => 'Le résultat est conservé comme note de communication dans le dossier client.',
        'result' => 'Résultat de l’entretien',
        'result_hint' => 'Qu’a dit le client ? Cette note servira de base au prochain devis.',
        'next_at' => 'Relancer à nouveau le',
        'next_at_hint' => 'Laisser vide lorsque la relance est terminée.',
        'note_subject' => 'Relance du devis :number',
        'next_action' => 'Relancer à nouveau le devis :number',
        'wrong_status' => 'Seuls les devis envoyés ou approuvés peuvent être relancés.',
        'no_customer' => 'Le devis n’a pas de client — sans client, il n’y a pas de dossier pour la note.',
        'kpi' => [
            'due' => 'Dues',
            'upcoming' => 'À venir',
            'expiring' => 'Expire (:days jours)',
            'expiring_hint' => 'Sans réaction — ensuite le devis doit être refait ou prolongé.',
            'untracked' => 'Sans date',
            'untracked_hint' => 'Envoyé, mais personne n’a fixé de date de relance.',
        ],
        'section' => [
            'due' => 'Dues',
            'upcoming' => 'À venir',
            'expiring' => 'Expire sans réaction',
            'untracked' => 'Envoyé sans date de relance',
        ],
        'column' => [
            'number' => 'Devis',
            'customer' => 'Client',
            'owner' => 'Responsable',
            'follow_up_at' => 'Relance le',
            'valid_until' => 'Valable jusqu’au',
            'total' => 'Total',
        ],
        'filter' => ['mine' => 'Les miens seulement'],
    ],
];

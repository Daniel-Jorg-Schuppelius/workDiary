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
    'viewed' => 'Consulté par le client le :date',
    'signature' => [
        'name' => 'Nom de la personne signataire (facultatif)',
        'pad' => 'Signature (facultative)',
        'signed_by' => 'Accepté avec la signature de :name.',
        'signed' => 'Accepté avec signature.',
        'show' => 'Voir la signature',
    ],
    'follow_up' => [
        'title' => 'Relance des devis',
        'subtitle' => 'Relances dues, devis expirant et devis envoyés sans date',
        'action' => 'Enregistrer la relance',
        'submit' => 'Enregistrer',
        'recorded' => 'Relance enregistrée.',
        'scheduled' => 'Date de relance définie.',
        'schedule_action' => 'Fixer la date de relance',
        'schedule_title' => 'Date de relance du devis :number',
        'schedule_hint' => 'Fixe ou reporte la date sans enregistrer le résultat d’un entretien. Une relance déjà terminée redevient ouverte.',
        'schedule_submit' => 'Fixer la date',
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
    // Trefferquote (Feature 112, MVP-998).
    'win_rate' => [
        'title' => 'Taux de réussite des devis',
        'subtitle' => 'Devis gagnés, perdus et expirés selon la date de décision ou la fin de validité',
        'group' => 'Regrouper par',
        'group_customer' => 'Client',
        'group_owner' => 'Responsable',
        'rate' => 'Taux de réussite (nombre)',
        'volume_rate' => 'Taux de réussite (volume)',
        'won' => 'Gagnés',
        'lost' => 'Perdus',
        'expired' => 'Expirés',
        'won_volume' => 'Volume gagné',
        'decided_volume' => 'Volume décidé',
        'open' => 'Devis ouverts',
        'open_hint' => 'Ni décidés ni expirés — hors du taux.',
        'empty' => 'Aucun devis n’a été décidé sur la période.',
        'note' => 'Seule la dernière version par numéro de devis compte ; volume hors taxes, en cas d’acceptation partielle la partie acceptée.',
        'no_owner' => 'Sans responsable',
        'link' => 'Taux de réussite',
    ],
    'external_invoice' => [
        'action' => 'Facturé dans le logiciel comptable',
        'title' => 'Devis :number facturé dans le logiciel comptable',
        'hint' => 'Le devis a été facturé dans le logiciel comptable. Il quitte ainsi la liste « Devis acceptés sans facture ». Le numéro de facture est facultatif.',
        'number' => 'Numéro de facture dans le logiciel comptable',
        'submit' => 'Marquer comme facturé',
        'marked' => 'Devis marqué comme facturé dans le logiciel comptable.',
        'unmarked' => 'Marquage supprimé.',
        'unmark' => 'Supprimer le marquage « facturé »',
        'unmark_confirm' => 'Supprimer le marquage ? Le devis réapparaîtra sous « Devis acceptés sans facture ».',
        'marked_on' => 'Facturé dans le logiciel comptable (marqué le :date)',
        'marked_with_number' => 'Facturé dans le logiciel comptable : :number (marqué le :date)',
    ],
    // Auftragsbestätigung als eigener Beleg (Phase 145, MVP-1162).
    'order_confirmation' => [
        'open' => 'Confirmation de commande :number',
        'create' => 'Créer la confirmation de commande',
        'confirm' => 'Confirmer',
        'cancel' => 'Annuler',
        'cancel_confirm' => 'Annuler la confirmation de commande ? Elle reste dans le logiciel de comptabilité.',
        'to_quote' => 'Devis :number',
        'confirmed_on' => 'Confirmée le :date',
        'number_pending' => ':program attribue le numéro lors de la confirmation ; d\'ici là, la confirmation de commande porte un numéro provisoire.',
        'pdf' => [
            'number' => 'N° :number',
        ],
        'flash' => [
            'confirmed' => 'Confirmation de commande :number confirmée.',
            'cancelled' => 'Confirmation de commande annulée.',
            'create_first' => 'Veuillez d\'abord créer la confirmation de commande.',
        ],
        'error' => [
            'not_won' => 'Seuls les devis acceptés sont confirmés.',
            'not_draft' => 'Seuls les brouillons peuvent être confirmés.',
            'empty' => 'La confirmation de commande n\'a pas de positions.',
            'not_cancellable' => 'Cette confirmation de commande ne peut pas être annulée.',
            'not_confirmed' => 'La confirmation de commande n\'est pas encore confirmée.',
        ],
    ],
];

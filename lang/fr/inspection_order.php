<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_order.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfaufträge an Dienstleister (MVP-938).
return [
    'title' => 'Ordres d\'inspection',
    'subtitle' => 'Confier les contrôles dus à un prestataire : offre, acceptation, résultats par équipement et reprise comme preuve de contrôle.',
    'create' => 'Créer un ordre d\'inspection',
    'send' => 'Envoyer l\'ordre',
    'open' => 'Ouvrir',
    'empty' => 'Aucun ordre d\'inspection.',
    'no_schedules' => 'Aucune échéance de contrôle ouverte.',
    'due' => 'échéance :date',
    'accept' => 'Accepter l\'offre',
    'reject' => 'Refuser l\'offre',
    'take_over' => 'Reprendre les résultats',
    'taken_over' => 'repris',
    'cancel' => 'Annuler l\'ordre',
    'confirm_cancel' => 'Annuler l\'ordre ? Les échéances sont libérées.',
    'public_title' => 'Ordre d\'inspection de :org',
    'public_offer' => 'Soumettre une offre',
    'public_submit_offer' => 'Envoyer l\'offre',
    'public_report' => 'Déclarer les résultats',
    'public_submit_report' => 'Envoyer les résultats',
    'public_reported' => 'Les résultats ont été transmis. Merci.',
    'field' => [
        'title' => 'Intitulé',
        'supplier' => 'Prestataire de contrôle',
        'recipient_email' => 'E-mail du prestataire',
        'items' => 'Équipements',
        'status' => 'Statut',
        'offer_amount' => 'Prix de l\'offre',
        'offer_planned_on' => 'Date prévue',
        'offer_note' => 'Remarque sur l\'offre',
        'asset' => 'Équipement',
        'result' => 'Résultat',
        'performed_on' => 'Contrôlé le',
        'valid_until' => 'Valable jusqu\'au',
        'certificate_no' => 'Numéro de certificat',
        'certificate_file' => 'Certificat (PDF)',
        'event' => 'Preuve de contrôle',
    ],
    'status' => [
        'requested' => 'Demandé',
        'offered' => 'Offre reçue',
        'accepted' => 'Commandé',
        'reported' => 'Résultats déclarés',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé',
    ],
    'flash' => [
        'sent' => 'Ordre d\'inspection envoyé à :email.',
        'decided' => 'Décision enregistrée.',
        'taken_over' => ':count preuves de contrôle reprises.',
        'cancelled' => 'Ordre annulé.',
        'offered' => 'Merci, votre offre a bien été reçue.',
        'reported' => 'Merci, les résultats ont bien été reçus.',
    ],
    'error' => [
        'no_schedules' => 'Veuillez choisir au moins une échéance ouverte.',
        'nothing_reported' => 'Veuillez indiquer au moins un résultat.',
        'transition' => 'L\'ordre ne peut pas passer de « :from » à « :to ».',
    ],
    'mail' => [
        'subject' => 'Ordre d\'inspection de :org : :title',
        'body' => "Bonjour,\n\n:org vous demande une offre pour le contrôle « :title » (:count équipements). Ce lien vous permet de soumettre votre offre puis, après commande, de déclarer les résultats :\n:url\n\nLe lien est valable jusqu'au :until.",
    ],
];

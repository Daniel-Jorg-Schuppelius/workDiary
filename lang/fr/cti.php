<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cti.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Téléphonie / CTI',
    'intro' => 'Les appels entrants de clients connus sont enregistrés comme entrée de communication (métadonnées seulement : sens, numéro, heure, durée — jamais le contenu). Le fournisseur (sipgate, etc.) signale les appels à l\'URL de webhook générée ci-dessous. WorkDiary n\'est pas un standard téléphonique.',

    'note' => [
        'subject_inbound' => 'Appel entrant de :number',
        'subject_outbound' => 'Appel sortant vers :number',
    ],

    // Fenêtre d'appel (MVP-118) — notification in-app à l'employé dont le
    // numéro direct opt-in a été composé.
    'popup' => [
        'title_customer' => 'Appel de :name',
        'title_unknown' => 'Appel de :number',
        'message' => 'Appel entrant (:number).',
        'unknown_number' => 'numéro inconnu',
    ],

    'profile' => [
        'heading' => "Fenêtre d'appel",
        'extension_label' => 'Mon numéro direct',
        'extension_help' => "Lorsqu'un appel arrive sur ce numéro, vous recevez une fenêtre avec l'appelant et — si connu — un lien vers la fiche client. Laisser vide = pas de fenêtre.",
        'extension_placeholder' => 'p. ex. +49 30 1234-56',
        'invalid' => 'Veuillez saisir un numéro de téléphone valide.',
    ],

    'new_heading' => 'Nouvelle URL de webhook',
    'new_hint' => 'Saisissez-la maintenant dans le standard/le fournisseur — le jeton n\'est affiché qu\'une seule fois.',

    'issue_heading' => 'Émettre une connexion',
    'connections_heading' => 'Connexions',
    'no_connections' => 'Aucune connexion émise pour le moment.',

    'field' => [
        'name' => 'Libellé',
        'name_placeholder' => 'p. ex. Accueil sipgate',
        'provider' => 'Fournisseur',
    ],

    'action' => [
        'issue' => 'Émettre',
        'disconnect' => 'Désactiver',
    ],

    'col' => [
        'status' => 'Statut',
        'last_event' => 'Dernier événement',
    ],

    'status' => [
        'active' => 'Actif',
        'inactive' => 'Inactif',
    ],

    'flash' => [
        'dial_saved' => 'Clic-pour-appeler enregistré.',
        'issued' => 'Connexion CTI émise.',
        'disconnected' => 'Connexion CTI désactivée.',
    ],

    'dial' => [
        'action' => 'Appeler',
        'confirm' => 'Lancer un appel vers :number ? Le système téléphonique appelle d’abord votre poste.',
        'started' => 'L’appel vers :number est en cours d’établissement — décrochez votre poste.',
        'no_connection' => 'Aucune connexion téléphonique apte à appeler n’est configurée (activez le clic-pour-appeler dans les réglages CTI).',
        'not_configured' => 'Cette connexion n’a pas d’accès API ou de poste.',
        'no_base_url' => 'Aucune adresse API n’est enregistrée pour ce système.',
        'invalid_number' => 'Ce numéro n’est pas composable.',
        'rejected' => 'Le système téléphonique a refusé l’appel (HTTP :status).',
        'settings' => 'Clic-pour-appeler',
        'enabled' => 'Lancer les appels depuis workDiary',
        'api_token' => 'Jeton API',
        'api_token_help' => 'Laisser vide pour conserver le jeton enregistré.',
        'api_base_url' => 'Adresse API',
        'extension' => 'Poste utilisé',
        'extension_help' => 'Les appels partent de ce poste ; le système l’appelle en premier.',
    ],
];

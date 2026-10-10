<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : datev.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// DATEV-Online-Plugin (MVP-122), Aufruf mit `datev-online::datev.…`.
return [
    'plugin' => [
        'description' => 'DATEV Online : connexion avec DATEV, lots comptables par import EXTF au lieu du téléchargement et images de pièces chaque nuit vers DATEV Unternehmen online.',
    ],
    'settings' => [
        'client_id' => 'ID client (portail développeurs DATEV)',
        'client_id_help' => 'Issu de l\'enregistrement de l\'application chez DATEV ; y saisir cette adresse de redirection : :url',
        'client_secret' => 'Secret client',
        'sandbox' => 'Utiliser le sandbox',
        'sandbox_help' => 'Environnement de test de DATEV. Ne le désactivez pour la production qu\'après validation par DATEV.',
    ],
    'health' => [
        'not_configured' => 'Aucun enregistrement d\'application DATEV enregistré.',
        'not_connected' => 'Non connecté à DATEV.',
        'no_client' => 'Aucun mandant sélectionné.',
        'last_error' => 'Dernière erreur : :error',
        'ok' => 'Connecté à :client.',
    ],
    'connection_status' => [
        'active' => 'connecté',
        'disconnected' => 'non connecté',
    ],
    'incoming' => [
        'label' => 'DATEV Unternehmen online',
    ],
    'transfer_kind' => [
        'extf' => 'Lot comptable',
        'outgoing_document' => 'Facture émise',
        'incoming_document' => 'Facture reçue',
    ],
    'transfer_status' => [
        'pending' => 'en cours',
        'transferred' => 'transféré',
        'succeeded' => 'importé',
        'failed' => 'échoué',
    ],
    'error' => [
        'unknown_client' => 'Ce mandant n\'est pas autorisé pour cette connexion.',
        'not_ready' => 'Connectez-vous d\'abord à DATEV et sélectionnez un mandant.',
        'batch_not_exported' => 'Seuls les lots comptables clôturés peuvent être transférés.',
        'client_mismatch' => 'Le lot appartient à un autre conseiller ou mandant que celui connecté.',
        'file_missing' => 'Le fichier du lot est absent du stockage.',
        'transfer_failed' => 'DATEV n\'a pas accepté le lot ; les détails figurent dans la liste.',
    ],
    'flash' => [
        'not_configured' => 'DATEV Online n\'est pas configuré : l\'ID client et le secret client manquent.',
        'state_invalid' => 'État de connexion invalide ou expiré — veuillez vous reconnecter.',
        'oauth_denied' => 'La connexion à DATEV a été annulée.',
        'oauth_failed' => 'L\'échange de jetons avec DATEV a échoué (:class).',
        'connected' => 'Connecté à DATEV. Veuillez sélectionner le mandant.',
        'disconnected' => 'Connexion à DATEV coupée.',
        'client_selected' => 'Mandant :client sélectionné.',
        'documents_saved' => 'Paramètres des images de pièces enregistrés.',
        'uploaded' => ':transferred pièces transférées, :failed échouées.',
        'batch_transferred' => 'Lot :no remis à DATEV ; DATEV traite l\'import.',
        'jobs_refreshed' => ':count imports terminés.',
    ],
    'page' => [
        'subtitle' => 'Transférer les lots comptables et les images de pièces directement vers DATEV Unternehmen online.',
        'sandbox' => 'Sandbox',
        'connect' => 'Se connecter avec DATEV',
        'disconnect' => 'Déconnecter',
        'disconnect_confirm' => 'Vraiment se déconnecter de DATEV ?',
        'not_configured' => 'Les paramètres du plugin ne contiennent pas l\'ID client ni le secret client de l\'enregistrement d\'application DATEV.',
        'client' => [
            'heading' => 'Mandant',
            'choose' => 'Mandant (conseiller-mandant)',
            'save' => 'Appliquer',
            'error' => 'La liste des mandants est indisponible (:class).',
            'none' => 'Aucun mandant n\'est autorisé pour cette connexion.',
        ],
        'documents' => [
            'heading' => 'Images de pièces',
            'hint' => 'Les factures émises partent vers DATEV Unternehmen online comme « Rechnungsausgang », les factures reçues comme « Rechnungseingang » dès qu’elles sont attribuées dans les factures reçues — chacune une fois, à partir de la date choisie.',
            'enabled' => 'Transférer les images de pièces chaque nuit',
            'since' => 'À partir de la date de pièce',
            'save' => 'Enregistrer',
            'upload' => 'Transférer maintenant',
        ],
        'batches' => [
            'heading' => 'Lots comptables',
            'hint' => 'Les lots clôturés partent vers DATEV comme import EXTF ; le conseiller et le mandant du lot doivent correspondre au mandant connecté.',
            'empty' => 'Aucun lot comptable clôturé.',
            'transfer' => 'Transférer vers DATEV',
            'refresh' => 'Vérifier l\'état de l\'import',
            'col' => [
                'batch' => 'Lot',
                'period' => 'Période',
                'client' => 'Conseiller-mandant',
                'status' => 'DATEV',
            ],
        ],
        'transfers' => [
            'heading' => 'Pièces transférées récemment',
            'empty' => 'Aucune pièce transférée pour l\'instant.',
            'col' => [
                'kind' => 'Type',
                'date' => 'Moment',
                'status' => 'État',
            ],
        ],
    ],
];

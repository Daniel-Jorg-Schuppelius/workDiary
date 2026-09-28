<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : platform_usage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Nutzung je Mandant und Branchenvergleich (MVP-951/949).
return [
    'title' => 'Utilisation par client',
    'subtitle' => 'Utilisateurs, stockage, modules et dernière activité par organisation — réservé à l’exploitation de la plateforme.',
    'back' => 'Organisations',
    'empty' => 'Aucune organisation.',
    'field' => [
        'organization' => 'Organisation',
        'status' => 'Statut',
        'users' => 'Utilisateurs',
        'active_users' => 'Actifs (30 jours)',
        'storage' => 'Stockage',
        'modules' => 'Modules',
        'last_activity' => 'Dernière activité',
    ],
    'benchmark' => [
        'link' => 'Comparaison sectorielle',
        'subtitle' => 'Émissions annuelles par profil sectoriel principal pour tous les clients hors démo, uniquement à partir de trois organisations par secteur.',
        'title' => 'Émissions par secteur :year (anonyme)',
        'branch' => 'Secteur',
        'organizations' => 'Organisations',
        'mean' => 'Moyenne',
        'median' => 'Médiane',
        'empty' => 'Aucun secteur comptant au moins :min organisations avec des émissions saisies.',
    ],
    // Nutzungsabrechnung, Abrechnungsdaten und Tarifanfragen (MVP-956/957).
    'billing' => [
        'title' => 'Facturation à l’usage',
        'subtitle' => 'Utilisation mensuelle par organisation, valorisée avec les prix unitaires des paramètres système (platform_billing.*). Aucune facture n’est créée.',
        'month' => 'Mois',
        'plan' => 'Offre',
        'amount' => 'Montant',
        'empty' => 'Aucune utilisation mensuelle pour l’instant. Elle est enregistrée le premier du mois (platform:usage-snapshot).',
        'note' => 'Montant = forfait de base + utilisateurs + utilisateurs actifs + Go de stockage entamés, chacun multiplié par le prix unitaire.',
    ],
    'plan' => [
        'free' => 'Free',
        'pro' => 'Pro',
        'enterprise' => 'Enterprise',
    ],
    'billing_profile' => [
        'title' => 'Données de facturation',
        'subtitle' => 'Destinataire des factures pour l’utilisation du logiciel et changements d’offre.',
        'contact' => 'Destinataire de la facture',
        'save' => 'Enregistrer',
        'invalid_vat' => 'Le numéro de TVA est invalide.',
        'field' => [
            'name' => 'Nom / société',
            'email' => 'E-mail de facturation',
            'street' => 'Rue',
            'zip' => 'Code postal',
            'city' => 'Ville',
            'country' => 'Pays (ISO)',
            'vat_id' => 'N° de TVA',
            'reference' => 'Référence de commande',
        ],
        'hint' => [
            'reference' => 'Figure sur les factures de l’exploitant.',
        ],
        'flash' => [
            'saved' => 'Données de facturation enregistrées.',
        ],
    ],
    'plan_request' => [
        'title' => 'Demander un changement d’offre',
        'open_title' => 'Demandes d’offre ouvertes',
        'history' => 'Demandes',
        'current' => 'Offre actuelle : :plan',
        'send' => 'Envoyer la demande',
        'withdraw' => 'Retirer',
        'done' => 'Traitée',
        'decline' => 'Refuser',
        'empty' => 'Aucune demande.',
        'already_open' => 'Une demande est déjà ouverte.',
        'field' => [
            'plan' => 'Offre souhaitée',
            'addons' => 'Modules complémentaires',
            'note' => 'Remarque',
            'requester' => 'Demandée par',
            'created_at' => 'Date',
        ],
        'hint' => [
            'addons' => 'Codes de modules séparés par des virgules, p. ex. module.rental',
        ],
        'status' => [
            'open' => 'Ouverte',
            'done' => 'Traitée',
            'declined' => 'Refusée',
            'withdrawn' => 'Retirée',
        ],
        'flash' => [
            'sent' => 'Demande envoyée. L’exploitant reviendra vers vous.',
            'withdrawn' => 'Demande retirée.',
            'decided' => 'Demande clôturée.',
        ],
    ],
];

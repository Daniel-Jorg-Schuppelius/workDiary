<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : guarantee.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Bürgschaftsregister (Feature 114, MVP-603).
return [
    'title' => 'Cautions',
    'subtitle' => 'Cautions données et reçues avec échéance et justificatif de restitution',
    'empty' => 'Aucune caution enregistrée.',
    'unlimited' => 'sans échéance',
    'created' => 'Caution enregistrée.',
    'updated' => 'Caution mise à jour.',
    'returned' => 'Restitution enregistrée.',
    'drawn' => 'Appel de la caution enregistré.',
    'secured' => 'Retenue de garantie remplacée par la caution.',
    'not_active' => 'Cette caution n’est plus active.',
    'retention_not_open' => 'Cette retenue n’est plus ouverte.',
    'foreign_organization' => 'La caution et la retenue appartiennent à des organisations différentes.',
    'amount_too_low' => 'La caution ne couvre pas la retenue — une caution plus faible ne la remplace pas.',
    'issuer_hint' => 'Banque ou assureur selon l’acte ; sinon choisir une fiche fournisseur.',
    'issuer_supplier' => 'Garant issu des données de base',
    'action' => [
        'create' => 'Enregistrer une caution',
        'edit' => 'Modifier la caution',
        'returned' => 'Acte restitué',
    ],
    'kpi' => [
        'issued' => 'Données (actives)',
        'issued_hint' => 'Tant qu’elle n’est pas restituée, la commission d’aval continue.',
        'received' => 'Reçues (actives)',
        'received_hint' => 'Si elle expire sans qu’on le remarque, la garantie disparaît.',
        'expiring' => 'Expire sous 90 jours',
        'return_due' => 'Restitution à demander',
        'return_due_hint' => 'La retenue remplacée est libérée — l’acte doit revenir.',
    ],
    'column' => [
        'reference' => 'N° de caution',
        'direction' => 'Sens',
        'kind' => 'Type',
        'issuer' => 'Garant',
        'party' => 'Contrepartie',
        'amount' => 'Montant',
        'issued_on' => 'Émise le',
        'expires_on' => 'Échéance',
        'status' => 'Statut',
        'customer' => 'Client',
        'supplier' => 'Fournisseur',
        'project' => 'Projet',
        'responsible' => 'Responsable',
        'note' => 'Note',
    ],
    'filter' => [
        'direction' => 'Sens',
        'status' => 'Statut',
    ],
];

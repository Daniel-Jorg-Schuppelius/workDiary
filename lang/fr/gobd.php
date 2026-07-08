<?php

/*
 * Mise à disposition de données GoBD Z3 (Feature 063, MVP-132).
 */

return [
    'title' => 'Mise à disposition de données GoBD (Z3)',
    'subtitle' => 'Données fiscalement pertinentes sous forme de paquet GDPdU pour le contrôle fiscal (lisible par IDEA).',
    'period' => 'Période de contrôle',
    'sections' => 'Domaines de données',
    'section' => [
        'invoices' => 'Factures émises',
        'invoice_items' => 'Lignes de facture',
        'customers' => 'Débiteurs',
    ],
    'preflight' => [
        'title' => 'Contrôle préalable',
        'check' => 'Vérifier la période',
        'records' => ':count enregistrements',
        'warnings' => 'Remarques',
        'drafts' => ':count facture(s) non figée(s) (brouillon) dans la période — pas encore définitives fiscalement.',
        'empty_invoices' => 'Aucune facture émise dans la période sélectionnée.',
    ],
    'export' => 'Télécharger le paquet Z3',
    'recent' => [
        'title' => 'Exports récents',
        'package_hash' => 'Empreinte du paquet (SHA-256)',
        'records' => 'Enregistrements',
        'created' => 'Créé',
        'none' => 'Aucun export pour le moment.',
    ],
    'encoding' => 'Jeu de caractères des fichiers de données',
];

<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gobd.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
        'time_entries' => 'Relevés de temps',
        'booking_batches' => 'Lots d\'écritures',
        'booking_batch_items' => 'Positions des lots d\'écritures',
        'payment_allocations' => 'Affectations de paiement',
        'cash_entries' => 'Livre de caisse',
        'cash_daily_closings' => 'Clôtures de caisse journalières',
        'incoming_einvoices' => 'Factures électroniques entrantes',
        'expenses' => 'Frais',
    ],
    'preflight' => [
        'title' => 'Contrôle préalable',
        'check' => 'Vérifier la période',
        'records' => ':count enregistrements',
        'warnings' => 'Remarques',
        'drafts' => ':count facture(s) non figée(s) (brouillon) dans la période — pas encore définitives fiscalement.',
        'draft_batches' => ':count lot(s) d\'écritures non figé(s) (brouillon) dans la période — absent(s) de la preuve des lots d\'écritures.',
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

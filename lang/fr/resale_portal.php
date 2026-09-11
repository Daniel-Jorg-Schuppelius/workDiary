<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : resale_portal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Portail client « mes abonnements » (fonctionnalité 152) : parc sans prix, achats ni justificatifs.
return [
    'title' => 'Mes abonnements',
    'menu' => 'Abonnements',
    'subtitle' => 'Vos abonnements et licences — y compris ceux de vos clients finaux. Les prix et montants figurent sur vos factures.',
    'field' => [
        'product' => 'Désignation / produit',
        'holder' => 'Titulaire',
        'quantity' => 'Quantité',
        'term' => 'Durée',
        'interval' => 'Périodicité',
        'renewal' => 'Renouvellement',
        'next_period' => 'Prochaine période',
        'status' => 'Statut',
        'kind' => 'Type',
        'period' => 'Période',
    ],
    'holder' => [
        'end_customer' => 'Client final',
    ],
    'term' => [
        'since' => 'depuis le :date',
        'range' => ':from – :to',
        'running' => 'en cours',
    ],
    'interval' => [
        'yearly' => 'annuelle',
        'monthly' => 'mensuelle',
    ],
    'next_period' => [
        'none' => 'aucune autre',
    ],
    'period_status' => [
        'open' => 'ouverte',
        'billed' => 'facturée',
        'partial' => 'partiellement facturée',
        'waived' => 'non facturée',
        'disputed' => 'en cours d’examen',
    ],
    'periods' => [
        'title' => 'Périodes de facturation',
        'hint' => 'Les périodes découlent du début, de la durée et de la périodicité ; « facturée » signifie que vous avez reçu une facture correspondante.',
        'empty' => 'Aucune période planifiée pour le moment.',
    ],
    'empty' => 'Aucun abonnement enregistré.',
    'back' => 'Retour à l’aperçu',
    'show' => 'Détails',
];

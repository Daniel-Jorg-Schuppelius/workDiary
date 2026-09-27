<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sustainability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Nachhaltigkeit: Standorte, Benchmarking, Auszug (MVP-929/930).
return [
    'site' => [
        'benchmark' => 'Comparaison des sites',
        'subtitle' => 'Émissions par site et par année à partir des données d\'activité, avec intensités par m² et par salarié. Les activités sans facteur ne sont pas comptées.',
        'back' => 'Durabilité',
        'create' => 'Ajouter un site',
        'edit' => 'Modifier',
        'save' => 'Enregistrer',
        'inactive' => 'inactif',
        'empty' => 'Aucun site pour l\'instant — ajoutez des sites et saisissez des activités avec un site.',
        'field' => [
            'site' => 'Site',
            'code' => 'Code',
            'year' => 'Année',
            'area_m2' => 'Surface (m²)',
            'headcount' => 'Salariés',
            'co2e_t' => 'CO₂e (t)',
            'per_m2' => 'kg CO₂e par m²',
            'per_head' => 'kg CO₂e par personne',
            'missing' => 'sans facteur',
            'active' => 'actif',
        ],
        'flash' => [
            'saved' => 'Site enregistré.',
        ],
    ],
    'excerpt' => [
        'title' => 'Extrait public',
        'intro' => 'Publiez un instantané de rapport figé. Il est affiché via un lien et comme avis dans le portail client — sans allégation de conformité ni de neutralité climatique.',
        'snapshot' => 'Instantané publié',
        'none' => '— non publié —',
        'with_targets' => 'Inclure les objectifs',
        'publish' => 'Enregistrer la publication',
        'token_once' => 'Lien visible uniquement maintenant — veuillez le copier.',
        'link' => 'Lien public',
        'state_none' => 'non émis',
        'state_active' => 'actif',
        'state_paused' => 'suspendu',
        'pause' => 'Suspendre',
        'resume' => 'Reprendre',
        'revoke' => 'Révoquer',
        'rotate' => 'Émettre un nouveau lien',
        'issue' => 'Émettre un lien',
        'public_title' => 'Extrait de durabilité :org',
        'period' => 'Période du :from au :to',
        'emissions' => 'Émissions de gaz à effet de serre',
        'scope' => 'Scope :scope',
        'targets' => 'Objectifs',
        'disclaimer' => 'Chiffres figés issus des données d\'activité saisies ; aucune allégation de conformité ni de neutralité climatique.',
        'factors' => 'Jeux de facteurs : :sets.',
        'portal_subject' => 'Extrait de durabilité du :from au :to',
        'portal_body' => 'Émissions de gaz à effet de serre sur la période : :tonnes t CO₂e.',
        'flash' => [
            'published' => 'Publication enregistrée.',
            'issued' => 'Lien émis.',
            'revoked' => 'Lien révoqué.',
            'saved' => 'Paramètre enregistré.',
        ],
    ],
    // Vergleich nach Kundengruppe (MVP-949).
    'customer_group' => [
        'title' => 'Émissions par groupe de clients :year',
        'group' => 'Groupe de clients',
        'customers' => 'Clients',
        'per_customer' => 'par client',
        'none' => 'Sans groupe de clients',
        'customer' => 'Client (pour la comparaison par groupe de clients)',
        'empty' => 'Aucune activité liée à un client cette année.',
    ],
];

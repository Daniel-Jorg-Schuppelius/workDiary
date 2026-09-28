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
        'statement' => 'Déclaration pour l’extrait',
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
    // Klimanachweise und Umweltaussagen (MVP-961).
    'offset' => [
        'title' => 'Justificatifs climat',
        'subtitle' => 'Compensations, garanties d’origine et contributions climat avec norme, quantité et annulation.',
        'separate' => 'Les justificatifs sont présentés séparément et jamais déduits des émissions.',
        'list' => 'Justificatifs',
        'public_title' => 'Justificatifs climat (non déduits)',
        'add' => 'Saisir un justificatif',
        'empty' => 'Aucun justificatif pour l’instant.',
        'totals' => 'Total par année',
        'evidenced' => 'justifié',
        'not_evidenced' => 'Annulation ou référence de registre manquante',
        'confirm_delete' => 'Supprimer le justificatif ?',
        'kind' => [
            'compensation' => 'Compensation',
            'green_energy' => 'Garantie d’origine (électricité verte)',
            'contribution' => 'Contribution climat',
        ],
        'field' => [
            'kind' => 'Type',
            'provider' => 'Fournisseur',
            'standard' => 'Norme',
            'project_name' => 'Projet',
            'quantity_t' => 'Quantité (t CO₂e)',
            'claim_year' => 'Année',
            'vintage_year' => 'Millésime',
            'retired_on' => 'Annulé le',
            'registry_reference' => 'Référence de registre',
            'note' => 'Note',
            'evidence' => 'Preuve',
        ],
        'hint' => [
            'standard' => 'p. ex. Gold Standard, VCS, GO',
        ],
        'flash' => [
            'saved' => 'Justificatif saisi.',
            'deleted' => 'Justificatif supprimé.',
        ],
    ],
    'claim' => [
        'title' => 'Vérifier les allégations environnementales',
        'intro' => 'Vérifie les textes à la recherche de formulations interdites ou nécessitant une preuve selon la directive EmpCo (UE 2024/825, à partir du 27/09/2026).',
        'text' => 'Texte',
        'check' => 'Vérifier',
        'none' => 'Aucune formulation problématique trouvée.',
        'hint' => 'Vérifié à la publication pour les allégations environnementales interdites.',
        'warning' => 'Publié, mais veuillez vérifier : :terms',
        'disclaimer' => 'Avertissement automatique, pas un avis juridique.',
        'reason' => [
            'offset_neutrality' => 'Les allégations de neutralité ou d’impact climatique fondées sur la compensation sont interdites.',
            'generic' => 'Allégation environnementale générique : autorisée seulement avec une excellente performance environnementale reconnue.',
            'evidence' => 'L’allégation nécessite une preuve vérifiable.',
        ],
    ],
];

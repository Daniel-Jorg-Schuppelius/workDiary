<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : demo.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Contenu des blocs de démonstration (MVP-1532/1533). Enregistré dans la langue de la personne qui lance le seed.
return [
    'schedule' => [
        'shift_type' => [
            'early' => 'Équipe du matin',
            'late' => 'Équipe du soir',
            'office' => 'Service de bureau',
        ],
        'plan_current' => 'Planning de cette semaine et de la suivante (démo)',
        'plan_next' => 'Planning de la semaine d’après (démo)',
        'coverage_note' => 'Au moins deux personnes sont prévues en équipe du matin les jours ouvrés.',
        'exchange_reason' => 'Rendez-vous privé l’après-midi, échange convenu avec l’équipe du matin.',
    ],
    'forms' => [
        'report' => [
            'name' => 'Rapport d’intervention (démo)',
            'description' => 'Bref rapport après chaque intervention chez le client.',
            'work_done' => 'Travaux réalisés',
            'work_done_value' => 'Maintenance effectuée selon la liste de contrôle, aucun défaut constaté.',
            'hours_on_site' => 'Temps sur place',
            'result' => 'Résultat',
            'result_done' => 'Terminé',
            'result_follow_up' => 'Intervention de suivi nécessaire',
            'result_material' => 'Matériel manquant',
            'briefed' => 'Client informé',
            'satisfaction' => 'Satisfaction du client',
        ],
        'safety' => [
            'name' => 'Évaluation des risques avant le début des travaux (démo)',
            'description' => 'Contrôle rapide sur place avant le début des travaux.',
            'ppe_complete' => 'Équipement de protection complet',
            'area_secured' => 'Zone de travail sécurisée',
            'hazards' => 'Risques particuliers',
            'hazards_value' => 'Activité du client en cours, zone de travail balisée avec du ruban de signalisation.',
        ],
        'handover' => [
            'name' => 'Passation avant les congés (démo)',
            'description' => 'Brouillon : tâches en cours et contacts pour le remplacement.',
            'open_tasks' => 'Tâches en cours',
            'contact' => 'Interlocuteur chez le client',
        ],
        // Langer Bogen (Feature 203): Abschnitte, Speichern je Feld, Entwurf.
        'intake' => [
            'name' => 'Premier accueil (démo)',
            'description' => 'Formulaire long avec sections : chaque réponse est enregistrée en quittant le champ, la progression s’affiche par section.',
            'section_contact' => 'Contact',
            'person' => 'Personne de contact',
            'channel' => 'Canal préféré',
            'channel_phone' => 'Téléphone',
            'channel_mail' => 'E-mail',
            'channel_visit' => 'Sur place',
            'section_request' => 'Demande',
            'request' => 'De quoi s’agit-il ?',
            'urgent' => 'Urgent',
            'section_risk' => 'Évaluation',
            'risk' => 'Risque',
            'risk_low' => 'Faible',
            'risk_medium' => 'Moyen',
            'risk_high' => 'Élevé',
            'documents_checked' => 'Documents vérifiés',
            'section_close' => 'Clôture',
            'next_step' => 'Étape suivante',
            'follow_up' => 'Relance le',
            'person_value' => 'Mme Exemple',
            'request_value' => 'Vérification des documents avant le début du contrat.',
        ],
    ],
    'knowledge' => [
        'guide_title' => 'Guide : :subject',
        'guide_solution' => 'Procédure de réception, à parcourir point par point avec le client :',
        'issue_solution' => 'Convenir d’un rendez-vous avec le client, effectuer le contrôle et documenter le résultat dans l’ordre de travail. Ensuite, clore le point ouvert.',
        'onboarding_title' => 'Première semaine des nouveaux collègues',
        'onboarding_problem' => 'Les nouveaux collaborateurs ne savent pas où se trouvent les ordres, les temps et les documents.',
        'onboarding_solution' => 'Brouillon : visite guidée des ordres, de la saisie des temps et de la base de connaissances ; un interlocuteur fixe pendant les deux premières semaines.',
    ],
    'inventory' => [
        'warehouse_main' => 'Entrepôt principal (démo)',
        'warehouse_main_note' => 'Rangées d’étagères A–C au rez-de-chaussée',
        'warehouse_vehicle' => 'Véhicule de service 1 (démo)',
    ],
    'travel' => [
        'vendor' => [
            'parking' => 'Parking du centre',
            'lodging' => 'Hôtel du Marché',
            'supplies' => 'Magasin de bricolage exemple',
            'hospitality' => 'Restaurant exemple',
        ],
        'expense' => [
            'parking' => 'Stationnement pour le rendez-vous client à :city',
            'hotel' => 'Nuitée pour l’intervention à :city',
            'material' => 'Matériel de fixation pour l’intervention',
            'hospitality' => 'Repas d’affaires avec le client',
        ],
        'from_address' => 'Siège de l’entreprise',
        'trip_purpose' => 'Rendez-vous client chez :customer',
    ],
    'location' => [
        'zone_label' => ':customer (lieu d’intervention démo)',
    ],
    'contracts' => [
        'maintenance_title' => 'Contrat de maintenance :customer (démo)',
        'maintenance_notes' => 'Forfait mensuel, reconduction de douze mois sauf résiliation.',
        'review_title' => 'Entretien annuel sur l’étendue des prestations',
        'license_title' => 'Licence logicielle bureau (démo)',
        'license_partner' => 'Logiciel Exemple SARL',
        'payment_title' => 'Vérifier la facture annuelle de la licence',
        'carry_forward_note' => 'Avant le renouvellement, rapprocher le nombre de comptes utilisateurs (démo).',
        'rent_title' => 'Bail de surface de stockage (démo)',
        'rent_partner' => 'Parc d’activités Exemple SCI',
    ],
    'facility' => [
        'site_name' => ':customer – site principal',
        'street' => '1 rue Exemple',
        'building' => 'Bâtiment principal',
        'floor' => [
            'ground' => 'Rez-de-chaussée',
            'first' => '1er étage',
        ],
        'room' => [
            'reception' => 'Accueil',
            'technical' => 'Local technique',
            'storage' => 'Stockage',
            'office' => 'Bureau',
            'meeting' => 'Salle de réunion',
            'social' => 'Salle de pause',
        ],
        'access_note' => 'Accès uniquement avec la clé de l’accueil.',
    ],
    'bau' => [
        'boq_name' => 'Devis quantitatif :project (démo)',
        'section_supply' => 'Fourniture',
        'section_labour' => 'Montage et main-d’œuvre',
        'item_assembly' => 'Montage et mise en service',
        'item_assembly_long' => 'Montage de toutes les fournitures, y compris essai de fonctionnement et instruction.',
        'item_hourly' => 'Travaux en régie sur justificatif',
        'progress_note' => 'Premier métré après livraison',
    ],
    'team' => [
        'description' => 'Travail sur le projet :project',
    ],
];

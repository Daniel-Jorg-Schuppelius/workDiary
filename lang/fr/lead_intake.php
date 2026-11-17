<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : lead_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Dossier d'admission avec paquets de contrôle (Feature 200, MVP-1641–1644, MVP-1675).
return [
    'title' => 'Dossier d\'admission',
    'title_for' => 'Dossier d\'admission : :name',
    'index_title' => 'Admissions',
    'index_subtitle' => 'Dossiers d\'admission des leads, de la demande à la mise en place du client.',
    'back_to_lead' => 'Vers le lead',
    'revision' => 'Version :revision',
    'superseded' => 'Remplacée',
    'stages' => 'Étapes',
    'current_stage' => 'Étape actuelle',

    'action' => [
        'open' => 'Ouvrir l\'admission',
        'decide' => 'Décider',
        'refresh' => 'Mettre à jour',
        'pdf' => 'PDF',
        'archive' => 'Classer comme document',
        'move_to' => 'Suivant : :stage',
        'save' => 'Enregistrer',
        'confirm_second_review' => 'Confirmer l\'acceptation',
        'return_second_review' => 'Renvoyer',
    ],
    'confirm' => [
        'refresh' => 'Créer une nouvelle version du dossier ? Les réponses sont reprises et peuvent être vérifiées et complétées.',
        'archive' => 'Classer le PDF sans données d\'identité ni de santé comme document ?',
        'second_review' => 'Confirmer l\'acceptation en tant que seconde personne ?',
    ],
    'hint' => [
        'restricted' => ':count données de ce dossier exigent un droit propre et sont masquées.',
        'restricted_answered' => ':kind : :count renseignées.',
        'no_packages' => 'Aucun paquet de contrôle activé. Les dossiers ne contiennent alors que le formulaire d\'entretien ; les leads se convertissent directement comme avant.',
    ],

    'form' => [
        'section_base' => 'Demande et entretien',
        'request' => 'Demande',
        'services' => 'Prestations souhaitées',
        'services_help' => 'Devient l\'étendue des prestations du nouveau client lors de la conversion.',
        'start_on' => 'Début souhaité',
        'interview_notes' => 'Notes d\'entretien',
        'section_traits' => 'Caractéristiques d\'échéances : :rule_set',
        'traits_help' => 'Deviennent les caractéristiques d\'échéances du nouveau client lors de la conversion.',
        'section_documents' => 'Pièces : :package',
        'documents_help' => 'Cocher dès que la pièce est disponible ; classer le fichier ci-dessous dans les pièces.',
    ],

    'field' => [
        'stage' => 'Étape',
        'decision' => 'Décision',
        'decision_reason' => 'Motif',
        'decided_at' => 'Décidé le',
        'decided_by' => 'Décidé par',
        'second_review' => 'Second contrôle',
        'protocol' => 'Note',
        'packages' => 'Paquets de contrôle',
        'refresh_due_on' => 'Mise à jour due',
        'retention_until' => 'Conserver jusqu\'au',
        'customer' => 'Client',
        'relationship_ended_on' => 'Fin de la relation d\'affaires',
    ],

    'documents' => [
        'title' => 'Pièces',
        'upload' => 'Classer',
        'upload_for' => 'Fichier pour « :document »',
    ],

    'decision' => [
        'title' => 'Décision',
        'legend' => 'Accepter ou refuser l\'admission',
        'open' => 'Pas encore décidé.',
        'missing' => 'Manque encore avant l\'acceptation : :count',
        'blocks_conversion' => 'Un paquet de contrôle avec obligation de décision est actif : le lead ne devient client qu\'après l\'acceptation.',
        'reason_hint' => 'Le motif figure dans le dossier et dans le PDF.',
        'second_review_pending' => 'Accepté — le second contrôle par une autre personne est en attente.',
        'return_reason' => 'Motif du renvoi',
    ],

    'packages' => [
        'title' => 'Paquets de contrôle et délais',
        'none' => 'Aucun',
    ],
    'versions' => [
        'title' => 'Versions',
    ],
    'retention' => [
        'open' => 'court à partir de la fin de la relation d\'affaires',
    ],

    'settings' => [
        'title' => 'Paramètres des admissions',
        'submit' => 'Enregistrer',
        'packages' => 'Paquets de contrôle',
        'none_available' => 'Aucun paquet de contrôle disponible.',
        'packages_hint' => 'Les paquets activés s\'appliquent aux dossiers nouvellement ouverts. Un paquet avec obligation de décision bloque la conversion sans acceptation.',
        'refresh' => 'Mise à jour par niveau de risque GwG (mois)',
        'refresh_for' => 'Niveau de risque :level',
        'refresh_hint' => 'Par défaut : annuel. Le délai commence avec l\'acceptation.',
        'second_review' => 'Second contrôle',
        'second_review_label' => 'Faire confirmer l\'acceptation par une seconde personne',
        'second_review_hint' => 'Par défaut : désactivé. La seconde personne doit disposer du droit de décision et ne pas avoir accepté elle-même.',
    ],

    'relationship' => [
        'title' => 'Fin de la relation d\'affaires',
        'submit' => 'Enregistrer',
        'legend' => 'Relation d\'affaires',
        'hint' => 'À partir de la fin de cette année civile court la conservation des dossiers d\'admission (GwG : cinq ans, § 8 al. 4 GwG). Laisser vide tant qu\'elle dure.',
        'ongoing' => 'en cours',
    ],

    'cockpit' => [
        'none' => 'Pas encore de dossier d\'admission. Paquets actifs : :packages',
        'refresh_due' => 'Mise à jour :date',
        'retention' => 'conserver jusqu\'au :date',
    ],

    'filter' => [
        'status' => 'Sélection',
        'status_open' => 'Admissions ouvertes',
        'status_refresh' => 'Mise à jour due',
        'status_all' => 'Toutes',
        'stage' => 'Étape',
        'all_stages' => 'Toutes les étapes',
    ],
    'empty' => [
        'title' => 'Aucune admission',
        'message' => 'Les dossiers d\'admission s\'ouvrent sur le lead.',
    ],
    'column' => [
        'lead' => 'Lead',
        'stage' => 'Étape',
        'packages' => 'Paquets de contrôle',
        'decision' => 'Décision',
        'refresh_due_on' => 'Mise à jour',
        'updated_at' => 'Modifié',
    ],

    'widget' => [
        'title' => 'Admissions',
        'description' => 'Dossiers d\'admission ouverts par étape, de la demande à la mise en place.',
        'open_list' => 'Toutes les admissions',
        'empty' => 'Aucune admission ouverte pour le moment.',
    ],

    'flash' => [
        'opened' => 'Dossier d\'admission ouvert.',
        'saved' => 'Formulaire enregistré.',
        'stage' => 'Étape : :stage',
        'decided' => 'Décidé : :decision',
        'second_review_confirmed' => 'Acceptation confirmée.',
        'second_review_returned' => 'Acceptation renvoyée — le formulaire est de nouveau ouvert.',
        'document_added' => 'Pièce classée.',
        'refreshed' => 'Version :revision créée.',
        'archived' => 'Classé comme document : :file',
        'settings_saved' => 'Paramètres des admissions enregistrés.',
        'relationship_saved' => 'Enregistré ; conservation de :count dossiers d\'admission recalculée.',
    ],

    'error' => [
        'anonymized' => 'Le lead est anonymisé.',
        'stage_by_decision' => 'La mise en place, « mis en place » et « refusé » découlent de la décision ou de la conversion.',
        'closed' => 'Le dossier est décidé ou remplacé par une version plus récente.',
        'reason_required' => 'Veuillez motiver la décision.',
        'no_second_review' => 'Cette acceptation n\'attend pas de second contrôle.',
        'same_person' => 'Le second contrôle est effectué par une autre personne que celle qui a accepté.',
        'refresh_needs_acceptance' => 'Seule la dernière version acceptée peut être mise à jour.',
        'unknown_document' => 'Cette pièce n\'appartient pas au dossier.',
        'restricted' => 'Le droit propre pour cette pièce manque.',
        'no_carrier' => 'Ni client ni lead trouvé pour le classement.',
        'restricted_missing' => 'Une donnée soumise à un droit propre (:right) manque.',
    ],

    'block' => [
        'no_intake' => 'Un paquet de contrôle avec obligation de décision est actif : ouvrez d\'abord le dossier d\'admission et acceptez l\'admission.',
        'not_accepted' => 'L\'admission n\'est pas encore acceptée — le lead ne devient client qu\'ensuite.',
        'second_review' => 'L\'acceptation attend le second contrôle.',
        'rejected' => 'L\'admission a été refusée ; le lead n\'est pas converti.',
    ],

    'transfer' => [
        'service_note' => 'Repris du dossier d\'admission.',
        'traits_incomplete' => 'Les caractéristiques d\'échéances « :rule_set » du dossier sont incomplètes et n\'ont pas été reprises — veuillez les compléter sur le client.',
    ],

    'notification' => [
        'refresh' => [
            'title' => ':count dossier(s) d\'admission à mettre à jour',
            'message' => 'Dû à partir du :date : :names',
        ],
    ],

    'work' => [
        'decide' => 'Décider l\'admission : :name',
        'second_review' => 'Second contrôle : :name',
        'refresh' => 'Mettre à jour l\'admission : :name',
    ],

    'pdf' => [
        'file_prefix' => 'admission',
        'customer' => 'Client',
        'restricted' => 'restreint (:kind)',
    ],

    'vat_id' => [
        'invalid' => 'Le numéro de TVA est formellement invalide (structure ou chiffre de contrôle).',
    ],

    'dsar' => [
        'title' => 'Dossiers d\'admission',
        'list_title' => 'Dossier d\'admission, version :revision',
        'column' => [
            'topic' => 'Donnée',
            'value' => 'Contenu',
        ],
        'legal_basis' => 'Base juridique de la conservation',
        'legal_basis_value' => 'Art. 6, par. 1, point c RGPD en liaison avec :basis',
        'restricted' => 'présente — communication uniquement avec le droit « :right »',
    ],

    'demo' => [
        'request' => 'Comptabilité courante, paie de trois salariés et comptes annuels à partir du mois prochain.',
        'notes' => 'Premier entretien au cabinet ; les documents du conseiller précédent suivent par téléversement.',
        'purpose' => 'Conseil fiscal : comptabilité, paie et comptes annuels de la société.',
    ],

    'gwg' => [
        'label' => 'Loi allemande sur le blanchiment (GwG)',
        'protocol_title' => 'Identification et note de risque : :name',
        'protocol_description' => 'Issu du dossier d\'admission (version :revision). Niveau de risque : :risk. Motif : :reason',
        'document' => [
            'id_copy' => 'Copie de la pièce d\'identité ou de l\'extrait du registre (§ 8 al. 2 GwG)',
        ],
        'section' => [
            'identification' => 'GwG : Identification du cocontractant (§ 11 GwG)',
            'verification' => 'GwG : Vérification de l\'identité (§ 12 GwG)',
            'beneficial_owners' => 'GwG : Bénéficiaires effectifs (§ 3 GwG)',
            'pep' => 'GwG : Personne politiquement exposée (§ 15 al. 3 n° 1 GwG)',
            'purpose' => 'GwG : Objet et nature de la relation d\'affaires (§ 10 al. 1 n° 3 GwG)',
            'risk' => 'GwG : Évaluation des risques (§§ 14, 15 GwG)',
        ],
        'field' => [
            'party_kind' => 'Le cocontractant est',
            'first_name' => 'Prénom',
            'last_name' => 'Nom',
            'birth_place' => 'Lieu de naissance',
            'birth_date' => 'Date de naissance',
            'nationality' => 'Nationalité',
            'home_address' => 'Adresse du domicile',
            'company_name' => 'Raison sociale, nom ou dénomination',
            'legal_form' => 'Forme juridique',
            'register_number' => 'Numéro d\'immatriculation',
            'seat_address' => 'Adresse du siège ou de l\'établissement principal',
            'representatives' => 'Membres de l\'organe de représentation ou représentants légaux',
            'verification_method' => 'Vérifié au moyen de',
            'id_document_kind' => 'Type de document',
            'id_document_number' => 'Numéro du document',
            'id_issuer' => 'Autorité de délivrance',
            'verified_on' => 'Vérifié le',
            'beneficial_owners' => 'Bénéficiaires effectifs',
            'transparency_register_checked' => 'Registre de transparence consulté',
            'transparency_register_on' => 'Registre de transparence : consulté le',
            'transparency_register_reference' => 'Registre de transparence : extrait ou référence',
            'pep_status' => 'Personne politiquement exposée',
            'pep_details' => 'Précisions sur la fonction ou la relation',
            'purpose' => 'Objet et nature envisagée de la relation d\'affaires',
            'suspicion_checked' => 'Indices de blanchiment ou de financement du terrorisme vérifiés',
            'lower_risk_factors' => 'Facteurs de risque moindre (annexe 1 GwG)',
            'higher_risk_factors' => 'Facteurs de risque plus élevé (annexe 2 GwG)',
            'risk_level' => 'Niveau de risque',
            'risk_reason' => 'Justification du niveau de risque',
        ],
        'help' => [
            'party_kind' => 'Personne physique : § 11 al. 4 n° 1 GwG ; personne morale ou société de personnes : n° 2.',
            'verification_method' => 'Avec une pièce d\'identité ou un extrait du registre : noter le type, le numéro et l\'autorité de délivrance et classer une copie (§ 8 al. 2 GwG).',
            'id_document_number' => 'Enregistré chiffré et visible uniquement avec le droit relatif aux données d\'identité.',
            'beneficial_owners' => 'Nom, nature et étendue de la participation ou du contrôle ; pour une personne physique agissant pour son compte, elle-même.',
            'transparency_register_checked' => 'Preuve de l\'inscription ou extrait du registre de transparence (§ 11 al. 5, § 12 al. 3 GwG).',
            'pep_status' => 'Des mesures de vigilance renforcées s\'appliquent à une personne politiquement exposée — le niveau de risque est alors élevé.',
            'suspicion_checked' => 'Le dossier consigne uniquement la vérification ; aucune déclaration à la FIU n\'est faite depuis l\'application.',
            'lower_risk_factors' => 'Facteurs non exhaustifs selon l\'annexe 1 GwG.',
            'higher_risk_factors' => 'Facteurs non exhaustifs selon l\'annexe 2 GwG.',
            'risk_level' => 'Faible : vigilance simplifiée (§ 14), normal : générale (§ 10), élevé : renforcée (§ 15). Détermine le délai de mise à jour.',
            'risk_reason' => 'Obligatoire pour faible et élevé (§ 8 al. 1 phrase 1 n° 2 GwG).',
        ],
        'option' => [
            'party_kind' => [
                'natural' => 'Personne physique',
                'legal' => 'Personne morale ou société de personnes',
            ],
            'verification_method' => [
                'id_document' => 'Pièce d\'identité officielle avec photo (carte d\'identité, passeport)',
                'eid' => 'Preuve d\'identité électronique (eID)',
                'qes' => 'Signature électronique qualifiée',
                'register_extract' => 'Extrait du registre du commerce ou des coopératives',
                'formation_documents' => 'Documents constitutifs',
                'own_register_check' => 'Consultation propre et documentée du registre',
            ],
            'pep_status' => [
                'no' => 'Non',
                'pep' => 'Oui, personne politiquement exposée',
                'family' => 'Membre de la famille d\'une personne politiquement exposée',
                'associate' => 'Personne connue pour lui être étroitement associée',
            ],
            'lower_risk_factors' => [
                'listed_company' => 'Société cotée soumise à des obligations de publication',
                'public_body' => 'Administration ou entreprise publique',
                'low_risk_area' => 'Siège ou domicile dans une zone à risque moindre',
                'low_risk_product' => 'Prestation à faible risque',
            ],
            'higher_risk_factors' => [
                'unusual_circumstances' => 'Circonstances inhabituelles de la relation d\'affaires',
                'high_risk_area' => 'Siège ou domicile dans une zone à haut risque',
                'asset_holding_vehicle' => 'Structure de détention d\'actifs personnels',
                'nominee_shareholders' => 'Actionnaires apparents ou actions au porteur',
                'cash_intensive' => 'Activité utilisant beaucoup d\'espèces',
                'complex_structure' => 'Structure de propriété inhabituellement complexe',
                'no_personal_contact' => 'Pas de contact personnel sans mesures de sécurité',
                'unknown_third_party_payments' => 'Paiements de tiers inconnus ou non liés',
                'high_risk_goods' => 'Commerce de pétrole, d\'armes, de métaux précieux, de tabac, de biens culturels ou d\'espèces protégées',
            ],
        ],
        'error' => [
            'pep_requires_high' => 'Des mesures de vigilance renforcées s\'appliquent à une personne politiquement exposée : choisissez le niveau de risque « élevé » (§ 15 al. 3 n° 1 GwG).',
            'copy_missing' => 'La copie du document manque (§ 8 al. 2 GwG).',
        ],
    ],
];

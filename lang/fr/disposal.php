<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : disposal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Dossier d'élimination (feature 100, MVP-474/475) : liste, dossier, dialogues
// et PDF de justificatif client. Libellés d'enum et messages backend inline.
return [
    'eyebrow' => 'Élimination',

    'index' => [
        'title' => 'Dossiers d\'élimination',
        'subtitle' => 'Enlèvement, liste des appareils, traitement des supports de données et justificatifs de l\'éliminateur — traçable jusqu\'au justificatif client.',
        'empty' => 'Aucun dossier d\'élimination — créer le premier dossier via le dialogue.',
        'kpi' => [
            'open' => 'Dossiers ouverts',
            'hazardous_open' => 'Ouverts avec déchets dangereux',
            'completed_year' => 'Clôturés (année en cours)',
        ],
        'filter' => [
            'hazardous_only' => 'dangereux uniquement',
        ],
        'col' => [
            'items' => 'Positions',
            'picked_up' => 'Enlèvement',
        ],
    ],

    'field' => [
        'site' => 'Site d\'intervention',
        'diary_entry' => 'Mission',
        'picked_up_on' => 'Date d\'enlèvement',
        'total_weight' => 'Poids total (kg)',
        'created' => 'Créé',
        'cancelled_at' => 'Annulé le',
        'cancel_reason' => 'Motif d\'annulation',
        'completed_at' => 'Clôturé le',
        'completed_by' => 'Clôturé par',
    ],

    'form' => [
        'title_create' => 'Nouveau dossier d\'élimination',
        'title_edit' => 'Modifier le dossier d\'élimination',
        'submit_create' => 'Créer le dossier',
        'group_assignment' => 'Client et intervention',
        'group_pickup' => 'Enlèvement et détails',
        'site' => 'Site d\'intervention (optionnel)',
        'site_none' => 'sans site d\'intervention',
        'diary_entry' => 'Mission/dossier (optionnel)',
        'diary_entry_none' => 'sans référence de mission',
    ],

    'show' => [
        'nav' => 'Dossier d\'élimination',
        'title' => 'Dossier d\'élimination :number',
        'section' => [
            'job' => 'Dossier',
            'blockers' => 'Contrôle de clôture',
            'items' => 'Liste des appareils',
            'handovers' => 'Remises à l\'éliminateur',
            'signature' => 'Confirmation de prise en charge',
            'record' => 'Justificatif client',
        ],
    ],

    'badge' => [
        'hazardous' => 'dangereux',
        'signed' => 'Prise en charge signée',
    ],

    'item' => [
        'title_create' => 'Saisir une position',
        'title_edit' => 'Modifier la position',
        'group_device' => 'Appareil',
        'group_disposal' => 'Élimination et supports de données',
        'weight' => 'Poids (kg)',
        'condition_note' => 'Note d\'état',
        'avv_code' => 'Code déchet (AVV/CED)',
        'avv_hint' => 'Astérisque * = déchet dangereux — la classification est déduite automatiquement.',
        'has_data_storage' => 'L\'appareil contient des supports de données',
        'note' => 'Note',
        'empty' => 'Aucune position d\'appareil — ajouter des appareils via « Saisir une position ».',
        'col' => [
            'device' => 'Fabricant/modèle',
            'weight' => 'Poids (kg)',
            'avv' => 'Code déchet (AVV/CED)',
            'data_storage' => 'Supports de données',
        ],
        'treatments_count' => '1 traitement|:count traitements',
        'treatment_missing' => 'Traitement manquant',
    ],

    'treatment' => [
        'title_create' => 'Saisir un traitement de support de données',
        'group_method' => 'Procédé et norme',
        'group_evidence' => 'Exécution et justificatif',
        'media_type' => 'Type de support de données',
        'method' => 'Procédé',
        'din_category' => 'Catégorie de matériau DIN 66399',
        'security_level' => 'Niveau de sécurité (1–7)',
        'protection_class' => 'Classe de protection',
        'protection_class_none' => 'sans indication',
        'protection_class_short' => 'Classe de protection :class',
        'treated_at' => 'Date/heure',
        'performed_by' => 'Exécutant',
        'evidence_reference' => 'Référence de justificatif/certificat',
        'please_select' => '-- veuillez choisir --',
    ],

    'handover' => [
        'title_create' => 'Saisir une remise à l\'éliminateur',
        'group_proof' => 'Éliminateur et justificatif',
        'group_attachment' => 'Pièce et note',
        'disposer' => 'Éliminateur',
        'proof_type' => 'Type de justificatif',
        'document_number' => 'Numéro de pièce',
        'handed_over_on' => 'Date de remise',
        'certificate_reference' => 'Référence du certificat EfbV',
        'proof_file' => 'Fichier justificatif (optionnel)',
        'proof_file_hint' => 'PDF, JPG ou PNG — 10 Mo maximum. Le justificatif est archivé comme document GED.',
        'note' => 'Note',
        'no_disposers' => 'Aucune entreprise d\'élimination certifiée enregistrée.',
        'create_disposer' => 'Créer l\'éliminateur comme contact externe',
        'empty' => 'Aucune remise à un éliminateur saisie pour l\'instant.',
        'col' => [
            'disposer' => 'Éliminateur',
            'proof_type' => 'Type de justificatif',
            'document_number' => 'Numéro de pièce',
            'certificate' => 'Référence EfbV',
            'document' => 'Document GED',
        ],
    ],

    'sign' => [
        'signer_name' => 'Nom de la personne prenant en charge',
        'signed_at' => 'Signé le',
        'hash' => 'Somme de contrôle',
        'hint' => '« Confirmer la prise en charge » enregistre la signature de manière infalsifiable.',
        'missing' => 'Aucune signature de prise en charge disponible.',
    ],

    'record' => [
        'released_hint' => 'Le justificatif client est publié dans le portail client.',
        'pending_hint' => 'Le justificatif client est généré automatiquement à la clôture du dossier.',
    ],

    'cancel' => [
        'title' => 'Annuler le dossier d\'élimination',
        'intro' => 'L\'annulation est définitive et consignée avec motif dans la chaîne de traçabilité.',
        'reason' => 'Motif',
    ],

    'action' => [
        'create' => 'Nouveau dossier d\'élimination',
        'collect' => 'Saisir l\'enlèvement',
        'start_treatment' => 'Démarrer le traitement',
        'hand_over' => 'Remettre à l\'éliminateur',
        'pdf_preview' => 'PDF du justificatif (aperçu)',
        'add_item' => 'Saisir une position',
        'add_treatment' => 'Saisir un traitement',
        'add_handover' => 'Saisir une remise',
        'sign' => 'Confirmer la prise en charge',
    ],

    'confirm' => [
        'complete' => 'Clôturer le dossier ? Le justificatif client est généré et publié, et les actifs liés sont mis au rebut.',
        'delete_item' => 'Vraiment supprimer cette position d\'appareil ?',
        'delete_treatment' => 'Vraiment supprimer ce traitement de support de données ?',
        'delete_handover' => 'Vraiment supprimer cette remise à l\'éliminateur ?',
    ],

    'pdf' => [
        'title' => 'Justificatif de prise en charge et d\'élimination',
        'number' => 'Numéro de dossier',
        'customer' => 'Client',
        'picked_up_on' => 'Date d\'enlèvement',
        'responsible' => 'Responsable',
        'status' => 'Statut',
        'total_weight' => 'Poids total',
        'items' => 'Liste des appareils',
        'treatments' => 'Justificatif de protection des données et des supports (DIN 66399)',
        'handovers' => 'Justificatif d\'élimination et de destination',
        'confirmation' => 'Confirmation',
        'customer_signature' => 'Prise en charge par le client',
        'not_signed' => 'Non signé.',
        'provider' => 'Prestataire',
        'completed_at' => 'Clôturé le',
        'hazardous_suffix' => '(dangereux)',
        'col' => [
            'category' => 'Catégorie',
            'device' => 'Fabricant/modèle',
            'serial' => 'Numéro de série',
            'quantity' => 'Quantité',
            'weight' => 'Poids (kg)',
            'avv' => 'Code déchet (AVV/CED)',
            'media_type' => 'Type de support',
            'method' => 'Procédé',
            'din' => 'DIN 66399',
            'protection_class' => 'Classe de protection',
            'treated_at' => 'Date/heure',
            'performed_by' => 'Exécutant',
            'evidence' => 'N° de justificatif/certificat',
            'disposer' => 'Éliminateur',
            'proof_type' => 'Type de justificatif',
            'document_number' => 'Numéro de pièce',
            'handed_over_on' => 'Date',
            'certificate' => 'Certificat EfbV',
        ],
        'footer' => [
            'hash' => 'Somme de contrôle',
            'generated' => 'Généré le :at',
        ],
    ],
];

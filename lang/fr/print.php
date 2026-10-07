<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : print.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Imprimerie / copie (MVP-459, profil sectoriel druck-kopiershop).
return [
    'document_title' => 'Données d\'impression :number',

    'nav' => [
        'section' => 'Imprimerie & copie',
    ],

    'orders' => [
        'title' => 'Ordres d\'impression',
        'subtitle' => 'Réception des données, preflight, bon à tirer, production, contrôle qualité et remise — de façon reproductible sur l\'ordre de fabrication.',
        'detail_title' => 'Ordre d\'impression',
        'empty' => 'Aucun ordre d\'impression sur la période — les nouveaux ordres se créent via le dialogue.',
        'kpi' => [
            'open' => 'Ordres d\'impression ouverts',
        ],
        'action' => [
            'create' => 'Nouvel ordre d\'impression',
            'create_submit' => 'Créer l\'ordre',
            'manufacturing' => 'Ordre de fabrication',
            'bind_file' => 'Lier le fichier',
            'run_preflight' => 'Lancer le preflight',
            'override' => 'Déroger avec motif',
            'manual_preflight' => 'Enregistrer le constat manuel',
            'approve' => 'Donner le bon à tirer',
            'start_production' => 'Démarrer la production',
            'resume_production' => 'Reprendre la production',
            'quality_check' => 'Documenter le CQ',
            'issue' => 'Remettre',
            'cancel' => 'Annuler',
        ],
    ],

    'section' => [
        'order' => 'Ordre',
        'file' => 'Fichier de production & preflight',
        'approval' => 'Bon à tirer & instantané',
        'production' => 'Production, CQ & remise',
        'claims' => 'Réclamations',
    ],

    'field' => [
        'article' => 'Article/produit imprimé',
        'quantity' => 'Quantité',
        'unit' => 'Unité',
        'customer_optional' => 'Client (facultatif)',
        'walk_in' => 'Client de passage (sobre en données)',
        'due_at' => 'Échéance',
        'output_kind' => 'Mode de remise',
        'files_retain_until' => 'Rétention du fichier jusqu\'au',
        'preflight' => 'Preflight',
        'file' => 'Fichier',
        'file_hash' => 'Somme de contrôle (SHA-256)',
        'file_bound_at' => 'Lié le',
        'preflight_provider' => 'Outil de contrôle',
        'preflight_at' => 'Contrôlé le',
        'override_reason' => 'Motif de dérogation',
        'manual_errors' => 'Erreurs (une par ligne)',
        'manual_warnings' => 'Avertissements (un par ligne)',
        'approved_by' => 'Validé par',
        'approved_at' => 'Validé le',
        'approved_file_hash' => 'Somme de contrôle validée',
        'machine' => 'Machine',
        'without_machine' => 'sans liaison machine',
        'production_started_at' => 'Début de production',
        'qc_status' => 'Résultat CQ',
        'qc_by' => 'CQ par',
        'qc_note' => 'Note CQ',
        'issued_at' => 'Remis le',
        'handover_name' => 'Remis à',
        'handover_note' => 'Note de remise',
        'shipment' => 'Expédition',
        'reason' => 'Motif',
        'good_total' => 'Bonne quantité',
        'scrap_total' => 'Gâche',
        'cancel_reason' => 'Motif d\'annulation',
    ],

    'snapshot' => [
        'final_format' => 'Format fini',
        'pages' => 'Pages',
        'orientation' => 'Orientation',
        'bleed_mm' => 'Fond perdu (mm)',
        'safety_mm' => 'Marge de sécurité (mm)',
        'color_mode' => 'Chromie',
        'color_profile' => 'Profil colorimétrique',
        'spot_colors' => 'Tons directs',
        'material' => 'Support',
        'grammage' => 'Grammage',
        'quantity' => 'Quantité',
        'due_date' => 'Échéance',
        'finishing' => 'Façonnage',
    ],

    'badge' => [
        'approval_stale' => 'Fichier modifié — bon à tirer invalide',
        'file_purged' => 'Fichier supprimé après rétention',
    ],

    'qc' => [
        'passed' => 'Libéré',
        'rework' => 'Reprise',
        'blocked' => 'Bloqué',
    ],

    'hint' => [
        'retention' => 'À l\'échéance, seul le fichier client est supprimé — ordre, instantané et somme de contrôle demeurent comme preuve commerciale.',
        'no_snapshot' => 'Pas encore de bon à tirer — les paramètres sont figés en instantané immuable lors de la validation.',
        'counter_minimal' => 'Vente au comptoir : aucune donnée personnelle requise.',
        'claim_reference' => 'Le dossier est lié à l\'ordre d\'impression — fichier approuvé, instantané de production et résultat CQ restent référencés.',
    ],

    'flash' => [
        'created' => 'Ordre d\'impression créé.',
        'file_bound' => 'Fichier de production lié (somme de contrôle enregistrée).',
        'preflight_recorded' => 'Constat de preflight enregistré.',
        'preflight_overridden' => 'Preflight dérogé avec motif.',
        'approved' => 'Bon à tirer donné — instantané figé.',
        'production_started' => 'Production en cours.',
        'quality_checked' => 'Contrôle qualité documenté.',
        'issued' => 'Ordre remis.',
        'cancelled' => 'Ordre annulé.',
        'claim_opened' => 'Réclamation :number créée.',
    ],

    'preflight' => [
        'file_missing' => 'Le fichier de production est introuvable dans le stockage.',
        'file_empty' => 'Le fichier est vide (0 octet).',
        'mime_unexpected' => 'Type de fichier inattendu « :mime » — à vérifier pour l\'impression.',
        'pdf_header_invalid' => 'Le fichier est déclaré PDF mais n\'a pas d\'en-tête PDF valide.',
    ],

    'error' => [
        'customer_approval_status' => 'La validation du client ne peut être demandée que pendant le contrôle des données.',
        'customer_approval_not_pending' => 'Aucune demande de validation n\'est en attente.',
        'customer_approval_stale' => 'Le fichier d\'impression a changé entre-temps — veuillez attendre la nouvelle demande de validation.',
        'customer_decline_reason_required' => 'Veuillez motiver le refus.',
        'customer_approval_missing' => 'La validation du client pour le fichier d\'impression lié est manquante.',
        'customer_parameter_mismatch' => ':parameter diffère de la validation du client (validé : :approved). Veuillez redemander la validation.',
        'order_already_specialized' => 'Un ordre d\'impression existe déjà pour cet ordre de fabrication (1:1).',
        'order_closed' => 'L\'ordre d\'impression est clos — le fichier ne peut plus être modifié.',
        'document_mismatch' => 'Document/version incohérents ou n\'appartenant pas à cette organisation.',
        'file_required' => 'Lier d\'abord un fichier de production.',
        'provider_unsupported' => 'L\'outil de contrôle ne prend pas en charge ce type de fichier.',
        'override_only_failed' => 'Seules les erreurs bloquantes du preflight peuvent être dérogées.',
        'override_reason_required' => 'La dérogation exige un motif.',
        'preflight_blocks_approval' => 'Preflight en attente ou en échec — validation seulement après contrôle ou dérogation motivée.',
        'parameter_required' => 'Paramètre obligatoire manquant : :parameter.',
        'approval_stale' => 'Le fichier a été modifié après validation — l\'ordre redevient à contrôler/valider.',
        'machine_foreign' => 'La machine n\'appartient pas à cette organisation.',
        'machine_inspection_overdue' => 'Machine avec contrôle/étalonnage obligatoire en retard — démarrage interdit.',
        'invalid_transition' => 'Changement de statut non autorisé.',
        'invalid_transition_detail' => 'Changement de statut non autorisé : :from → :to.',
        'shipment_required' => 'La remise par expédition exige une expédition existante.',
        'handover_required' => 'Le retrait exige une preuve de remise (nom).',
        'cancel_reason_required' => 'L\'annulation exige un motif.',
        'file_missing_storage' => 'La version du fichier n\'existe pas dans le stockage.',
    ],

    // Reklamation am Druckauftrag (Issue #75).
    'claim' => [
        'title' => 'Réclamation ordre d\'impression :number',
        'none' => 'Aucune réclamation pour cet ordre.',
        'description' => 'Description',
        'affected_quantity' => 'Quantité concernée',
        'affected_quantity_note' => 'Quantité concernée : :quantity',
        'open' => 'Créer une réclamation',
    ],
    'intake' => [
        'target_label' => 'Ordre d\'impression :number',
        'version_note' => 'Repris de la demande client',
        'article_invalid' => 'Veuillez choisir un article fabricable.',
        'file_invalid' => 'Le fichier choisi n\'appartient pas à la demande.',
        'production_file' => 'Fichier de production',
        'production_file_hint' => 'Les autres fichiers restent joints à la demande. Il peut aussi être défini plus tard sur l\'ordre d\'impression.',
        'production_file_later' => 'Définir plus tard',
        'panel_title' => 'Ordre d\'impression',
        'no_production_file' => 'Pas encore de fichier de production',
        'customer_approval' => 'Validation du client',
        'open_order' => 'Ouvrir l\'ordre d\'impression',
        'origin_title' => 'Issue de la demande client',
        'quote' => 'Devis :number (version :version)',
        'intake_files' => 'Fichiers de la demande',
        'bind_file' => 'Définir comme fichier de production',
        'bind_confirm' => 'Lier ce fichier comme fichier de production ? Les contrôles et validations d\'un autre fichier expirent.',
        'request_approval' => 'Demander la validation du client',
        'portal_title' => 'Bon à tirer',
        'portal_waiting' => 'Nous contrôlons vos données d\'impression. Dès qu\'elles sont prêtes, nous vous demanderons ici votre validation.',
        'portal_confirm' => 'Par votre validation, vous confirmez exactement ce fichier et ces paramètres. L\'impression ne démarre qu\'après notre validation interne.',
        'version' => 'Version :version',
        'checksum' => 'Somme de contrôle (SHA-256)',
        'download_file' => 'Télécharger le fichier',
        'decline_reason' => 'Motif (uniquement en cas de refus)',
        'approve' => 'Valider les données d\'impression',
        'decline' => 'Refuser',
        'flash' => [
            'approval_requested' => 'Validation du client demandée.',
            'approved' => 'Merci — votre bon à tirer a été consigné.',
            'declined' => 'Votre refus a été transmis.',
        ],
        'approval' => [
            'approved' => 'Validé le :date',
            'pending' => 'Demandé le :date',
            'declined' => 'Refusé le :date',
            'none' => 'Pas encore demandé',
        ],
        'stage' => [
            'approval_pending' => 'Bon à tirer requis',
            'approval_declined' => 'Les données d\'impression sont en cours de révision',
            'data_check' => 'Les données d\'impression sont en cours de contrôle',
            'approved' => 'Validé — production planifiée',
            'in_production' => 'En production',
            'ready_pickup' => 'Prêt à être retiré',
            'ready_shipping' => 'Prêt à être expédié',
            'ready_counter' => 'Prêt à être retiré',
            'issued' => 'Remis',
            'cancelled' => 'Annulé',
        ],
        'next_step' => [
            'approve' => 'Veuillez vérifier le fichier et les paramètres et valider les données d\'impression.',
        ],
    ],
];

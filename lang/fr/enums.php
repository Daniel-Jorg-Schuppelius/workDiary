<?php

return [
    'event' => [
        'type' => [
            'training' => 'Formation',
            'workshop' => 'Atelier',
            'conference' => 'Conférence',
            'meeting' => 'Réunion',
            'internal_briefing' => 'Briefing interne',
            'external_visit' => 'Visite externe',
        ],
        'status' => [
            'planned' => 'Planifié',
            'confirmed' => 'Confirmé',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
        ],
        'visibility' => [
            'internal' => 'Interne',
            'external' => 'Externe',
            'public' => 'Public',
        ],
        'participant' => [
            'role' => [
                'organizer' => 'Organisateur',
                'trainer' => 'Formateur',
                'attendee' => 'Participant',
                'optional' => 'Facultatif',
            ],
            'status' => [
                'invited' => 'Invité',
                'accepted' => 'Accepté',
                'declined' => 'Refusé',
                'attended' => 'Présent',
                'no_show' => 'Absent',
            ],
        ],
        'reminder' => [
            'channel' => [
                'mail' => 'E-mail',
                'webpush' => 'Push',
                'database' => 'Dans l\'app',
            ],
        ],
    ],
    'vehicle' => [
        'type' => [
            'car' => 'Voiture',
            'van' => 'Camionnette',
            'truck' => 'Camion',
            'bicycle' => 'Vélo',
            'other' => 'Autre',
        ],
        'propulsion' => [
            'diesel' => 'Diesel',
            'petrol' => 'Essence',
            'gas' => 'Gaz',
            'hybrid' => 'Hybride',
            'electric' => 'Électrique',
            'muscle' => 'Force musculaire',
            'other' => 'Autre',
        ],
        'ownership' => [
            'owned' => 'En propriété',
            'leased' => 'Leasing',
            'rental' => 'Location',
        ],
    ],
    'sickness' => [
        'kind' => [
            'initial' => 'Certificat initial',
            'follow_up' => 'Certificat de prolongation',
        ],
    ],
    'tour' => [
        'status' => [
            'draft' => 'Brouillon',
            'planned' => 'Planifié',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
        ],
    ],
    'activity' => [
        'category_type' => [
            'admin' => 'Administration',
            'training' => 'Formation',
            'meeting' => 'Réunion',
            'internal' => 'Interne',
            'travel' => 'Déplacement',
            'break' => 'Pause',
            'absence' => 'Absence',
            'standby' => 'Astreinte',
            'other' => 'Autre',
        ],
    ],
    'vacation' => [
        'type' => [
            'vacation' => 'Congés',
            'sick' => 'Maladie',
            'special' => 'Congé spécial',
            'unpaid' => 'Non payé',
        ],
        'status' => [
            'pending' => 'En attente',
            'approved' => 'Approuvé',
            'rejected' => 'Refusé',
            'cancelled' => 'Annulé',
        ],
    ],
    'project' => [
        'status' => [
            'active' => 'Actif',
            'paused' => 'En pause',
            'archived' => 'Archivé',
        ],
    ],
    'task' => [
        'status' => [
            'open' => 'Ouvert',
            'in_progress' => 'En cours',
            'done' => 'Terminé',
        ],
        'priority' => [
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'urgent' => 'Urgente',
        ],
    ],
    'timesheet' => [
        'status' => [
            'draft' => 'Brouillon',
            'submitted' => 'Soumis',
            'signed' => 'Signé',
            'locked' => 'Verrouillé',
        ],
        'kind' => [
            'project' => 'Projet',
            'personal_day' => 'Journée personnelle',
        ],
    ],
    'time_entry' => [
        'kind' => [
            'work' => 'Travail',
            'travel' => 'Déplacement',
            'standby' => 'Astreinte',
        ],
    ],
    'expense' => [
        'status' => [
            'draft' => 'Brouillon',
            'pending' => 'Soumis',
            'approved' => 'Approuvé',
            'rejected' => 'Refusé',
            'cancelled' => 'Annulé',
            'reimbursed' => 'Remboursé',
            'invoiced' => 'Facturé',
        ],
        'payment_method' => [
            'private_paid' => 'Payé à titre privé',
            'company_card' => 'Carte d\'entreprise',
            'cash' => 'Caisse',
            'bank_transfer' => 'Virement bancaire',
        ],
    ],
    'per_diem' => [
        'day_kind' => [
            'departure_day' => 'Jour de départ',
            'full_day' => 'Journée complète de voyage',
            'return_day' => 'Jour de retour',
            'single_day' => 'Voyage d\'une journée',
        ],
        'trip_status' => [
            'draft' => 'Brouillon',
            'converted' => 'Converti en frais',
            'cancelled' => 'Annulé',
        ],
    ],
    'notification' => [
        'event' => [
            'openIssue' => [
                'assigned' => 'Point ouvert assigné',
                'dueSoon' => 'Point ouvert bientôt dû',
                'overdue' => 'Point ouvert en retard',
            ],
            'communication' => [
                'followupDueSoon' => 'Action de suivi bientôt due',
                'followupOverdue' => 'Action de suivi en retard',
            ],
            'document' => [
                'expiringSoon' => 'Document expirant bientôt',
                'expired' => 'Document expiré',
            ],
            'timeCorrection' => [
                'requested' => 'Demande de correction de temps soumise',
                'decided' => 'Demande de correction de temps décidée',
            ],
            'monthClosure' => [
                'submitted' => 'Clôture mensuelle soumise',
            ],
            'isms' => [
                'certificateExpiring' => 'Certificat ISMS arrive à expiration',
                'correctiveActionOverdue' => 'Action corrective SMSI en retard',
                'riskReviewDue' => 'Revue de risque SMSI à échéance',
            ],
        ],
        'channel' => [
            'inApp' => 'Dans l’application',
            'mail' => 'E-mail',
            'push' => 'Push',
        ],
    ],

    'open-issue' => [
        'status' => [
            'open' => 'Ouvert',
            'inProgress' => 'En cours',
            'blocked' => 'Bloqué',
            'done' => 'Terminé',
            'wontDo' => 'Ne sera pas fait',
            'reopened' => 'Rouvert',
        ],
        'severity' => [
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'critical' => 'Critique',
        ],
        'source' => [
            'manual' => 'Manuel',
            'protocolDefect' => 'Depuis le protocole',
            'communicationFollowup' => 'Depuis la communication',
        ],
        'visibility' => [
            'internal' => 'Interne',
            'customer' => 'Visible par le client',
        ],
    ],
    'communication' => [
        'type' => [
            'call' => 'Appel téléphonique',
            'email' => 'E-mail',
            'meeting' => 'Entretien sur site',
            'videocall' => 'Visioconférence',
            'chat' => 'Chat / messagerie',
            'internal' => 'Concertation interne',
            'decision' => 'Décision',
            'letter' => 'Courrier / fax',
            'other' => 'Autre',
        ],
        'direction' => [
            'inbound' => 'Entrant',
            'outbound' => 'Sortant',
            'internal' => 'Interne',
        ],
        'visibility' => [
            'internal' => 'Interne',
            'customer' => 'Visible client',
        ],
        'party' => [
            'internal' => 'Interne',
            'customer' => 'Client',
            'thirdParty' => 'Tiers',
        ],
    ],
    'knowledge' => [
        'status' => [
            'draft' => 'Brouillon',
            'published' => 'Publié',
            'archived' => 'Archivé',
        ],
        'visibility' => [
            'internal' => 'Interne (toute l’organisation)',
            'team' => 'Limité à l’équipe',
        ],
    ],
    'form' => [
        'template_status' => [
            'draft' => 'Brouillon',
            'active' => 'Actif',
            'archived' => 'Archivé',
        ],
        'field_type' => [
            'text' => 'Texte',
            'textarea' => 'Texte multiligne',
            'number' => 'Nombre',
            'date' => 'Date',
            'select' => 'Sélection',
            'checkbox' => 'Case à cocher',
        ],
    ],
    'document' => [
        'type' => [
            'contract' => 'Contrat',
            'testReport' => 'Rapport de contrôle',
            'certificate' => 'Certificat',
            'manual' => 'Manuel',
            'datasheet' => 'Fiche technique',
            'manufacturerDoc' => 'Document fabricant',
            'permit' => 'Autorisation',
            'insurance' => 'Assurance',
            'other' => 'Autre',
        ],
        'status' => [
            'draft' => 'Brouillon',
            'active' => 'Actif',
            'expired' => 'Expiré',
            'archived' => 'Archivé',
        ],
    ],
    'protocol' => [
        'status' => [
            'draft' => 'Brouillon',
            'in_review' => 'En revue',
            'signed' => 'Signé',
            'archived' => 'Archivé',
            'superseded' => 'Remplacé',
        ],
        'type' => [
            'acceptance' => 'Réception',
            'service' => 'Intervention de service',
            'maintenance' => 'Maintenance',
            'handover' => 'Remise',
            'defect' => 'Rapport de défaut',
            'inspection' => 'Inspection',
            'siteVisit' => 'Visite sur site',
            'other' => 'Autre',
        ],
        'visibility' => [
            'internal' => 'Interne',
            'customer' => 'Visible par le client',
        ],
        'item-result' => [
            'ok' => 'OK',
            'notok' => 'Pas OK',
            'n_a' => 'Non applicable',
            'open' => 'Ouvert',
        ],
        'signature-role' => [
            'customer' => 'Client',
            'contractor' => 'Prestataire',
            'witness' => 'Témoin',
        ],
        'signature-method' => [
            'onscreen' => 'Signature à l\'écran',
            'portal' => 'Portail client',
            'emailLink' => 'Lien e-mail',
            'paper' => 'Papier',
        ],
        'item-type' => [
            'group' => 'Section',
            'text' => 'Texte libre',
            'boolean' => 'Élément oui/non',
            'choice' => 'Choix unique',
            'multichoice' => 'Choix multiple',
            'number' => 'Mesure / nombre',
            'range' => 'Plage cible',
            'date' => 'Date',
            'datetime' => 'Date et heure',
            'signature' => 'Signature',
            'photo' => 'Photo obligatoire',
            'file' => 'Document obligatoire',
            'defect' => 'Défaut',
            'measurement.timestamped' => 'Série de mesures',
            'procedure_step' => 'Étape de procédure',
            'signoff_internal' => 'Approbation interne',
        ],
        'item-photo-phase' => [
            'before' => 'Avant',
            'after' => 'Après',
            'detail' => 'Détail',
            'defect' => 'Défaut',
            'reference' => 'Référence',
        ],
    ],
    'procedure' => [
        'risk-level' => [
            'low' => 'Bas',
            'normal' => 'Normal',
            'high' => 'Élevé',
            'critical' => 'Critique',
        ],
        'step-type' => [
            'confirm' => 'Confirmation',
            'text' => 'Texte',
            'number' => 'Nombre/mesure',
            'choice' => 'Choix',
            'photo' => 'Photo',
            'file' => 'Fichier',
            'backup' => 'Enregistrement de sauvegarde',
            'signature' => 'Signature',
            'material' => 'Saisie de matériel',
            'dienstmittel' => 'Équipement de service',
            'freigabe' => 'Approbation (double contrôle)',
            'messreihe' => 'Série de mesures',
            'link_protocol' => 'Lier le protocole',
            'link_test' => 'Lier le test',
        ],
        'run-status' => [
            'open' => 'Ouvert',
            'inProgress' => 'En cours',
            'blocked' => 'Bloqué',
            'completed' => 'Terminé',
            'aborted' => 'Interrompu',
        ],
        'step-run-status' => [
            'pending' => 'En attente',
            'done' => 'Terminé',
            'n_a' => 'Non applicable',
            'failed' => 'Échoué',
            'deviated' => 'Écart',
            'blocked' => 'Bloqué',
        ],
        'backup-scope' => [
            'config' => 'Configuration',
            'database' => 'Base de données',
            'fullSystem' => 'Système complet',
            'customScript' => 'Script personnalisé',
        ],
        'backup-storage-target' => [
            'attachment' => 'Pièce jointe',
            'external' => 'Stockage externe',
        ],
        'backup-verify-method' => [
            'checksum' => 'Comparaison de somme de contrôle',
            'restoreCheck' => 'Test de restauration',
            'managerConfirmation' => 'Confirmation de la direction',
        ],
        'deviation-type' => [
            'not_applicable' => 'Non applicable',
            'not_possible' => 'Impossible',
            'partial' => 'Partiellement rempli',
            'alternative_method' => 'Méthode alternative',
            'failed_check' => 'Lecture hors tolérance',
            'material_substitute' => 'Matériel de substitution',
            'safety_block' => 'Interruption de sécurité',
            'customer_decline' => 'Refus du client',
        ],
        'deviation-severity' => [
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'critical' => 'Critique',
        ],
        'deviation-proposed-action' => [
            'none' => 'Aucune action de suivi',
            'open_issue' => 'Point ouvert',
            'new_diary_entry' => 'Nouvelle mission',
            'requalify' => 'Réexécuter',
            'escalate' => 'Escalade',
        ],
    ],
    'isms' => [
        'audit-package-status' => [
            'draft' => 'Brouillon',
            'finalized' => 'Finalisé',
        ],
        'audit-kind' => [
            'internal' => 'Interne',
            'external' => 'Externe',
            'supplier' => 'Fournisseur',
        ],
        'audit-status' => [
            'planned' => 'Planifié',
            'inPreparation' => 'En préparation',
            'inProgress' => 'En cours',
            'reportIssued' => 'Rapport émis',
            'closed' => 'Clôturé',
        ],
        'finding-kind' => [
            'nonconformityMajor' => 'Non-conformité majeure',
            'nonconformityMinor' => 'Non-conformité mineure',
            'observation' => 'Observation',
            'improvement' => 'Piste d’amélioration',
        ],
        'finding-status' => [
            'open' => 'Ouvert',
            'inCorrection' => 'En correction',
            'effectivenessCheck' => 'Contrôle d’efficacité',
            'closed' => 'Clôturé',
        ],
        'corrective-action-status' => [
            'open' => 'Ouverte',
            'inProgress' => 'En cours',
            'done' => 'Mise en œuvre',
            'effective' => 'Efficace',
            'ineffective' => 'Inefficace',
        ],
        'review-status' => [
            'draft' => 'Brouillon',
            'approved' => 'Approuvée',
        ],
        'assessment-kind' => [
            'gross' => 'Brut',
            'net' => 'Net',
            'target' => 'Cible',
        ],
        'assessment-status' => [
            'draft' => 'Brouillon',
            'approved' => 'Approuvée',
        ],
        'risk-category' => [
            'organizational' => 'Organisationnel',
            'technical' => 'Technique',
            'physical' => 'Physique',
            'personnel' => 'Personnel',
            'supplier' => 'Fournisseur',
        ],
        'risk-treatment' => [
            'avoid' => 'Éviter',
            'mitigate' => 'Réduire',
            'transfer' => 'Transférer',
            'accept' => 'Accepter',
        ],
        'risk-status' => [
            'identified' => 'Identifié',
            'analyzed' => 'Analysé',
            'treated' => 'Traité',
            'accepted' => 'Accepté',
            'closed' => 'Clôturé',
        ],
        'requirement-source' => [
            'catalog' => 'Catalogue de référence',
            'custom' => 'Exigence propre',
        ],
        'control-implementation-status' => [
            'open' => 'Ouvert',
            'partial' => 'Partiellement mis en œuvre',
            'implemented' => 'Mis en œuvre',
            'notApplicable' => 'Non applicable',
        ],
        'software-category' => [
            'os' => 'Système d\'exploitation',
            'application' => 'Application',
            'service' => 'Service',
            'library' => 'Bibliothèque',
            'other' => 'Autre',
        ],
        'support-status' => [
            'supported' => 'Pris en charge',
            'extendedSupport' => 'Support étendu',
            'endOfLife' => 'Fin de vie',
            'unknown' => 'Inconnu',
        ],
        'norm-conformity-status' => [
            'notAssessed' => 'Non évalué',
            'gapAnalysisDone' => 'Analyse des écarts effectuée',
            'inProgress' => 'En cours de mise en œuvre',
            'internallyAuditReady' => 'Prêt pour l’audit interne',
            'externalAuditPlanned' => 'Audit externe planifié',
            'certified' => 'Certifié',
            'certificateSuspended' => 'Certificat suspendu',
            'certificateExpired' => 'Certificat expiré',
        ],
    ],
    'surcharge' => [
        'kind' => [
            'night' => 'Nuit',
            'saturday' => 'Samedi',
            'sunday' => 'Dimanche',
            'holiday' => 'Jour férié',
            'custom' => 'Personnalisé',
        ],
    ],
    'finance' => [
        'billing-mode' => [
            'workdiary' => 'WorkDiary (local)',
            'lexoffice' => 'Lexoffice pilote',
            'datev' => 'DATEV pilote',
        ],
        'transfer-channel' => [
            'time' => 'Prestations/temps',
            'material' => 'Produits/matériel',
        ],
        'transfer-target' => [
            'lexoffice' => 'Lexoffice',
            'datev' => 'DATEV',
            'file' => 'Export de fichier',
        ],
        'transfer-status' => [
            'draft' => 'Brouillon',
            'confirmed' => 'Confirmé',
            'transferred' => 'Transféré',
            'failed' => 'Échoué',
            'voided' => 'Annulé',
        ],
    ],

    // Clôture journalière (MVP-015, docs/tagesabschluss.md §3/§5).
    'dayClosure' => [
        'status' => [
            'open' => 'Ouvert',
            'closed' => 'Clôturé',
            'correction' => 'En correction',
            'locked' => 'Verrouillé',
        ],
    ],
    'dayCorrection' => [
        'status' => [
            'pending' => 'En attente',
            'approved' => 'Approuvée',
            'rejected' => 'Rejetée',
        ],
    ],
];

<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : journal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Journal-Ereignisse (MVP-864): `journal.<modulcode>.<event>`, gelesen von
// JournalEntry::label(); Ereignisse mit Punkten liegen verschachtelt
// (`order.created` → ['order' => ['created' => …]]).
return [
    'finance' => [
        'analyzed' => 'Analysé',
        'blocked' => 'Bloqué',
        'cancelled' => 'Annulé',
        'completed' => 'Terminé',
        'cutover_executed' => 'Bascule effectuée',
        'item_decided' => 'Poste tranché',
        'parallel_run_started' => 'Fonctionnement parallèle démarré',
        'planned' => 'Planifié',
        'status_changed' => 'Statut modifié',
        'position_edited' => 'Poste modifié',
        'position_removed' => 'Poste supprimé',
        'positions_merged' => 'Postes fusionnés',
        'texts_edited' => 'Textes modifiés',
        'discarded' => 'Rejeté',
        'finalized' => 'Figé',
        'sources_removed' => 'Sources retirées',
        'return_processed' => 'Retour traité',
        'skonto_accepted' => 'Escompte accepté',
        'unmatched' => 'Affectation annulée',
        'accounting' => [
            'opening_balance_imported' => 'Bilan d\'ouverture importé',
        ],
    ],
    'privacy' => [
        'assessed' => 'Évalué',
        'authority_report_recorded' => 'Notification à l\'autorité enregistrée',
        'closed' => 'Clôturé',
        'controller_notified' => 'Responsable informé',
        'measure_added' => 'Mesure ajoutée',
        'measure_completed' => 'Mesure terminée',
        'notification_decided' => 'Obligation de notification tranchée',
        'opened' => 'Ouvert',
        'reported' => 'Signalé',
        'deadline_reminder' => 'Rappel de délai',
        'assigned' => 'Attribué',
        'decided' => 'Tranché',
        'identity_verified' => 'Identité vérifiée',
        'portal_email_confirmed' => 'E-mail du portail confirmé',
        'portal_receipt_failed' => 'Accusé de réception du portail en échec',
        'portal_receipt_sent' => 'Accusé de réception du portail envoyé',
        'portal_submitted' => 'Déposé via le portail',
        'shredded' => 'Détruit',
        'subject_export_generated' => 'Export de la personne concernée généré',
    ],
    'whistleblowing' => [
        'assigned' => 'Attribué',
        'deadline_reminder' => 'Rappel de délai',
        'decided' => 'Tranché',
        'identity_verified' => 'Identité vérifiée',
        'opened' => 'Ouvert',
        'portal_email_confirmed' => 'E-mail du portail confirmé',
        'portal_receipt_failed' => 'Accusé de réception du portail en échec',
        'portal_receipt_sent' => 'Accusé de réception du portail envoyé',
        'portal_submitted' => 'Déposé via le portail',
        'shredded' => 'Détruit',
        'subject_export_generated' => 'Export généré',
    ],
    'agile' => [
        'backlog' => [
            'added' => 'Ajouté au backlog',
            'removed' => 'Retiré du backlog',
            'reranked' => 'Backlog réordonné',
        ],
        'column' => [
            'moved' => 'Colonne changée',
        ],
        'sprint' => [
            'item_added' => 'Ajouté au sprint',
            'item_removed' => 'Retiré du sprint',
            'started' => 'Sprint démarré',
            'completed' => 'Sprint terminé',
            'cancelled' => 'Sprint annulé',
        ],
        'item' => [
            'blocked' => 'Bloqué',
            'unblocked' => 'Débloqué',
        ],
        'points' => [
            'changed' => 'Story points modifiés',
        ],
        'override' => [
            'wip' => 'Limite WIP outrepassée',
            'dod' => 'Definition of Done outrepassée',
            'criteria' => 'Critères d\'acceptation outrepassés',
        ],
        'epic' => [
            'assigned' => 'Épopée attribuée',
        ],
    ],
    'diary' => [
        'order' => [
            'created' => 'Ordre créé',
            'accept' => 'Ordre accepté',
            'start' => 'Ordre démarré',
            'pause' => 'Ordre mis en pause',
            'resume' => 'Ordre repris',
            'complete' => 'Ordre terminé',
            'acceptance' => 'Réception démarrée',
            'invoice' => 'Facture déclenchée',
            'cancel' => 'Ordre annulé',
        ],
        'dispatch' => [
            'gap_fill_applied' => 'Proposition de créneau libre appliquée',
            'gap_fill_dismissed' => 'Proposition de créneau libre écartée',
            'calendly_confirmed' => 'Demande Calendly confirmée',
        ],
        'issue' => [
            'created' => 'Point ouvert créé',
            'assigned' => 'Attribué',
            'started' => 'Démarré',
            'blocked' => 'Bloqué',
            'unblocked' => 'Débloqué',
            'completed' => 'Terminé',
            'wontDo' => 'Ne sera pas réalisé',
            'reopened' => 'Rouvert',
            'dueDateChanged' => 'Échéance modifiée',
            'severityChanged' => 'Gravité modifiée',
            'visibilityChanged' => 'Visibilité modifiée',
            'commentAdded' => 'Commentaire ajouté',
            'attachmentAdded' => 'Pièce jointe ajoutée',
        ],
    ],
    'time' => [
        'month' => [
            'approved' => 'Mois approuvé',
            'locked' => 'Mois verrouillé',
            'rejected' => 'Mois rejeté',
            'submitted' => 'Mois soumis',
        ],
        'export' => [
            'delivered' => 'Livré',
            'downloaded' => 'Téléchargé',
            'line_updated' => 'Ligne mise à jour',
            'preparing' => 'En préparation',
            'ready' => 'Prêt',
            'rejected' => 'Rejeté',
            'superseded' => 'Remplacé',
            'delivered_auto' => 'Livré automatiquement',
            'delivery_failed' => 'Livraison échouée',
        ],
    ],
    'procedure' => [
        'procedure' => [
            'runStarted' => 'Exécution démarrée',
            'stepCompleted' => 'Étape terminée',
            'stepFailed' => 'Étape échouée',
            'stepDeviated' => 'Étape avec écart',
            'stepNA' => 'Étape sans objet',
            'stepUnlocked' => 'Étape déverrouillée',
            'stepBlocked' => 'Étape bloquée',
            'runCompleted' => 'Exécution terminée',
            'runCompletionRejected' => 'Clôture refusée',
            'runAborted' => 'Exécution interrompue',
            'secondPersonAssigned' => 'Seconde personne attribuée',
            'secondPersonSigned' => 'Seconde personne a signé',
            'secondPersonRequested' => 'Seconde personne demandée',
            'secondPersonRevoked' => 'Seconde personne retirée',
            'backupRegistered' => 'Sauvegarde enregistrée',
            'backupVerified' => 'Sauvegarde vérifiée',
            'backupRejected' => 'Sauvegarde refusée',
            'deviationRecorded' => 'Écart enregistré',
            'deviationUpdated' => 'Écart mis à jour',
            'deviationActionTriggered' => 'Mesure d\'écart déclenchée',
            'criticalRiskAccepted' => 'Risque critique accepté',
        ],
    ],
    'protocol' => [
        'protocol' => [
            'created' => 'Procès-verbal créé',
            'itemAdded' => 'Poste ajouté',
            'itemRemoved' => 'Poste supprimé',
            'itemReordered' => 'Postes réordonnés',
            'itemFilled' => 'Poste renseigné',
            'requestedReview' => 'Vérification demandée',
            'returnedToDraft' => 'Remis en brouillon',
            'signed' => 'Signé',
            'archived' => 'Archivé',
            'supersededBy' => 'Remplacé par une version ultérieure',
            'attachmentAdded' => 'Pièce jointe ajoutée',
            'attachmentRemoved' => 'Pièce jointe supprimée',
            'signatureRequested' => 'Signature demandée',
            'signatureLinkOpened' => 'Lien de signature ouvert',
            'signatureRejected' => 'Signature refusée',
            'signatureLinkRevoked' => 'Lien de signature révoqué',
            'customerQueryRaised' => 'Question du client posée',
            'customerQueryAnswered' => 'Question du client répondue',
            'pdfRendered' => 'PDF généré',
            'pdfDownloaded' => 'PDF téléchargé',
            'item' => [
                'photoAdded' => 'Photo ajoutée',
                'photoRemoved' => 'Photo supprimée',
                'photoReordered' => 'Photos réordonnées',
                'photoUpdatedCaption' => 'Légende modifiée',
            ],
        ],
    ],
    'learning' => [
        'status_changed' => 'Statut modifié',
    ],
    'auth' => [
        'auth' => [
            'lockout' => 'Compte verrouillé',
            '2fa_failed' => 'Second facteur échoué',
            'password_reset_requested' => 'Réinitialisation du mot de passe demandée',
            'impossible_travel' => 'Déplacement impossible détecté',
        ],
        'wb' => [
            'login_failed' => 'Canal de signalement : connexion échouée',
        ],
        'api' => [
            'token_invalid' => 'Jeton API invalide',
        ],
        'webhook' => [
            'signature_invalid' => 'Signature de webhook invalide',
        ],
        'sso' => [
            'failed' => 'SSO échoué',
        ],
        'terminal' => [
            'badge_unknown' => 'Badge de terminal inconnu',
        ],
        'admin' => [
            'ip_blocked' => 'IP d\'administrateur de plateforme bloquée',
        ],
    ],
];

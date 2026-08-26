<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : safety.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Événements de sécurité',
    ],
    'subtitle' => [
        'index' => 'Enregistrer et suivre les accidents, presqu’accidents, dangers et défauts.',
    ],
    'empty' => 'Aucun événement de sécurité enregistré.',

    'field' => [
        'event_no' => 'N°',
        'kind' => 'Type',
        'severity' => 'Gravité',
        'status' => 'Statut',
        'occurred_at' => 'Survenu le',
        'location' => 'Lieu',
        'affected_person' => 'Personne concernée',
        'reporter' => 'Signalé par',
        'subject' => 'Lié à',
        'description' => 'Description',
        'immediate_action' => 'Mesure immédiate',
        'root_cause' => 'Analyse des causes',
        'closed_at' => 'Clôturé le',
        'closed_by' => 'Clôturé par',
        'followup_title' => 'Titre de la mesure de suivi',
        'followup_description' => 'Description (facultatif)',
    ],

    'section' => [
        'status' => 'Changer le statut',
        'followup' => 'Mesure de suivi',
        'attachments' => 'Pièces jointes',
        'followups' => 'Mesures de suivi',
    ],

    'no_attachments' => 'Aucune pièce jointe.',
    'no_followups' => 'Aucune mesure de suivi pour le moment.',

    'action' => [
        'create' => 'Signaler un événement',
        'edit' => 'Modifier',
        'save' => 'Enregistrer',
        'show' => 'Voir',
        'back' => 'Retour',
        'create_followup' => 'Créer une mesure de suivi',
    ],

    'transition' => [
        'investigating' => 'Lancer l’investigation',
        'measuresDefined' => 'Mesures définies',
        'closed' => 'Clôturer',
    ],

    'hint' => [
        'root_cause_for_close' => 'Une analyse des causes est requise pour clôturer l’événement.',
        'followup' => 'Crée un point ouvert comme reprise lié à cet événement.',
    ],

    'flash' => [
        'created' => 'Événement de sécurité enregistré.',
        'updated' => 'Événement de sécurité mis à jour.',
        'deleted' => 'Événement de sécurité supprimé.',
        'followup_created' => 'Mesure de suivi créée.',
        'status' => [
            'reported' => 'Événement réinitialisé.',
            'investigating' => 'Investigation lancée.',
            'measuresDefined' => 'Mesures définies.',
            'closed' => 'Événement clôturé.',
        ],
    ],

    'error' => [
        'invalid_transition' => 'Changement de statut invalide : :from → :to.',
        'close_requires_root_cause' => 'La clôture nécessite une analyse des causes.',
    ],

    'report' => [
        'title' => 'Analyse de sécurité',
        'nav' => 'Sécurité au travail',
        'subtitle' => 'Événements de sécurité par type et gravité sur la période.',
        'by_kind' => 'Par type',
        'by_severity' => 'Par gravité',
        'kpi' => [
            'total' => 'Total des événements',
            'open' => 'Ouverts',
            'closed' => 'Clôturés',
            'critical' => 'Critiques',
        ],
    ],

    // Registre sécurité au travail (Feature 132) : évaluation des risques, formation, visite médicale.
    'register' => [
        'section' => 'Sécurité au travail',
        'nav' => [
            'assessments' => 'Évaluations des risques',
            'instructions' => 'Formations sécurité',
            'checkups' => 'Visites médicales',
        ],
        'title' => [
            'assessments' => 'Évaluations des risques',
            'instructions' => 'Formations sécurité',
            'checkups' => 'Visites médicales du travail',
        ],
        'subtitle' => [
            'assessments' => 'Évaluations des risques selon § 5 ArbSchG — versionnées, avec date de révision.',
            'instructions' => 'Formations sécurité selon DGUV Vorschrift 1 § 4 avec preuve de participation par personne.',
            'checkups' => 'Visites médicales selon ArbMedVV — uniquement type, date et attestation, aucune donnée de santé.',
        ],
        'field' => [
            'assessment_no' => 'Numéro',
            'version' => 'Version',
            'area' => 'Zone',
            'activity' => 'Activité',
            'description' => 'Description',
            'status' => 'Statut',
            'review_due_on' => 'Révision prévue',
            'approved_by' => 'Approuvé par',
            'approved_at' => 'Approuvé le',
            'created_by' => 'Créé par',
            'supersedes' => 'Remplace',
            'superseded_by' => 'Remplacé par',
            'items' => 'Dangers',
            'position' => 'Pos.',
            'hazard' => 'Danger',
            'measure' => 'Mesure',
            'severity' => 'Gravité (G)',
            'likelihood' => 'Probabilité (P)',
            'risk_before' => 'Risque avant',
            'risk_after' => 'Risque après',
            'before' => 'Avant mesure',
            'after' => 'Après mesure',
            'instruction_no' => 'Numéro',
            'topic' => 'Sujet',
            'held_on' => 'Date',
            'instructor' => 'Formateur·rice',
            'assessment' => 'Évaluation des risques',
            'repeat_interval_months' => 'Répétition (mois)',
            'notes' => 'Remarques',
            'participants' => 'Participants',
            'signed' => 'Confirmé',
            'signed_at' => 'Confirmé le',
            'method' => 'Forme de preuve',
            'next_due_on' => 'Prochaine échéance',
            'user' => 'Personne',
            'kind' => 'Type',
            'occasion' => 'Motif',
            'performed_on' => 'Effectuée le',
            'certificate_on_file' => 'Attestation disponible',
        ],
        'action' => [
            'create_assessment' => 'Créer une évaluation des risques',
            'edit' => 'Modifier',
            'save' => 'Enregistrer',
            'delete' => 'Supprimer',
            'show' => 'Afficher',
            'back' => 'Retour',
            'transition' => 'Changer le statut',
            'new_version' => 'Créer une version suivante',
            'add_item' => 'Ajouter un danger',
            'edit_item' => 'Modifier le danger',
            'create_instruction' => 'Enregistrer une formation',
            'sign' => 'Confirmer la participation',
            'create_checkup' => 'Enregistrer une visite',
        ],
        'filter' => [
            'all' => 'Tous',
            'current_only' => 'Versions actuelles uniquement',
            'open_only' => 'Uniquement avec confirmations ouvertes',
            'due_only' => 'Échues uniquement',
        ],
        'kpi' => [
            'review_due' => 'Révision échue',
            'instruction_due' => 'Répétition échue',
            'checkup_due' => 'Visite échue',
        ],
        'empty' => [
            'assessments' => 'Aucune évaluation des risques pour l’instant.',
            'items' => 'Aucun danger saisi pour l’instant.',
            'instructions' => 'Aucune formation saisie pour l’instant.',
            'participants' => 'Aucun participant.',
            'checkups' => 'Aucune visite saisie pour l’instant.',
        ],
        'hint' => [
            'frozen' => 'Cette version est approuvée et gelée. Les modifications passent par une version suivante.',
            'approve_requires_items' => 'L’approbation exige au moins un danger.',
            'sign_self' => 'Confirmez votre participation — nom, heure et adresse IP sont enregistrés comme preuve.',
            'no_health_data' => 'Aucun résultat ni diagnostic n’est enregistré — uniquement type, date et présence de l’attestation.',
            'after_optional' => 'Risque après mesure facultatif — saisir les deux valeurs ensemble.',
            'pdf_not_in_mvp' => 'La preuve PDF suivra dans une étape ultérieure.',
        ],
        'confirm' => [
            'delete_assessment' => 'Supprimer l’évaluation des risques ?',
            'delete_item' => 'Supprimer le danger ?',
            'delete_instruction' => 'Supprimer la formation ?',
            'delete_checkup' => 'Supprimer l’entrée de visite ?',
            'sign' => 'Confirmer maintenant la participation (définitif) ?',
        ],
        'flash' => [
            'assessment_created' => 'Évaluation des risques créée.',
            'assessment_updated' => 'Évaluation des risques mise à jour.',
            'assessment_transitioned' => 'Statut modifié.',
            'assessment_version_created' => 'Version suivante :version créée.',
            'assessment_deleted' => 'Évaluation des risques supprimée.',
            'item_created' => 'Danger ajouté.',
            'item_updated' => 'Danger mis à jour.',
            'item_deleted' => 'Danger retiré.',
            'instruction_created' => 'Formation enregistrée.',
            'instruction_updated' => 'Formation mise à jour.',
            'instruction_deleted' => 'Formation supprimée.',
            'participation_signed' => 'Participation confirmée.',
            'checkup_created' => 'Visite enregistrée.',
            'checkup_updated' => 'Visite mise à jour.',
            'checkup_deleted' => 'Entrée de visite supprimée.',
        ],
        'error' => [
            'assessment_frozen' => 'Les évaluations approuvées sont gelées — veuillez créer une version suivante.',
            'approve_requires_items' => 'L’approbation exige au moins un danger.',
            'new_version_requires_approved' => 'Une version suivante n’est possible qu’à partir d’une version approuvée.',
            'after_pair_incomplete' => 'Risque après mesure : saisir gravité et probabilité ensemble.',
            'sign_only_self' => 'Seule la personne inscrite peut confirmer sa participation.',
            'already_signed' => 'La participation est déjà confirmée.',
            'delete_with_signatures' => 'Les formations avec preuves confirmées ne peuvent pas être supprimées.',
        ],
        'status_summary' => ':signed sur :total confirmés',
    ],
];

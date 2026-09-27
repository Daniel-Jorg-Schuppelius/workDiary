<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recruiting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Recruiting-Ergänzungen (MVP-924/925).
return [
    'suitability' => [
        'title' => 'Matrice d\'adéquation',
        'subtitle' => 'Compétences requises pour « :title » et évaluation des candidatures — une aide à la sélection, pas une décision automatique.',
        'requirements' => 'Compétences requises',
        'no_requirements' => 'Aucune exigence définie pour l\'instant.',
        'no_competencies' => 'Aucune compétence n\'existe encore (catalogue de la plateforme d\'apprentissage).',
        'competency' => 'Compétence',
        'level' => 'Niveau',
        'add' => 'Ajouter une exigence',
        'remove' => 'Supprimer',
        'confirm_remove' => 'Supprimer cette compétence requise ?',
        'required' => 'Requis :level',
        'matrix' => 'Candidatures',
        'candidate' => 'Candidat',
        'gaps' => 'Écarts',
        'score' => 'Adéquation',
        'no_applications' => 'Aucune candidature pour ce poste.',
        'rating_title' => 'Évaluation des compétences',
        'note' => 'Justification (interne)',
        'save' => 'Enregistrer',
        'flash' => [
            'requirement' => 'Exigence enregistrée.',
            'requirement_removed' => 'Exigence supprimée.',
            'rated' => 'Évaluation enregistrée.',
        ],
    ],
    'offer' => [
        'title' => 'Proposer des créneaux',
        'slot' => 'Créneau :n',
        'mode' => 'Type d\'entretien',
        'duration' => 'Durée (minutes)',
        'valid_days' => 'Valable (jours)',
        'interviewer' => 'Recruteur',
        'send' => 'Envoyer l\'invitation',
        'ics_title' => 'Entretien d\'embauche',
        'public_title' => 'Choisir un créneau d\'entretien',
        'public_intro' => 'Veuillez choisir un créneau (:minutes minutes, :mode).',
        'choose' => 'Créneaux proposés',
        'confirm' => 'Confirmer ce créneau',
        'confirmed_title' => 'Créneau confirmé',
        'confirmed_text' => 'Nous nous réjouissons de l\'entretien du :when avec :org. Une confirmation avec entrée de calendrier est en route.',
        'flash' => [
            'sent' => 'Invitation avec :count créneaux envoyée.',
        ],
        'error' => [
            'no_email' => 'Aucune adresse e-mail valide pour cette candidature.',
            'no_slots' => 'Veuillez indiquer au moins un créneau futur.',
            'unavailable' => 'Ce créneau n\'est plus disponible.',
        ],
        'mail' => [
            'subject' => 'Votre entretien : veuillez choisir un créneau (:title)',
            'body' => "Bonjour :name,\n\nnous souhaitons vous rencontrer. Veuillez choisir l'un des créneaux suivants avant le :until :\n:slots\n\nChoisir un créneau : :url",
            'confirmed_subject' => 'Confirmation de votre entretien',
            'confirmed_body' => "Bonjour :name,\n\nvotre entretien aura lieu le :when (:mode). Le rendez-vous est joint en tant qu'entrée de calendrier.",
        ],
    ],
];

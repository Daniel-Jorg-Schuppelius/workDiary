<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : delegation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vertretung bei Abwesenheit (Feature 202, MVP-1647).
return [
    'title' => 'Remplacements',
    'subtitle' => 'Qui remplace qui pendant une absence. Le remplaçant voit le travail attribué dans la file de travail et reçoit ses notifications — sans obtenir de droits supplémentaires.',
    'create' => 'Créer un remplacement',
    'from_absence' => 'Créer un remplacement',
    'filter' => [
        'state' => 'Période',
        'current' => 'En cours et prévus',
        'past' => 'Terminés et expirés',
        'all' => 'Toute l’organisation',
    ],
    'empty' => [
        'title' => 'Aucun remplacement',
        'message' => 'Les remplacements auxquels vous participez apparaissent ici.',
    ],
    'column' => [
        'represented' => 'Personne remplacée',
        'deputy' => 'Remplaçant',
        'period' => 'Période',
        'state' => 'État',
        'work' => 'Reprend',
    ],
    'open_end' => 'jusqu’à nouvel ordre',
    'state' => [
        'active' => 'En cours',
        'upcoming' => 'Prévu',
        'past' => 'Expiré',
        'ended' => 'Terminé',
    ],
    'ended_by' => 'Terminé par :name le :date',
    'end' => 'Terminer le remplacement',
    'end_confirm' => 'Terminer le remplacement maintenant ? Le travail reviendra alors uniquement à la personne remplacée.',
    'dialog' => [
        'title' => 'Créer un remplacement',
        'submit' => 'Créer un remplacement',
        'no_rights_hint' => 'Le remplaçant voit et traite le travail attribué et reçoit ses notifications. Il n’obtient aucun droit supplémentaire ni accès aux temps, absences ou dossier du personnel.',
        'people' => 'Personnes',
        'deputy_hint' => 'Prérempli avec le suppléant désigné de la personne.',
        'choose_deputy' => 'Veuillez choisir',
        'from' => 'Du',
        'until' => 'Au',
        'until_hint' => 'Sans date de fin, le remplacement s’applique jusqu’à ce que quelqu’un y mette fin.',
        'kind' => 'Motif',
        'note' => 'Note',
        'work_heading' => 'Ce que le remplaçant reprend de :name',
        'work_none' => 'Actuellement aucun travail attribué en cours',
        'work_hint' => 'La personne remplacée reste responsable ; rien n’est à réattribuer ensuite.',
    ],
    'banner' => [
        'represents' => 'Vous remplacez :names.',
        'represented_by' => 'Remplacé par :names.',
        'person_until' => ':name (jusqu’au :date)',
        'work_queue' => 'Vers la file de travail',
        'manage' => 'Remplacements',
    ],
    'notification' => [
        'on_behalf_of' => 'En remplacement de :name :',
        'created' => 'Vous remplacez :name du :from au :until.',
        'created_open' => 'Vous remplacez :name à partir du :from jusqu’à nouvel ordre.',
    ],
    'flash' => [
        'created' => 'Remplacement par :name créé.',
        'ended' => 'Remplacement terminé.',
    ],
    'error' => [
        'past_start' => 'Un remplacement commence au plus tôt aujourd’hui.',
        'end_before_start' => 'La fin est antérieure au début.',
        'absence_mismatch' => 'L’absence n’appartient pas à cette personne ou n’est pas approuvée.',
        'overlap' => ':name a déjà un remplacement sur cette période.',
        'deputy_absent' => ':name est lui-même remplacé sur cette période et ne peut pas remplacer.',
        'represented_is_deputy' => ':name remplace déjà quelqu’un sur cette période ; il n’y a pas de remplacements en chaîne.',
        'already_ended' => 'Ce remplacement est déjà terminé.',
        'foreign_organization' => 'Les remplacements ne sont possibles qu’au sein de votre propre organisation.',
        'self' => 'Une personne ne peut pas se remplacer elle-même.',
        'deactivated' => 'Les comptes désactivés ne peuvent ni remplacer ni être remplacés.',
    ],
    // Transfert définitif sans départ et sélection multiple dans la liste des commandes (MVP-1648).
    'handover' => [
        'action' => 'Transférer les responsabilités',
        // Personenwahl auf der Seite „Vertretungen“ (Einstieg ohne Mitgliederliste).
        'choose_title' => 'De qui voulez-vous transférer les responsabilités ?',
        'choose_hint' => 'Choisissez la personne dont le travail passe définitivement à des successeurs. Vous choisirez ensuite le successeur pour chaque domaine.',
        'choose_empty' => 'Il n\'y a aucune personne dont vous pouvez transférer le travail.',
        'title' => 'Transférer les responsabilités de :name',
        'intro' => 'Transfert définitif sans départ : les domaines choisis passent durablement au successeur ; la personne reste membre. Pour une absence temporaire, un remplacement est la bonne solution.',
        'areas' => 'Domaines et successeur',
        'hint' => 'Un successeur par domaine, ou aucun. Seul ce qui est ouvert et attribué à la personne au moment de l’enregistrement est transféré ; le nombre par domaine est consigné.',
        'submit' => 'Transférer définitivement',
        'empty' => 'Rien de transférable n’est actuellement attribué à cette personne.',
        'single_records' => 'Les commandes individuelles se transfèrent dans la liste des commandes via la sélection « Changer de responsable ».',
        'help' => 'Aide',
        'flash' => '{0} Rien n’a été transféré.|{1} Un élément de :name transféré.|[2,*] :count éléments de :name transférés.',
        'area' => [
            'assigned_orders' => '{1} Une commande attribuée|[2,*] :count commandes attribuées',
            'assigned_tasks' => '{1} Une tâche ouverte|[2,*] :count tâches ouvertes',
            'assigned_service_tickets' => '{1} Un ticket de service attribué|[2,*] :count tickets de service attribués',
            'assigned_procedure_runs' => '{1} Une exécution de procédure en cours|[2,*] :count exécutions de procédure en cours',
        ],
        'error' => [
            'inactive' => 'Seul le travail des membres actifs peut être transféré ; pour un départ, utilisez la boîte de dialogue de départ.',
            'successor' => 'Le successeur doit être un autre membre actif de la même organisation.',
            'none' => 'Veuillez choisir un successeur pour au moins un domaine.',
        ],
    ],
    'reassign' => [
        'selected' => ':n commandes sélectionnées',
        'choose' => 'Choisir le nouveau responsable…',
        'submit' => 'Changer de responsable',
        'select_entry' => 'Sélectionner la commande « :title »',
        'unknown_person' => 'Cette personne n’existe pas dans votre organisation ou est désactivée.',
        'done' => '{0} Aucun responsable modifié.|{1} Une commande est désormais chez :name.|[2,*] :count commandes sont désormais chez :name.',
        'skipped' => '{1} Une commande a été ignorée (pas de droit de modification, déjà responsable ou qualification manquante).|[2,*] :count commandes ont été ignorées (pas de droit de modification, déjà responsable ou qualification manquante).',
    ],
    'demo' => [
        'note' => 'Exemple : remplacement pendant une formation.',
    ],
];

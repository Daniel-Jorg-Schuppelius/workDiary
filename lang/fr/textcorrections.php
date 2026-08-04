<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : textcorrections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Dictionnaire',
        'subtitle' => 'Corrections orthographiques (faux → correct) appliquées automatiquement aux textes de position générés — les saisies de temps enregistrées restent inchangées.',
    ],

    'notice' => 'Les entrées s\'appliquent automatiquement lors de la construction des textes de position des transferts et brouillons de facture (mot entier, la casse est reprise). Les textes originaux des saisies de temps ne sont jamais modifiés.',
    'search_placeholder' => 'Rechercher (faux/correct) …',
    'legend' => 'Entrée du dictionnaire',
    'empty' => 'Aucune entrée de dictionnaire',
    'delete_confirm' => 'Supprimer cette entrée du dictionnaire ? La correction ne sera plus appliquée.',
    'wrong_placeholder' => 'p. ex. maintenence',
    'wrong_help' => 'Mot ou expression mal orthographié — correspondance en mot entier uniquement, sans tenir compte de la casse.',
    'correct_placeholder' => 'p. ex. maintenance',
    'correct_help' => 'Orthographe correcte — elle remplace la faute dans tous les textes de position générés.',

    'field' => [
        'wrong' => 'Faux',
        'correct' => 'Correct',
        'origin' => 'Origine',
        'origin_manual' => 'Manuel',
        'origin_learned' => 'Appris',
        'usage' => 'Utilisé',
        'active' => 'Actif',
        'enabled_yes' => 'Oui',
        'enabled_no' => 'Non',
    ],

    'action' => [
        'new' => 'Créer une entrée',
        'edit' => 'Modifier l\'entrée',
        'submit' => 'Enregistrer',
        'activate' => 'Activer',
        'deactivate' => 'Désactiver',
        'delete' => 'Supprimer',
    ],

    'flash' => [
        'saved' => 'Entrée du dictionnaire créée.',
        'updated' => 'Entrée du dictionnaire mise à jour.',
        'deleted' => 'Entrée du dictionnaire supprimée.',
        'activated' => 'Entrée du dictionnaire activée.',
        'deactivated' => 'Entrée du dictionnaire désactivée.',
        'learned' => 'Correction ajoutée au dictionnaire.',
        'duplicate_updated' => 'L\'entrée existait déjà et a été mise à jour.',
        'invalid' => 'Faux et correct ne doivent pas être identiques.',
    ],

    'validation' => [
        'duplicate' => 'Une entrée existe déjà pour cette faute.',
    ],

    'learn' => [
        'title' => 'Mémoriser la correction ?',
        'question' => 'Des corrections de mots ont été détectées dans votre modification. Les ajouter au dictionnaire pour qu\'elles soient appliquées automatiquement à l\'avenir ?',
        'confirm' => 'Mémoriser',
        'dismiss' => 'Ne pas mémoriser',
    ],
];

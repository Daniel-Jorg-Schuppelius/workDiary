<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : takeoff.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Aufmaßblatt (MVP-1058).
return [
    'title' => 'Métré',
    'back' => 'Retour',
    'default_title' => 'Métré :carrier',
    'lines' => 'Lignes de métré',
    'totals' => 'Quantités par poste',
    'photos' => 'Photos et croquis',
    'values' => 'Valeurs',
    'empty' => 'Aucune ligne — ajoutez une formule via « Ajouter une ligne ».',
    'no_target' => '— sans affectation —',
    'pdf_note' => 'Calculé avec les formules de la REB-VB 23.003. Valeurs en mètres, angles en grades (cercle complet = 400).',
    'formula' => [
        'Sum' => 'Unités / somme',
        'Triangle' => 'Triangle',
        'Rectangle' => 'Rectangle / parallélépipède',
        'Trapezoid' => 'Trapèze',
        'Circle' => 'Cercle / secteur',
        'Mean' => 'Moyenne',
        'Free' => 'Formule libre',
    ],
    'value' => [
        'amount' => 'Valeur',
        'base' => 'Base',
        'height' => 'Hauteur',
        'depth' => 'Profondeur / hauteur (volume, facultatif)',
        'length' => 'Longueur',
        'width' => 'Largeur',
        'side_a' => 'Côté a',
        'side_c' => 'Côté c',
        'radius' => 'Rayon',
        'angle' => 'Angle en grades (400 = cercle complet)',
        'expression' => 'Expression',
    ],
    'hint' => [
        'Sum' => 'Les valeurs s’additionnent ; une valeur négative soustrait.',
        'Triangle' => 'Base × hauteur ÷ 2 ; avec profondeur en volume.',
        'Rectangle' => 'Longueur × largeur ; avec profondeur/hauteur en volume.',
        'Trapezoid' => '(a + c) ÷ 2 × hauteur ; avec profondeur en volume.',
        'Circle' => 'Rayon² × π × angle ÷ 400 ; 400 grades = cercle complet.',
        'Mean' => 'Moyenne arithmétique des valeurs.',
        'Free' => 'Expression avec + − × ÷ et parenthèses, virgule ou point décimal.',
        'factor' => 'Nombre de parties identiques ; négatif soustrait (p. ex. −1 pour une porte).',
        'label' => 'Pièce, élément ou axe.',
        'unit' => 'Vide = unité du poste ou de l’article.',
    ],
    'col' => [
        'label' => 'Pièce / élément',
        'formula' => 'Formule',
        'values' => 'Valeurs',
        'factor' => 'Facteur',
        'quantity' => 'Quantité',
        'target' => 'Poste',
    ],
    'field' => [
        'title' => 'Désignation',
        'measured_on' => 'Mesuré le',
        'note' => 'Remarque',
        'boq_item' => 'Poste du bordereau',
        'article' => 'Article / prestation',
        'description' => 'Description (sans article)',
        'unit' => 'Unité',
    ],
    'action' => [
        'create' => 'Nouveau métré',
        'edit' => 'Modifier',
        'pdf' => 'PDF',
        'delete' => 'Supprimer',
        'add_line' => 'Ajouter une ligne',
    ],
    'transition' => [
        'completed' => 'Clôturer',
        'draft' => 'Rouvrir',
    ],
    'confirm' => [
        'completed' => 'Clôturer le métré ? Les lignes sont alors verrouillées et les quantités reportables.',
        'draft' => 'Rouvrir le métré ? Les quantités déjà reportées ne changent pas.',
        'delete' => 'Supprimer le métré avec toutes ses lignes ?',
        'delete_line' => 'Supprimer vraiment cette ligne ?',
    ],
    'flash' => [
        'created' => 'Métré créé.',
        'saved' => 'Métré enregistré.',
        'deleted' => 'Métré supprimé.',
        'status' => 'Statut modifié.',
        'line_saved' => 'Ligne enregistrée.',
        'line_deleted' => 'Ligne supprimée.',
    ],
    'error' => [
        'locked' => 'Le métré est clôturé et ne peut plus être modifié.',
        'not_computable' => 'La formule ne peut pas être calculée avec ces valeurs — vérifiez les valeurs obligatoires ou l’expression.',
        'not_found' => 'Métré introuvable.',
    ],
    'carrier' => [
        'section' => 'Métrés',
        'lines' => ':count ligne|:count lignes',
        'none' => 'Aucun métré pour l’instant.',
    ],
    'transfer' => [
        'title' => 'Reporter les quantités',
        'action' => 'Reporter',
        'confirm' => [
            'quote' => 'Reporter les quantités dans un nouveau devis ?',
            'invoice' => 'Reporter les quantités dans un brouillon de facture ? Le PDF du métré est joint comme document.',
            'progress' => 'Déclarer les quantités comme avancement des postes du bordereau ?',
        ],
        'targets' => 'Reporté vers',
        'kind' => [
            'quote' => 'En devis',
            'invoice' => 'En brouillon de facture',
            'progress' => 'En avancement du bordereau',
        ],
        'hint' => 'Chaque type une fois par métré ; les lignes sans prix reçoivent 0 € et sont complétées dans le document.',
        'done' => 'Reporté',
        'based_on' => 'Quantités selon le métré « :title ».',
        'document_title' => 'Métré :title',
        'progress_note' => 'Du métré « :title »',
        'flash' => [
            'quote' => 'Devis créé à partir du métré.',
            'invoice' => 'Brouillon de facture créé à partir du métré ; le PDF du métré est joint comme document.',
            'progress' => ':count postes du bordereau déclarés.',
        ],
        'error' => [
            'not_completed' => 'Clôturez d’abord le métré.',
            'already' => 'Déjà reporté : :kind.',
            'no_customer' => 'Aucun client n’est lié à la commande ou au projet.',
            'empty' => 'Le métré ne contient aucune quantité.',
            'no_boq' => 'Aucune ligne n’est affectée à un poste du bordereau.',
        ],
    ],
    'chain' => [
        'label' => 'Métrés sans document',
        'measured_on' => 'Mesuré le :date',
    ],
    'presets' => [
        'label' => 'Modèles',
    ],
    'quick' => [
        'title' => 'Saisie rapide',
        'hint' => 'Fonctionne aussi hors ligne — la ligne est transmise à la prochaine connexion.',
        'photo' => 'Photo',
        'add' => 'Saisir la ligne',
    ],
];

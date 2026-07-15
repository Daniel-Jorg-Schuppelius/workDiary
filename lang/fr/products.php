<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : products.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Produktstamm (Typ-Ebene Hersteller-Modell, MVP-370).
return [
    'title' => [
        'index' => 'Produits',
        'subtitle' => 'Niveau type fabricant + modèle : regroupe les articles et les actifs d\'un même produit.',
        'create' => 'Créer un produit',
        'edit' => 'Modifier le produit',
        'empty' => 'Aucun produit pour le moment.',
        'empty_search' => 'Aucun produit trouvé pour « :q ».',
    ],
    'field' => [
        'basics' => 'Données de base',
        'manufacturer' => 'Fabricant',
        'model' => 'Modèle',
        'name' => 'Nom d\'affichage',
        'name_placeholder' => 'Fabricant modèle',
        'name_help' => 'Laisser vide pour « fabricant modèle ».',
        'product_group' => 'Groupe de produits',
        'no_group' => '— aucun —',
        'articles' => 'Articles',
        'assets' => 'Actifs',
        'status' => 'Statut',
        'notes' => 'Notes',
        'product' => 'Produit',
        'no_product' => '— aucun produit —',
        'product_help' => 'Affectation de type (fabricant modèle) ; préremplit fabricant/modèle.',
    ],
    'action' => [
        'create' => 'Créer un produit',
        'save' => 'Enregistrer',
        'edit' => 'Modifier',
        'delete' => 'Supprimer',
        'delete_confirm' => 'Supprimer vraiment ce produit ? Les articles et actifs sont conservés et perdent seulement leur affectation de type.',
    ],
    'flash' => [
        'created' => 'Produit créé.',
        'updated' => 'Produit mis à jour.',
        'deleted' => 'Produit supprimé.',
    ],
];

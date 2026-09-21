<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer-material.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'panel_title' => 'Coûts matériels et bénéfice',
    'add_title' => 'Affecter des coûts matériels',
    'source' => 'Source des coûts',
    'source_hint' => 'Sélectionnez une pièce d\'achat Lexoffice ou saisissez un montant libre.',
    'voucher' => 'Pièce d\'achat',
    'voucher_hint' => 'Facultatif — un montant partiel est possible ; une pièce peut être répartie entre plusieurs clients.',
    'manual_amount' => '— Montant libre —',
    'description' => 'Description',
    'description_hint' => 'Obligatoire sans pièce — désigne les coûts matériels.',
    'allocation' => 'Affectation',
    'amount' => 'Montant',
    'amount_hint' => 'Montant (partiel) affecté au client.',
    'date' => 'Date',
    'project' => 'Projet',
    'project_hint' => 'Facultatif — pour une affectation plus fine.',
    'no_project' => '— Aucun projet —',
    'source_lexoffice' => 'Pièce Lexoffice',
    'revenue' => 'Chiffre d\'affaires (facturé)',
    'material_cost' => 'Coûts matériels',
    'profit' => 'Bénéfice (calc.)',
    'margin' => 'marge',
    'range_hint' => 'Valeurs de la période sélectionnée (:range).',
    'double_count_hint' => 'Vue de gestion (hors frais généraux). Affectez le matériel soit via une pièce d\'achat, SOIT via une sortie de stock — pas les deux pour les mêmes marchandises.',
    'empty_hint' => 'Aucun coût matériel affecté pour l\'instant. Utilisez « Affecter des coûts matériels » pour attribuer des pièces ou des montants libres au client et faire apparaître le bénéfice.',
    'confirm_delete' => 'Voulez-vous vraiment supprimer cette affectation de coûts matériels ?',
    'delete' => 'Supprimer',
    'flash_saved' => 'Coûts matériels affectés.',
    'flash_deleted' => 'Affectation de coûts matériels supprimée.',
    'error_description_required' => 'Veuillez saisir une description si aucune pièce n\'est sélectionnée.',
    'error_voucher_not_purchase' => 'La pièce sélectionnée n\'est pas une pièce d\'achat.',
    'error_amount_over_voucher' => 'Le montant dépasse le total de la pièce.',
    'error_project_foreign' => 'Le projet n\'appartient pas à ce client.',
    'stock_title' => 'Sortir du stock',
    'stock_issue' => 'Sortir et comptabiliser',
    'stock_source' => 'Sortie de stock',
    'stock_hint' => 'Valorisation au coût moyen pondéré ; la sortie réduit le stock et est comptabilisée en coûts matériels.',
    'article' => 'Article',
    'warehouse' => 'Entrepôt',
    'qty' => 'Quantité',
    'qty_hint' => 'En unité de base.',
    'choose' => '— Veuillez choisir —',
    'source_stock' => 'Stock',
    'stock_item' => 'Article en stock',
    'flash_stock_issued' => 'Sortie de stock comptabilisée et affectée en coûts matériels.',
    'book_to_customer' => 'Client des coûts matériels',
    'no_customer' => '— Aucun client —',
];

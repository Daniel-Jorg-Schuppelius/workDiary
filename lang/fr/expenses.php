<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : expenses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Frais',
        'create' => 'Enregistrer un frais',
        'edit' => 'Modifier le frais',
        'inbox' => 'Approbation des frais',
        'category_index' => 'Catégories de frais',
        'category_create' => 'Créer une catégorie de frais',
        'category_edit' => 'Modifier la catégorie de frais',
    ],
    'intro' => [
        'category' => 'Les catégories de frais regroupent les justificatifs (p. ex. repas, hébergement, réception) et définissent des valeurs par défaut comme le taux de TVA, l\'obligation de joindre un justificatif et si le frais est par défaut refacturable au client. L\'icône et la couleur définissent l\'apparence dans les listes et les rapports.',
    ],
    'field' => [
        'label' => 'Libellé',
        'slug' => 'Slug',
        'icon' => 'Icône (material symbol)',
        'color' => 'Couleur',
        'description' => 'Description',
        'sort' => 'Ordre',
        'is_active' => 'Actif',
        'default_tax_rate' => 'Taux de TVA (par défaut, %)',
        'requires_receipt' => 'Justificatif obligatoire',
        'default_billable' => 'Refacturable au client par défaut',
        'date' => 'Date du justificatif',
        'category' => 'Catégorie',
        'vendor' => 'Fournisseur',
        'amount_gross' => 'Montant brut',
        'amount_net' => 'Montant net',
        'tax_rate' => 'Taux de TVA (%)',
        'tax_amount' => 'Montant de TVA',
        'currency' => 'Devise',
        'payment_method' => 'Mode de paiement',
        'project' => 'Projet',
        'customer' => 'Client',
        'task' => 'Tâche',
        'billable' => 'Refacturable au client',
        'notes' => 'Notes',
        'status' => 'Statut',
        'attachments' => 'Justificatifs',
        'reimbursement_reference' => 'Référence de remboursement',
        'reject_reason' => 'Motif de rejet',
        'decided_at' => 'Décidé le',
        'reimbursed_at' => 'Remboursé le',
    ],
    'action' => [
        'create_category' => 'Créer une catégorie',
        'create' => 'Enregistrer un frais',
        'submit' => 'Soumettre pour approbation',
        'approve' => 'Approuver',
        'reject' => 'Rejeter',
        'cancel' => 'Annuler',
        'reimburse' => 'Marquer comme remboursé',
        'export' => 'Exporter en CSV',
    ],
    'help' => [
        'color' => 'Définit la couleur d\'accent pour l\'icône, le badge et les surbrillances dans les listes.',
        'gross_first' => 'Saisissez le montant brut du justificatif. Le montant net et la TVA sont calculés automatiquement.',
        'requires_receipt' => 'Si actif, au moins un justificatif (photo/PDF) est requis lors de la saisie.',
    ],
    'empty' => [
        'categories' => 'Aucune catégorie de frais pour le moment.',
        'expenses' => 'Aucun frais enregistré pour le moment.',
    ],
    'confirm' => [
        'delete_category' => 'Vraiment supprimer cette catégorie de frais ?',
        'delete_expense' => 'Vraiment supprimer ce frais ?',
    ],
];

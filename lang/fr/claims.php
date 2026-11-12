<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : claims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'pattern' => [
        'title' => 'Schémas remarquables (défauts en série, lots)',
        'hint' => 'Groupes d’au moins :threshold réclamations dans la période. Une indication, pas une décision.',
        'none' => 'Aucun schéma remarquable dans la période.',
        'rule_label' => 'Règle',
        'group' => 'Groupe',
        'cases' => 'Dossiers',
        'count' => 'Nombre',
        'rule' => [
            'lot' => 'Lot',
            'article_defect' => 'Article × type de défaut',
            'article_cause' => 'Article × cause',
            'supplier_defect' => 'Fournisseur × type de défaut',
            'entry_type_cause' => 'Type de commande × cause',
        ],
        'notify_title' => 'Schéma de réclamations remarquable : :label',
        'notify_message' => ':count réclamations en :days jours (:rule).',
    ],
    // Lagerbezug der Rückläufer (MVP-1576).
    'rma' => [
        'group_return' => 'Retour',
        'group_receipt' => 'Réception',
        'group_stock' => 'Référence de stock',
        'hint_stock' => 'Avec l’entrepôt, la variante et la quantité, la réception comptabilise le stock en quarantaine. Sans variante, le retour est seulement documenté.',
        'hint_warehouse' => 'Nécessaire pour l’écriture de stock dès qu’une variante est choisie.',
        'hint_serial' => 'La sélection fixe la variante et la quantité 1.',
        'option' => ['no_stock' => '— sans article de stock —'],
        'without_stock' => 'sans référence de stock — aucune écriture de stock',
        'no_stock_module' => 'Le module de stock n’est pas actif. Le retour est enregistré sans écriture de stock.',
        'disposition_books' => 'La décision transfère le stock hors de la quarantaine ou le sort du stock.',
        'disposition_documents' => 'Ce retour n’a pas de référence de stock. La décision est enregistrée sans écriture de stock.',
        'flash' => [
            'received_booked' => 'Réception comptabilisée en quarantaine (:state).',
            'received_unbooked' => 'Réception enregistrée sans écriture de stock (pas d’article de stock ou module de stock inactif).',
            'disposition_booked' => 'Décision d’affectation comptabilisée.',
            'disposition_unbooked' => 'Décision d’affectation enregistrée sans écriture de stock.',
        ],
        'error' => [
            'serial_variant' => 'Le numéro de série appartient à une autre variante d’article.',
            'serial_qty' => 'Un retour avec numéro de série a la quantité 1.',
            'lot_variant' => 'Le lot appartient à une autre variante d’article.',
        ],
    ],
    // Bezugsobjekte, Einstieg, Finanzfolge und Regress der Akte (MVP-1577).
    'form' => [
        'title_edit' => 'Modifier la réclamation',
        'lot_article_mismatch' => 'Le lot appartient à un autre article.',
        'group_links' => 'Objets concernés',
        'hint_links' => 'Reliez ce à quoi la réclamation se rapporte. Lorsqu’un client est choisi, les listes n’affichent que ses objets.',
        'hint_serial' => 'Un numéro de série connu en stock est rattaché au dossier.',
        'serial_linked' => 'Le numéro de série est connu en stock et rattaché.',
    ],
    'open' => [
        'action' => 'Ouvrir une réclamation',
    ],
    'financial' => [
        'hint_invoice_local' => 'L’avoir, l’annulation, la réduction de prix et la correction sont créés à partir de cette facture. L’annulation suppose une facture émise et impayée, l’avoir une facture payée.',
        'hint_invoice_external' => 'Les factures de ce client sont gérées dans un système externe. Le document correctif y est créé ; saisissez ensuite son numéro ici.',
        'source_invoice' => 'pour la facture :number',
        'error' => [
            'invoice_required' => 'Choisissez la facture à partir de laquelle le document correctif doit être créé.',
            'not_cancellable' => 'La facture :number ne peut pas être annulée. L’annulation suppose une facture émise et impayée.',
            'not_creditable' => 'Aucun avoir ne peut être créé pour la facture :number. Elle doit être payée et ne pas encore avoir de correction.',
            'not_rejectable' => 'Seules les suites proposées peuvent être refusées.',
        ],
        'reject' => 'Refuser',
        'reject_confirm' => 'Refuser la proposition ? Elle se termine sans pièce ; une nouvelle proposition reste possible.',
        'flash' => [
            'rejected' => 'Proposition refusée.',
        ],
    ],
    'recourse' => [
        'group_claim' => 'Demande',
        'group_reference' => 'Référence',
        'hint_due_days' => 'Sans indication, :days jours à compter de la soumission s’appliquent.',
        'hint_terms' => 'Par exemple durée de garantie, exclusions ou délai de réclamation selon le contrat de livraison.',
        'field_incoming_invoice' => 'Facture fournisseur',
        'due_days' => 'Délai de réponse : :days jours à compter de la soumission',
    ],
    // Retourenlabel einer RMA (MVP-917).
    'return_label' => [
        'title' => 'Étiquette de retour',
        'create' => 'Créer l\'étiquette de retour',
        'download' => 'Télécharger l\'étiquette',
        'created' => 'Étiquette de retour créée (envoi :tracking).',
        'no_address' => 'L\'étiquette de retour nécessite l\'adresse du client (rue, code postal, ville).',
    ],
    // Retourenanmeldung im Kundenportal (MVP-935).
    'portal_return' => [
        'list' => [
            'claim' => 'Réclamation',
            'empty' => 'Aucun retour déclaré pour l’instant.',
            'rma' => 'Numéro de retour',
            'status' => 'Statut',
            'title' => 'Mes retours',
        ],
        'capability' => 'Déclarer un retour',
        'nav' => 'Déclarer un retour',
        'title' => 'Déclarer un retour',
        'intro' => 'Choisissez la livraison ou l\'objet, décrivez le motif et joignez des photos si nécessaire. Vous recevrez un numéro de retour ; nous fournissons une étiquette de retour si besoin.',
        'empty' => 'Aucune livraison ni aucun objet pour votre compte.',
        'submit' => 'Déclarer le retour',
        'label' => 'Télécharger l\'étiquette de retour',
        'field' => [
            'delivery' => 'Livraison',
            'asset' => 'Objet',
            'serial_no' => 'Numéro de série',
            'quantity' => 'Quantité',
            'title' => 'Description courte',
            'description' => 'Motif du retour',
            'photos' => 'Photos ou justificatifs (5 au maximum)',
        ],
        'flash' => [
            'submitted' => 'Retour déclaré : réclamation :number, numéro de retour :rma.',
        ],
        'error' => [
            'subject' => 'Veuillez choisir une livraison ou un objet.',
            'serial' => 'Ce numéro de série n\'appartient pas à la livraison choisie.',
        ],
    ],
    // Nachreichungen aus dem Kundenportal.
    'portal_note' => [
        'history' => 'Vos envois complémentaires',
        'notification_title' => 'Envoi complémentaire pour la réclamation :number',
    ],
    'action' => [
        'follow_up_order' => 'Commande de suivi',
        'follow_up_none' => '— aucune commande de suivi —',
    ],
];

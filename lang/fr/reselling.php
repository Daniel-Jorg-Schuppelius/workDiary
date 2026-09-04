<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : reselling.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Lizenz-Reselling-Abgleich (Feature 151, MVP-757).
return [
    'title' => [
        'menu' => 'Rapprochement licences',
        'index' => 'Rapprochement de revente de licences',
        'show' => 'Exécution du rapprochement',
    ],
    'subtitle' => 'Comparer les abonnements marketplace (Telekom, Quality Hosting) avec les factures sortantes Lexoffice : périodes manquantes, partielles ou facturées sous le prix d’achat, plus un contrôle des prix contre la liste de prix revendeur.',
    'action' => [
        'new' => 'Nouvelle exécution',
        'download' => 'CSV',
        'delete' => 'Supprimer',
        'refresh' => 'Actualiser',
        'assign' => 'Affecter',
        'rerun' => 'Recalculer',
        'remove_mapping' => 'Supprimer l’affectation',
        'back' => 'Retour à la vue d’ensemble',
    ],
    'dialog' => [
        'title' => 'Démarrer une nouvelle exécution',
        'hint' => 'Au moins un fichier d’export est requis. L’exécution lit Lexoffice en arrière-plan ; avec de nombreux clients, cela prend quelques minutes.',
        'telekom' => 'Telekom Cloud Marketplace : purchases.csv',
        'qualityhosting' => 'Quality Hosting : export des contrats (.xlsx)',
        'pricelist' => 'Quality Hosting : liste de prix (.xlsx, facultatif)',
        'map' => 'Fichier d’affectation (facultatif)',
        'map_hint' => 'Une ligne par société : « Société;UUID du contact Lexoffice » ou « Société;customer:<Sqid> ». Pour tout ce que l’exécution ne peut pas affecter sans ambiguïté.',
        'reference' => 'Date de référence',
        'reference_hint' => 'Les périodes commencées au plus tard ce jour comptent comme dues. Pas de limite vers le passé : tout est vérifié depuis le premier début de contrat des exports.',
        'before' => 'Jours avant le début de période (prépaiement)',
        'after' => 'Jours après le début de période (facturation tardive)',
        'window_hint' => 'Une facture appartient à une période si sa date se situe dans cette fenêtre autour du début de période. Laissez la partie arrière large (deux ans par défaut) : facturations tardives et blocs pluriannuels sont répartis en licence-mois, rien ne compte deux fois.',
        'strict' => 'Contrôle strict du produit',
        'strict_hint' => 'Ne compter que les lignes de facture dont le texte nomme l’édition. Sans coche, toute ligne Microsoft du contact dans la fenêtre compte si aucune édition correspondante n’est trouvée (factures groupées).',
        'submit' => 'Démarrer',
    ],
    'field' => [
        'created' => 'Démarrée', 'status' => 'Statut', 'sources' => 'Sources', 'reference' => 'Date de référence',
        'periods' => 'Périodes', 'problems' => 'Signalées', 'open_fee' => 'Frais d’achat ouverts', 'unmapped' => 'Sans affectation',
        'window' => 'Fenêtre', 'files' => 'Fichiers', 'by' => 'Par', 'error' => 'Erreur', 'price_flags' => 'Alertes prix',
        'company' => 'Société', 'customer' => 'Client', 'contact' => 'Contact Lexoffice', 'mapping' => 'Affectation', 'candidates' => 'Candidats',
        'source' => 'Source', 'edition' => 'Édition', 'period' => 'Période', 'quantity' => 'Quantité', 'purchase' => 'Achat',
        'vouchers' => 'Facture(s)', 'unit_net' => 'Net par unité', 'note' => 'Remarque', 'succession' => 'Succession',
        'voucher' => 'Facture', 'date' => 'Date', 'position' => 'Ligne', 'remaining' => 'Restant',
        'product' => 'Produit', 'term' => 'Durée', 'running' => 'Unités en cours', 'contract_price' => 'Achat (contrat)', 'list_price' => 'Achat (liste)',
        'uvp' => 'Prix conseillé', 'sales' => 'Vente (médiane, nombre)', 'sales_range' => 'Vente min – max', 'margin' => 'Marge vs liste',
        'telekom_from' => 'Telekom à partir de', 'telekom_to' => 'Telekom jusqu’au', 'successor' => 'Contrat QH', 'successor_from' => 'QH à partir de',
        'billed_via' => 'Facturé via un partenaire (client tiers)',
        'stored_mapping' => 'Affectation enregistrée',
        'used' => 'Utilisé', 'recognized' => 'Reconnu comme',
        'article_price' => 'Prix article (an)',
        'valid_from' => 'Liste de prix valable à partir du',
    ],
    'status' => [
        'queued' => 'En attente',
        'running' => 'En cours',
        'done' => 'Terminée',
        'failed' => 'Échouée',
    ],
    'section' => [
        'lines' => 'Lignes de facture trouvées pour les contacts affectés',
        'summary' => 'Résumé', 'price' => 'Contrôle des prix', 'findings' => 'Périodes', 'mappings' => 'Affectation société marketplace → contact Lexoffice',
        'extras' => 'Lignes Microsoft sans période due', 'successions' => 'Successions Telekom → Quality Hosting', 'issues' => 'Remarques issues des fichiers', 'errors' => 'Erreurs de lecture', 'files' => 'Fichiers et options',
    ],
    'filter' => [
        'status' => 'Statut', 'problems' => 'Signalées seulement', 'all' => 'Toutes', 'company' => 'Société', 'all_companies' => 'Toutes les sociétés',
    ],
    'empty' => [
        'lines' => 'Aucune ligne de facture trouvée.',
        'runs' => 'Aucune exécution. Téléversez les fichiers d’export pour lancer le premier rapprochement.', 'findings' => 'Aucune période dans cette sélection.', 'price' => 'Aucun contrat en cours ou aucune liste de prix téléversée.', 'mappings' => 'Aucune société.', 'extras' => 'Aucune ligne supplémentaire.', 'successions' => 'Aucune succession détectée.',
    ],
    'price_flag' => [
        'below_list' => 'sous le prix d’achat', 'below_uvp' => 'sous le prix conseillé', 'contract_above_list' => 'contrat plus cher que la liste', 'no_sales' => 'aucune donnée de facture', 'no_list' => 'absent de la liste de prix',
    ],
    'flash' => [
        'mapping_saved' => 'Affectation enregistrée. « Recalculer » l’applique au rapport.', 'mapping_removed' => 'Affectation supprimée.', 'rerun' => 'L’exécution est recalculée.',
        'created' => 'Exécution démarrée. Le rapport apparaîtra ici une fois Lexoffice lu.', 'deleted' => 'Exécution supprimée.', 'not_done' => 'L’exécution n’est pas encore terminée.',
    ],
    'validation' => [
        'customer_required' => 'Veuillez choisir un client.', 'contact_required' => 'Veuillez indiquer l’UUID d’un contact Lexoffice.',
        'need_file' => 'Au moins un fichier d’export (Telekom ou Quality Hosting) est requis.',
    ],
    'hint' => [
        'lines' => 'Diagnostic : tout ce que le rapprochement a vu dans Lexoffice pour les contacts affectés sur la période, avec la quantité utilisée. Une société sans lignes ici n’a aucune facture pour son contact sur la période.',
        'lines_hidden' => ':count positions sans rapport avec les licences (prestations propres, matériel, domaines) sont masquées.',
        'run_pending' => 'L’exécution n’est pas encore terminée. Actualisez la page pour voir le rapport.', 'run_failed' => 'L’exécution a échoué.', 'unmapped' => 'Les sociétés sans affectation peuvent être résolues avec un fichier d’affectation lors de la prochaine exécution.', 'extras' => 'Facturé sans abonnement en cours, ou édition non reconnue par le rapprochement.',
        'mapping' => 'Avec « Affecter », vous définissez par société qui reçoit la facture : la société elle-même, un partenaire ou un contact Lexoffice. Les affectations enregistrées priment sur la détection automatique.',
        'foreign' => 'Les clients finaux d’un partenaire (clients tiers) sont vérifiés via le partenaire : la facture va au partenaire, qui la répercute. Créez les clients tiers sous le client partenaire, ou ajoutez « Société;partner:<nom ou Sqid> » au fichier d’affectation.',
        'succession' => 'La durée Telekom a été coupée au début du contrat Quality Hosting ; sinon chaque migration compterait deux fois.', 'price' => 'Les prix de vente proviennent des lignes de facture affectées ; le prix d’achat de liste et le prix conseillé de la liste de prix pour la même durée et le même rythme. Le prix article est votre prix de vente actuel issu du catalogue d’articles Lexoffice, ramené à la durée ; sans données de facture, il sert de référence.',
    ],
    'source' => [
        'telekom' => 'Telekom', 'qualityhosting' => 'Quality Hosting',
    ],
    'mapping' => [
        'title' => 'Affecter la société',
        'submit' => 'Enregistrer l’affectation',
        'hint' => 'L’affectation s’applique à toutes les exécutions futures de cette organisation. Utilisez ensuite « Recalculer » pour qu’elle apparaisse dans le rapport.',
        'mode_label' => 'Facturation',
        'mode' => [
            'customer' => 'Directement : la société est le client',
            'partner' => 'Via un partenaire (client tiers)',
            'contact' => 'Contact Lexoffice',
        ],
        'mode_hint' => [
            'customer' => 'La facture est adressée à ce client lui-même.',
            'partner' => 'Le client choisi reçoit la facture et la répercute. La société est créée comme client tiers chez lui si elle manque.',
            'contact' => 'Sans fiche client : les factures de ce contact Lexoffice sont vérifiées.',
        ],
        'customer' => 'Client ou partenaire',
        'customer_placeholder' => 'Choisir un client',
        'contact' => 'UUID du contact Lexoffice',
        'contact_hint' => 'Nécessaire seulement pour « Contact Lexoffice » ; figure dans l’URL Lexoffice du contact.',
    ],
    'line' => [
        'header_only' => 'Pièce sans lignes',
        'microsoft' => 'Ligne Microsoft',
        'other' => 'Autre',
    ],
    'months' => 'mois',
];

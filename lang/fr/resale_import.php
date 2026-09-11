<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : resale_import.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Registre de revente (fonctionnalité 152) : messages des lecteurs d'import (CSV Telekom,
// XLSX/PDF Quality Hosting, liste générique). Noms de fichiers uniquement, jamais de chemins.
return [
    'column' => [
        'company' => 'Société',
        'product' => 'Produit',
        'start' => 'Début',
    ],
    'file' => [
        'unreadable' => 'Fichier illisible : :file',
        'xlsx_unreadable' => 'Fichier XLSX illisible : :file (:reason)',
        'no_header' => 'CSV sans ligne d\'en-tête : :file',
        'no_sheet' => 'XLSX sans feuille de calcul : :file',
        'too_large' => ':file est trop volumineux pour l\'import (au plus :rows lignes par feuille et :mb Mo décompressés).',
        'missing_columns' => 'Colonnes obligatoires manquantes : :columns',
    ],
    'row' => [
        'too_many_fields' => 'Ligne :line : :found champs au lieu de :expected (séparateur non échappé ?) - ignorée.',
        'missing_company_or_product' => 'Ligne :line : société ou produit manquant - ignorée.',
        'missing_company_or_entitlement' => 'Ligne :line : société ou entitlement manquant - ignorée.',
        'start_unreadable' => 'Ligne :line (:company) : début « :value » illisible - ignorée.',
        'end_unreadable' => 'Ligne :line (:company) : fin « :value » illisible - ignorée.',
        'end_before_start' => 'Ligne :line (:company) : la fin n\'est pas postérieure au début - ignorée.',
        'unknown_frequency' => 'Ligne :line (:company) : périodicité inconnue « :value » - ignorée.',
        'quantity_invalid' => 'Ligne :line (:company) : quantité « :value » illisible ou pas un entier positif - ignorée.',
        'unknown_currency' => 'Ligne :line (:company) : devise inconnue « :value » - ignorée.',
        'duplicate_id' => 'Ligne :line (:company) : identifiant « :value » en double avec la ligne :other - ignorée.',
        'term_unreadable' => 'Ligne :line (:company) : durée « :value » illisible - durée standard de la périodicité utilisée.',
        'fee_unreadable' => 'Ligne :line (:company) : frais « :value » illisibles - ignorée.',
        'dates_unreadable' => 'Ligne :line (:company) : date illisible (« :start » / « :end ») - ignorée.',
        'contract_end_before_start' => 'Ligne :line (:company) : la fin du contrat n\'est pas postérieure au début - ignorée.',
        'no_contract' => 'Ligne :line (:company) : sans numéro de contrat - ignorée.',
        'total_unreadable' => 'Ligne :line (:company) : prix total « :value » illisible - ignorée.',
        'contract_start_unreadable' => 'Ligne :line (:company) : début de contrat « :value » illisible - ignorée.',
        'status_end_before_start' => 'Ligne :line (:company) : la fin de contrat issue du statut n\'est pas postérieure au début - ignorée.',
        'status_unknown' => 'Ligne :line (:company) : statut de contrat « :value » inconnu - traité comme en cours.',
    ],
    'pricelist' => [
        'unreadable' => 'Liste de prix illisible : :file',
        'unreadable_reason' => 'Liste de prix illisible : :file (:reason)',
        'no_sheet' => 'Liste de prix sans feuille « Preisdaten » (colonne « Produkttarif » manquante).',
        'missing_columns' => 'Colonnes obligatoires de la liste de prix manquantes : :columns',
        'row_invalid' => 'Liste de prix ligne :line (:product) : durée, intervalle ou prix illisible - ignorée.',
        'valid_from_unreadable' => 'Liste de prix ligne :line (:product) : « Gültig ab » « :value » illisible - validité selon la page de garde ou la date d\'import.',
        'no_valid_from' => 'Liste de prix sans date de validité (ni page de garde ni colonne « Gültig ab ») - la date d\'import sert de début de validité.',
    ],
    'invoice' => [
        'unreadable' => 'Fichier PDF :file illisible (:reason).',
        'no_text' => 'Aucun texte n\'a pu être extrait de :file (même pas par OCR).',
        'no_number' => 'Aucun numéro de facture/avoir trouvé.',
        'no_date' => 'Aucune date de document trouvée.',
        'total_mismatch' => 'La somme des positions (:lines) diffère du montant net du document (:net) - des positions manquent ou ont été découpées autrement.',
    ],
];

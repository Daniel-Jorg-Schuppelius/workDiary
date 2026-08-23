<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : accounting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'action' => [
        'push' => 'Transférer vers la comptabilité',
    ],

    'flash' => [
        'pushed' => 'Client transféré vers la comptabilité (ID :id).',
        'failed' => 'Le transfert a échoué : :msg',
        'no_plugin' => 'Aucun système comptable actif.',
    ],

    'error' => [
        'accounting_leads' => 'La comptabilité détient les données de base — rien n’est transféré (paramètre « autorité des données de base »).',
        'no_syncer' => 'Le plugin :plugin ne transfère pas de contacts.',
    ],

    'authority' => [
        'workdiary' => 'workDiary dirige',
        'accounting' => 'La comptabilité dirige',
    ],

    // Lokale Buchhaltung (Feature 125, MVP-671): Einrichtung, Buchungshoheit,
    // Geschäftsjahre und Preflight.
    'ledger' => [
        'title' => 'Comptabilité locale',
        'menu' => 'Comptabilité',
        'setup_menu' => 'Configuration',
        'subtitle' => 'Autorité comptable, exercices et contrôle préalable de la configuration.',
        'open_ended' => 'en cours',
        'section' => [
            'profile' => 'Profil comptable',
            'preflight' => 'Contrôle préalable',
            'fiscal_years' => 'Exercices',
            'sovereignty' => 'Autorité comptable',
        ],
        'field' => [
            'profit_determination' => 'Détermination du résultat',
            'base_currency' => 'Devise de référence',
            'fiscal_year_start_month' => 'L\'exercice commence en',
            'starts_on' => 'Début des écritures (date de référence)',
            'note' => 'Note',
            'fiscal_year_starts_on' => 'Début de l\'exercice',
            'fiscal_year_label' => 'Libellé',
            'sovereignty' => 'Nouvelle autorité comptable',
            'external_provider' => 'Système directeur',
            'valid_from' => 'Valable à partir du',
            'reason' => 'Motif',
            'datev_account' => 'Compte DATEV',
            'euer_category' => 'Ligne recettes-dépenses',
            'euer_category_none' => '— sans affectation —',
            'deductible_percent' => 'Part déductible (%)',
            'description' => 'Description',
            'post_now' => 'Comptabiliser immédiatement',
            'reversal_reason' => 'Motif',
            'reversal_booked_on' => 'Date de la contre-écriture',
        ],
        'hint' => [
            'profit_determination' => 'Modifie l\'analyse (recettes/dépenses ou partie double), pas les règles d\'écriture et de preuve.',
            'base_currency' => 'La première version ne gère quʼune seule devise ; les pièces divergentes sont signalées au lieu dʼêtre converties.',
            'starts_on' => 'Les écritures locales commencent ce jour-là. Les pièces antérieures restent de lʼhistorique et ne sont pas rétro-comptabilisées.',
            'fiscal_year_starts_on' => 'Douze périodes mensuelles sont créées jusqu\'à la veille de l\'année suivante.',
            'fiscal_year_label' => 'Laisser vide pour « 2026 » ou « 2026/2027 » en cas dʼexercice décalé.',
            'sovereignty' => 'Qui tenait le grand livre et pour quelle période reste traçable, même après un changement.',
            'sovereignty_switch' => 'Le transfert des données reste le changement de comptabilité ; ici, seule la direction est réattribuée.',
            'external_provider' => 'Uniquement en cas dʼautorité externe : nom du système directeur (p. ex. lexoffice).',
            'datev_account' => 'Uniquement pour l\'export ; l\'écriture locale n\'en dépend pas.',
            'euer_category' => 'Détermine la ligne du formulaire dans laquelle le compte apparaît. Sans affectation, il figure parmi les cas non élucidés.',
            'deductible_percent' => "N'agit que sur l'aperçu recettes-dépenses — le journal porte toujours le montant intégral (p. ex. 70 % pour les frais de représentation).",
            'normal_balance' => 'Prérempli selon le type de compte, modifiable au cas par cas.',
            'post_now' => 'Une fois comptabilisée, l\'écriture ne se corrige que par une contre-écriture.',
            'reversal_booked_on' => 'Laisser vide pour le jour d\'origine, tant que sa période est ouverte.',
        ],
        'action' => [
            'activate' => 'Activer la comptabilité locale',
            'add_fiscal_year' => 'Créer un exercice',
            'switch' => 'Changer l\'autorité comptable',
            'switch_submit' => 'Réattribuer la direction',
            'add_account' => 'Créer un compte',
            'edit_account' => 'Modifier le compte',
            'deactivate' => 'Désactiver',
            'add_entry' => 'Nouvelle écriture',
            'post' => 'Comptabiliser',
            'reverse' => 'Extourner',
            'reverse_submit' => 'Créer la contre-écriture',
        ],
        'column' => [
            'fiscal_year' => 'Exercice',
            'range' => 'Période',
            'periods' => 'Périodes',
            'status' => 'Statut',
            'from' => 'À partir du',
            'to' => 'Jusqu\'au',
            'holder' => 'Direction',
            'reason' => 'Motif',
            'number' => 'Compte',
            'name' => 'Libellé',
            'type' => 'Type de compte',
            'normal_balance' => 'Sens du solde',
            'flags' => 'Caractéristiques',
            'journal_no' => 'N°',
            'booked_on' => 'Date de comptabilisation',
            'document_on' => 'Date de la pièce',
            'memo' => 'Libellé',
            'accounts' => 'Comptes',
            'amount' => 'Montant',
            'debit' => 'Débit',
            'credit' => 'Crédit',
            'account' => 'Compte',
            'document_reference' => 'Pièce',
            'posted_by' => 'Comptabilisé par',
            'source' => 'Source',
        ],
        'empty' => [
            'accounts' => "Aucun compte créé pour l'instant.",
            'entries' => 'Aucune écriture sur la période.',
            'fiscal_years' => 'Aucun exercice créé pour lʼinstant.',
            'sections' => 'Aucun changement de direction enregistré.',
        ],
        'flash' => [
            'saved' => 'Profil comptable enregistré.',
            'activated' => 'Comptabilité locale activée.',
            'fiscal_year_created' => 'Exercice :year créé avec ses périodes.',
            'sovereignty_switched' => 'Autorité comptable modifiée.',
            'account_saved' => 'Compte enregistré.',
            'account_deactivated' => 'Compte désactivé.',
            'imported' => 'Import des comptes : :imported nouveaux, :updated mis à jour, :errors erreurs.',
            'entry_saved' => 'Écriture enregistrée.',
            'entry_posted' => 'Écriture comptabilisée.',
            'entry_reversed' => 'Contre-écriture créée.',
        ],
        'error' => [
            'sovereignty' => 'Le :date, le grand livre est tenu par :holder — aucune écriture locale nʼest permise ce jour-là.',
            'fiscal_year_overlap' => 'La période chevauche l\'exercice :year.',
            'start_locked' => 'Le début des écritures ne peut plus être modifié après lʼactivation.',
            'provider_required' => 'Une autorité externe exige de nommer le système directeur.',
            'sovereignty_unchanged' => 'Cette autorité comptable sʼapplique déjà à cette date.',
            'later_section_exists' => 'Une période de direction plus récente commence déjà le :date.',
            'period_closed' => 'La période à partir du :period n\'accepte plus d\'écritures.',
            'no_period' => 'Aucune période comptable pour le :date.',
            'entry_frozen' => 'L\'écriture est comptabilisée — correction uniquement par contre-écriture.',
            'needs_two_lines' => 'Une écriture nécessite au moins deux lignes.',
            'unknown_account' => 'Une ligne renvoie à un compte inconnu.',
            'inactive_account' => 'Le compte :account est désactivé.',
            'foreign_currency_line' => 'Toutes les lignes doivent être en :currency.',
            'negative_amount' => 'Les montants sont positifs ; le sens vient du débit ou du crédit.',
            'both_sides' => 'Une ligne porte soit le débit, soit le crédit, jamais les deux.',
            'unbalanced' => 'Le débit (:debit) et le crédit (:credit) ne correspondent pas.',
            'reverse_not_posted' => 'Seule une écriture comptabilisée peut être extournée.',
            'reversal_reason_required' => 'L\'extourne exige un motif.',
            'account_in_use' => 'Ce compte a déjà été mouvementé — il ne peut qu\'être désactivé.',
            'entry_without_organization' => 'L\'écriture n\'a pas d\'organisation — merci d\'informer l\'administrateur.',
            'account_number_taken' => 'Ce numéro de compte existe déjà.',
        ],
        'preflight' => [
            'not_configured' => 'Profil non encore enregistré — le contrôle démarre au premier enregistrement.',
            'blocked_hint' => 'L\'activation reste bloquée tant qu\'un point est rouge.',
            'profile_missing' => 'Aucun profil comptable nʼest enregistré.',
            'starts_on_missing' => 'Aucun début des écritures nʼest défini.',
            'starts_on_ok' => 'Début des écritures : :date.',
            'fiscal_year_missing' => 'Aucun exercice ne couvre le début des écritures.',
            'periods_missing' => 'L\'exercice :year nʼa aucune période.',
            'fiscal_year_ok' => 'Exercice :year avec :count périodes.',
            'migration_active' => 'Un changement de comptabilité est en cours (:status) — la direction nʼest pas univoque entre-temps.',
            'migration_none' => 'Aucun changement de comptabilité en cours.',
            'handed_over' => 'Le lot DATEV :batch couvre déjà la période jusquʼau :to.',
            'handed_over_none' => 'Aucun lot exporté ne chevauche la période.',
            'sovereignty_conflict' => 'À partir du :date, :holder dirige déjà — la période serait occupée deux fois.',
            'sovereignty_ok' => 'Aucune période de direction concurrente.',
            'foreign_currency' => ':count pièces à partir de la date de référence ne sont pas en :currency ; elles restent visibles dans la boîte de saisie.',
            'base_currency_ok' => 'Toutes les pièces à partir de la date de référence sont en :currency.',
            'billing_external' => 'Les factures sont établies par :program — les pièces viendront de là.',
            'billing_local' => 'workDiary établit lui-même les factures de vente.',
            'master_data_external' => 'Les données de base sont dirigées par la comptabilité ; clients et fournisseurs ne sont pas écrasés dʼici.',
            'master_data_local' => 'workDiary dirige les données de base.',
            'key' => [
                'profile' => 'Profil',
                'starts_on' => 'Date de référence',
                'fiscal_year' => 'Exercice',
                'migration_run' => 'Changement',
                'handed_over' => 'Transferts',
                'sovereignty' => 'Direction',
                'base_currency' => 'Devise',
                'billing_mode' => 'Facturation',
                'master_data' => 'Données de base',
            ],
        ],
        'reversal_memo' => 'Extourne de l\'écriture n° :no',
        'opening_memo' => 'Écriture d\'ouverture',
        'reverse_hint' => 'L\'extourne crée une véritable contre-écriture. L\'écriture d\'origine reste inchangée.',
        'accounts' => [
            'title' => 'Plan comptable',
            'menu' => 'Plan comptable',
            'subtitle' => 'Comptes, sens du solde et correspondance DATEV de la comptabilité locale.',
        ],
        'journal' => [
            'title' => 'Journal',
            'menu' => 'Journal',
            'subtitle' => 'Écritures comptabilisées et préparées sur la période choisie.',
        ],
        'entry' => [
            'title' => 'Écriture',
            'head' => 'En-tête de l\'écriture',
            'lines' => 'Lignes de l\'écriture',
            'total' => 'Total',
            'is_reversal_of' => 'Cette écriture extourne l\'écriture n° :no.',
            'reversed_by' => 'Extournée par l\'écriture n° :no — :reason',
        ],
        'filter' => [
            'only_active' => 'actifs uniquement',
            'all_types' => 'Tous les types de compte',
            'all_states' => 'Tous les états',
        ],
        'flag' => [
            'open_item' => 'Postes ouverts',
            'bank' => 'Banque',
            'cash' => 'Caisse',
            'clearing' => 'Attente',
            'inactive' => 'Désactivé',
        ],
        'confirm' => [
            'deactivate' => 'Désactiver vraiment ce compte ? Les écritures existantes sont conservées.',
        ],
        'import' => [
            'line_invalid' => 'Ligne :line ignorée (numéro, nom ou type de compte manquant).',
        ],
    ],

    // Buchungs-Inbox und Mappingregeln (Feature 125, MVP-673).
    'inbox' => [
        'title' => 'Boîte de saisie comptable',
        'menu' => 'Boîte de saisie',
        'subtitle' => 'Pièces, notes de frais et opérations de caisse de la période avec leur statut comptable.',
        'empty' => 'Aucun élément ouvert sur la période.',
        'four_eyes_active' => 'Principe des quatre yeux actif : celui qui prépare une proposition ne la comptabilise pas lui-même.',
        'state' => [
            'blocked' => 'Bloqué',
            'open' => 'Non comptabilisé',
            'ready' => 'Prêt',
            'posted' => 'Comptabilisé',
        ],
        'column' => [
            'kind' => 'Source',
            'document' => 'Pièce',
            'booked_on' => 'Date',
            'proposal' => 'Proposition',
        ],
        'filter' => [
            'all_kinds' => 'Toutes les sources',
            'include_posted' => 'afficher comptabilisés',
        ],
        'action' => [
            'prepare' => 'Accepter la proposition',
            'prepare_and_post' => 'Accepter et comptabiliser',
            'batch_prepare' => 'Tout accepter',
            'batch_post' => 'Tout accepter et comptabiliser',
        ],
        'confirm' => [
            'batch' => 'Accepter comme brouillons tous les éléments non bloqués de la période ?',
            'batch_post' => 'Accepter ET comptabiliser tous les éléments non bloqués ? Les écritures comptabilisées ne se corrigent que par contre-écriture.',
        ],
        'flash' => [
            'prepared' => 'Proposition acceptée.',
            'batch' => 'Lot : :prepared acceptés, :posted comptabilisés, :failed en attente.',
        ],
        'error' => [
            'four_eyes' => 'Principe des quatre yeux : vous avez préparé cette écriture — quelqu\'un d\'autre doit la comptabiliser.',
        ],
        'blocker' => [
            'missing_rule' => 'Aucune règle comptable pour :role:criteria.',
            'handed_over' => 'La pièce fait déjà partie d\'un lot exporté.',
            'no_tax_breakdown' => 'La pièce ne comporte pas de ventilation de TVA.',
            'no_amount' => 'La pièce ne comporte aucun montant.',
            'no_lines' => 'La proposition n\'a aucune ligne d\'écriture.',
            'sovereignty' => 'Sur cette période, l\'organisation ne tient pas de grand livre local.',
            'foreign_currency' => 'La pièce est en :currency, la comptabilité en :base — aucune conversion justifiable n\'existe encore.',
            'unsupported_target' => 'Aucun chemin comptable n\'existe encore pour cette cible de paiement.',
        ],
        'memo' => [
            'sales_invoice' => 'Facture :number · :customer',
            'incoming_invoice' => 'Facture d\'achat :number · :seller',
            'expense' => 'Note de frais :description · :user',
            'cash_entry' => 'Caisse :register · :purpose',
            'payment' => 'Paiement (:kind) · :target',
        ],
        'reversal_reason' => [
            'unmatched' => 'Affectation de paiement annulée — contre-écriture.',
        ],
    ],
    'rules' => [
        'title' => 'Règles comptables',
        'menu' => 'Règles comptables',
        'subtitle' => 'Correspondance source et rôle vers un compte — versionnée et datée.',
        'empty' => 'Aucune règle comptable créée.',
        'fallback' => 'Règle par défaut (tous les cas)',
        'no_tax_code' => '— sans code de taxe —',
        'column' => [
            'role' => 'Rôle',
            'match' => 'Critères',
            'validity' => 'Validité',
            'priority' => 'Priorité',
        ],
        'field' => [
            'tax_code' => 'Code de taxe',
            'match_key' => 'Critère',
            'match_value' => 'Valeur',
        ],
        'hint' => [
            'role' => 'Ce que représente le compte dans l\'écriture — produit, créance, TVA déductible …',
            'tax_code' => 'Facultatif ; rattache le résultat fiscal figé de la pièce à un compte.',
            'match' => 'Laisser vide pour la règle par défaut. Exemples : tax_rate = 19.00, expense_category_id = 5.',
            'priority' => 'La plus élevée gagne ; à égalité, la règle la plus spécifique.',
        ],
        'action' => [
            'add' => 'Créer une règle',
            'edit' => 'Modifier la règle',
        ],
        'confirm' => [
            'deactivate' => 'Désactiver la règle ? Les écritures existantes gardent leur version de règle.',
        ],
        'flash' => [
            'saved' => 'Règle comptable enregistrée.',
            'versioned' => 'Nouvelle version de règle créée à partir de la date.',
            'deactivated' => 'Règle comptable désactivée.',
        ],
    ],

    // Offene Posten (Feature 125, MVP-674).
    'open_items' => [
        'title' => 'Postes ouverts',
        'menu' => 'Postes ouverts',
        'subtitle' => 'Créances et dettes issues des écritures comptabilisées, avec balance âgée.',
        'empty' => 'Aucun poste ouvert.',
        'overdue_days' => 'en retard de :days jours',
        'settle_hint' => 'Ouvert : :open. Les paiements viennent du rapprochement bancaire — ici seulement escompte, retenue ou passage en perte.',
        'column' => [
            'counterparty' => 'Tiers',
            'due_date' => 'Échéance',
            'original' => 'Origine',
            'open' => 'Ouvert',
            'kind' => 'Type',
        ],
        'bucket' => [
            'not_due' => 'Non échu',
            'd30' => '1–30 jours',
            'd60' => '31–60 jours',
            'd90' => '61–90 jours',
            'd90plus' => 'plus de 90 jours',
        ],
        'action' => [
            'settle' => 'Solder',
            'show_entry' => 'Voir l\'écriture',
        ],
        'flash' => [
            'settled' => 'Règlement enregistré.',
        ],
    ],

    // Wiederkehrende Vorgänge (Feature 125, MVP-675).
    'recurring' => [
        'title' => 'Opérations récurrentes',
        'menu' => 'Récurrent',
        'subtitle' => 'Attentes de pièces, modèles d\'écriture et plans de facturation en un coup d\'œil.',
        'principle' => 'Une attente de pièce ne crée ni pièce ni écriture — seul l\'original la satisfait. Les modèles d\'écriture ne créent que des brouillons.',
        'invoice_schedules_hint' => 'Les facturations récurrentes restent au plan de facturation ; affichées ici pour information.',
        'preview' => 'Prochaines échéances : :dates',
        'no_account' => '— aucun compte —',
        'section' => [
            'open_runs' => 'Opérations ouvertes',
            'templates' => 'Modèles',
            'invoice_schedules' => 'Plans de facturation',
        ],
        'column' => [
            'template' => 'Modèle',
            'period' => 'Période',
            'expected' => 'Attendu',
            'name' => 'Libellé',
            'kind' => 'Type',
            'interval' => 'Rythme',
            'next_due' => 'Prochaine échéance',
            'responsible' => 'Responsable',
        ],
        'field' => [
            'due_day' => 'Jour d\'échéance',
            'starts_on' => 'Début',
            'ends_on' => 'Fin',
        ],
        'hint' => [
            'kind' => 'L\'attente de pièce attend un original ; le modèle d\'écriture crée un brouillon.',
            'due_day' => '1–28, pour que chaque mois comporte ce jour.',
            'accounts' => 'Uniquement pour les modèles d\'écriture — avec le montant attendu.',
        ],
        'action' => [
            'add' => 'Créer un modèle',
            'edit' => 'Modifier le modèle',
            'run' => 'Exécuter',
            'pause' => 'Suspendre',
            'resume' => 'Reprendre',
            'end' => 'Terminer',
            'open_schedules' => 'Ouvrir les plans',
        ],
        'confirm' => [
            'end' => 'Terminer le modèle ? Les opérations déjà créées subsistent.',
        ],
        'empty' => [
            'runs' => 'Aucune opération ouverte.',
            'templates' => 'Aucun modèle créé.',
            'schedules' => 'Aucun plan actif.',
        ],
        'flash' => [
            'saved' => 'Modèle enregistré.',
            'versioned' => 'Modèle enregistré en nouvelle version.',
            'paused' => 'Modèle suspendu.',
            'resumed' => 'Modèle repris.',
            'ended' => 'Modèle terminé.',
            'ran' => 'Exécution effectuée.',
        ],
        'error' => [
            'already_closed' => 'Cette opération est déjà close.',
            'template_incomplete' => 'Un modèle d\'écriture exige un compte débit, un compte crédit et un montant.',
        ],
        'blocker' => [
            'no_lines' => 'Le modèle n\'a aucune ligne d\'écriture.',
        ],
        'notification' => [
            'title' => 'Opération récurrente en retard : :name',
            'message' => 'Échéance le :due — statut : :status.',
        ],
    ],

    // Finanzberichte (Feature 125, MVP-676).
    'reports' => [
        'title' => 'Rapports financiers',
        'menu' => 'Rapports financiers',
        'subtitle' => 'Analyses de la comptabilité locale pour la période choisie.',
        'period' => 'Période :from – :to',
        'as_of' => 'Au :date',
        'empty' => 'Aucune donnée sur la période.',
        'vat_preview_hint' => 'Aperçu vérifiable — le MVP ne transmet aucune déclaration de TVA.',
        'euer_preview_hint' => "Aperçu selon l'encaissement et le décaissement (§ 11 EStG), classé selon les lignes du formulaire allemand — ce n'est pas le formulaire.",
        'euer_manual_hint' => 'à saisir manuellement',
        'pnl_hint' => 'Résultat par groupes de comptes — pas un compte de résultat certifié.',
        'column' => [
            'euer_category' => 'Ligne recettes-dépenses',
            'gross' => 'Montant',
            'deductible' => 'Déductible',
            'not_deductible' => 'Non déductible',
            'opening' => 'Solde initial',
            'closing' => 'Solde final',
            'balance' => 'Solde',
            'direction' => 'Sens',
            'payable' => 'TVA due',
            'result' => 'Résultat',
            'section' => 'Section',
        ],
        'section' => [
            'income' => 'Produits',
            'expense' => 'Charges',
            'balances' => 'Comptes bancaires et de caisse',
        ],
        'kpi' => [
            'cash' => 'Banque et caisse',
            'receivable' => 'Créances',
            'payable' => 'Dettes',
            'forecast' => 'Prévision',
            'findings' => 'Constats',
        ],
        'aging' => [
            'receivable' => 'Balance âgée des créances',
            'payable' => 'Balance âgée des dettes',
        ],
        'unclear' => [
            'title' => 'Cas non clarifiés',
            'none' => 'Aucun cas non clarifié.',
            'open_items' => ':count postes ouverts ne sont pas soldés sur la période.',
            'settlement_without_item' => 'Règlement :id sans poste ouvert correspondant.',
            'settlement_without_source' => 'Règlement :id sans pièce d’origine exploitable.',
            'account_without_category' => 'Le compte :account n’a pas de ligne recettes-dépenses.',
        ],
        'quality' => [
            'headline' => 'Ce qui bloque les analyses',
            'none' => 'Aucun constat.',
            'drafts' => ':count écritures ne sont pas comptabilisées.',
            'unbalanced' => ':count brouillons ne sont pas équilibrés.',
            'blocked_runs' => ':count exécutions récurrentes sont bloquées.',
            'open_expectations' => ':count attentes de pièces sont encore ouvertes.',
            'ten_day_rule' => ':count paiements se situent entre le 22.12 et le 10.01 et relèvent de l’année voisine selon la pièce (§ 11 al. 1 phr. 2 EStG).',
            'open_clearing' => ":count comptes d'attente ne sont pas encore soldés.",
            'overdue_filings' => ':count échéances déclaratives sont dépassées et non marquées comme déposées.',
            'kpi' => [
                'drafts' => 'Brouillons',
                'unbalanced' => 'Déséquilibrés',
                'blocked_runs' => 'Exécutions bloquées',
                'open_expectations' => 'Attentes ouvertes',
            ],
        ],
        'card' => [
            'trial_balance' => [
                'title' => 'Balance générale',
                'text' => 'Report, mouvement et solde par compte.',
            ],
            'account_ledger' => [
                'title' => 'Grand livre',
                'text' => 'Tous les mouvements d\'un compte avec accès à l\'écriture.',
            ],
            'vat' => [
                'title' => 'TVA',
                'text' => 'TVA collectée, déductible et due, en aperçu.',
            ],
            'euer' => [
                'title' => 'Aperçu recettes-dépenses',
                'text' => 'Recettes et dépenses selon encaissement et décaissement.',
            ],
            'recapitulative' => [
                'title' => 'État récapitulatif',
                'text' => 'Livraisons intracommunautaires par n° de TVA',
            ],
            'pnl' => [
                'title' => 'Résultat',
                'text' => 'Produits et charges par groupes de comptes.',
            ],
            'liquidity' => [
                'title' => 'Trésorerie',
                'text' => 'Soldes réels, postes ouverts et prévision — séparés.',
            ],
            'quality' => [
                'title' => 'Qualité comptable',
                'text' => 'Brouillons, exécutions bloquées et attentes ouvertes.',
            ],
            'journal' => [
                'title' => 'Journal',
                'text' => 'Toutes les écritures comptabilisées dans l\'ordre du journal.',
            ],
            'open_items' => [
                'title' => 'Postes ouverts',
                'text' => 'Créances et dettes avec balance âgée.',
            ],
        ],
    ],

    // Periodenabschluss (Feature 125, MVP-677).
    'closing' => [
        'title' => 'Clôture des périodes',
        'menu' => 'Clôture',
        'subtitle' => 'Clôturer les périodes provisoirement ou définitivement — et les rouvrir avec un motif.',
        'blocked_hint' => 'La clôture reste bloquée tant qu\'un point est rouge.',
        'reopen_hint' => 'La réouverture lève une clôture. Elle est consignée avec son motif dans la chaîne de preuve.',
        'column' => [
            'period' => 'Période',
            'closed_at' => 'Clôturée',
            'reopened' => 'Rouverte',
        ],
        'field' => [
            'reason' => 'Motif',
        ],
        'action' => [
            'soft_close' => 'Clôturer provisoirement',
            'close' => 'Clôturer définitivement',
            'close_submit' => 'Clôturer la période',
            'reopen' => 'Rouvrir',
            'reopen_submit' => 'Ouvrir la période',
            'close_year' => 'Clôturer l\'exercice',
        ],
        'confirm' => [
            'year' => 'Clôturer l\'exercice ? Toutes les périodes doivent être clôturées.',
        ],
        'check' => [
            'no_drafts' => 'Aucun brouillon ouvert dans la période.',
            'drafts' => ':count écritures ne sont pas comptabilisées.',
            'balanced' => 'Toutes les écritures sont équilibrées.',
            'unbalanced' => ':count écritures ne sont pas équilibrées.',
            'sequence_ok' => 'Aucune période antérieure ouverte.',
            'earlier_open' => ':count périodes antérieures sont encore ouvertes.',
            'key' => [
                'drafts' => 'Brouillons',
                'balanced' => 'Équilibre',
                'sequence' => 'Ordre',
            ],
        ],
        'flash' => [
            'soft_closed' => 'Période clôturée provisoirement.',
            'closed' => 'Période clôturée.',
            'reopened' => 'Période rouverte.',
            'year_closed' => 'Exercice clôturé.',
        ],
        'error' => [
            'reason_required' => 'La réouverture exige un motif.',
            'already_open' => 'La période est déjà ouverte.',
            'wrong_status' => 'Cette étape est impossible dans l\'état :status.',
            'periods_open' => ':count périodes ne sont pas clôturées.',
        ],
    ],

    // Startsalden und DATEV-Übergabe (Feature 125, MVP-677).
    'opening' => [
        'title' => 'Importer les soldes initiaux',
        'subtitle' => 'CSV avec compte, débit et crédit — vérifier puis comptabiliser.',
        'hint' => 'Le MVP reprend solde initial, postes ouverts et date de référence ; un journal ancien complet n\'est volontairement pas importé.',
        'field' => [
            'file' => 'Fichier CSV',
        ],
        'action' => [
            'dry_run' => 'Simulation',
            'import' => 'Importer',
        ],
        'flash' => [
            'dry_run' => 'Simulation : :lines lignes, débit :debit, crédit :credit, :errors erreurs.',
            'imported' => 'Écriture d\'ouverture :no créée.',
        ],
        'error' => [
            'missing_account' => 'Ligne :line sans compte.',
            'unknown_account' => 'Le compte :account (ligne :line) n\'existe pas.',
            'both_sides' => 'La ligne :line porte débit et crédit.',
            'unbalanced' => 'Le débit (:debit) et le crédit (:credit) ne correspondent pas.',
        ],
    ],
    'datev' => [
        'title' => 'Transfert DATEV',
        'subtitle' => 'Lignes des écritures de la période en CSV.',
        'hint' => 'Généré à partir des écritures comptabilisées — non redérivé des pièces.',
        'action' => [
            'export' => 'Exporter',
        ],
    ],

    // Kontenplan-Vorlagen (Feature 125, MVP-678).
    'template' => [
        'title' => 'Plan comptable depuis un modèle',
        'subtitle' => 'Créer comptes, codes de taxe et règles comptables en une fois.',
        'hint_first' => 'Le modèle crée comptes, codes de taxe et règles correspondantes — la boîte de saisie est immédiatement utilisable.',
        'hint_additive' => 'Ajout uniquement : les comptes et règles existants restent inchangés.',
        'disclaimer' => 'Extrait de démarrage inspiré du plan comptable standard allemand correspondant, valable pour l\'Allemagne. Le choix des comptes et la correspondance fiscale doivent être validés avant la première écriture.',
        'field' => [
            'template' => 'Modèle',
        ],
        'action' => [
            'apply' => 'Appliquer le modèle',
        ],
        'flash' => [
            'applied' => 'Modèle appliqué : :accounts comptes, :tax_codes codes de taxe, :rules règles créés, :skipped ignorés.',
        ],
        'error' => [
            'unknown' => 'Modèle de plan comptable inconnu : :code',
        ],
    ],

    // Versteuerungsart (Feature 125, MVP-679).
    'taxation' => [
        'title' => 'Régime de TVA',
        'subtitle' => 'Sur les débits ou sur les encaissements — n\'affecte que l\'analyse de TVA.',
        'current' => 'Actuel : :method',
        'default_hint' => 'Sans réglage, le régime sur les débits s\'applique (§ 16 al. 1 UStG).',
        'field' => [
            'method' => 'Régime',
            'valid_from' => 'Valable à partir du',
        ],
        'hint' => [
            'method' => 'Le régime sur les encaissements (§ 20 UStG) exige une autorisation ; la TVA déductible n\'est pas concernée.',
            'valid_from' => 'Généralement au changement d\'année — le 1er janvier suivant est proposé.',
        ],
        'column' => [
            'changeover' => 'Postes ouverts lors du changement',
        ],
        'action' => [
            'switch' => 'Changer de régime',
            'switch_submit' => 'Enregistrer le changement',
        ],
        'changeover' => [
            'headline' => ':count postes ouverts pour :amount sont concernés à la date de référence.',
            'hint' => '§ 20 phrase 3 UStG : les opérations ne doivent être ni comptabilisées deux fois ni rester non taxées. Le changement n\'est pas bloqué — l\'appréciation revient au conseil fiscal.',
            'summary' => ':count postes / :amount',
        ],
        'flash' => [
            'switched' => 'Régime de TVA modifié.',
        ],
        'error' => [
            'unchanged' => 'Ce régime s\'applique déjà à cette date.',
            'later_section' => 'Une période plus récente commence déjà le :date.',
        ],
    ],
    // Klärungsbuchung und interne Umbuchung (Feature 125, MVP-681).
    'clearing' => [
        'title' => 'Écriture d’attente',
        'memo' => 'Cas à clarifier : :purpose',
        'no_account' => "Aucun compte d'attente n'est configuré. Marquez un compte du plan comptable comme compte d'attente.",
        'action' => [
            'post' => 'Comptabiliser en compte d’attente',
            'post_submit' => 'Créer l’écriture d’attente',
        ],
        'field' => [
            'account' => "Compte d'attente",
            'note' => 'Pourquoi cette opération est-elle incertaine ?',
            'follow_up_on' => 'Date de rappel',
        ],
        'hint' => [
            'account' => "Seuls les comptes explicitement marqués comme comptes d'attente sont proposés.",
            'note' => "Obligatoire — c'est la seule trace expliquant pourquoi rien n'a été affecté ici.",
            'follow_up_on' => 'Le cas doit être résolu à cette date.',
        ],
        'error' => [
            'not_a_clearing_account' => "Le compte choisi n'est pas un compte d'attente.",
            'note_required' => 'Un motif est obligatoire.',
        ],
        'blocker' => [
            'unassigned' => "Aucune pièce affectée — comptabilisable uniquement via une affectation ou le compte d'attente.",
        ],
        'flash' => [
            'posted' => "Écriture d'attente créée.",
        ],
    ],
    'transfer' => [
        'title' => 'Virement interne',
        'action' => [
            'record' => 'Virement interne',
            'record_submit' => 'Comptabiliser le virement',
        ],
        'field' => [
            'from_account' => 'Du compte',
            'to_account' => 'Vers le compte',
        ],
        'hint' => [
            'note' => 'À quoi correspond ce mouvement (p. ex. retrait bancaire pour la caisse) ?',
        ],
        'error' => [
            'same_account' => 'Le compte source et le compte cible doivent différer.',
            'not_a_money_account' => "Le compte :account n'est ni un compte bancaire, ni une caisse, ni un compte de transit.",
            'amount_positive' => 'Le montant doit être supérieur à zéro.',
        ],
        'flash' => [
            'recorded' => 'Virement comptabilisé.',
        ],
    ],

    // Meldepflichten der Umsatzsteuer (Feature 125, MVP-684).
    'filing' => [
        'fields' => [
            'title' => 'Codes de la déclaration',
            'subtitle' => 'Affectation des codes de taxe aux cases de la déclaration allemande — aide au rapprochement, pas le formulaire.',
            'tax_codes' => 'Codes de taxe',
            'remaining' => 'Acompte restant (83)',
            'unclear' => 'Code de taxe :code sans numéro de case.',
            'column' => [
                'field' => 'Code',
                'base' => 'Base imposable',
                'tax' => 'Montant de taxe',
            ],
            'hint' => [
                'base' => 'Case de la base imposable, p. ex. 81 (19 %), 86 (7 %), 41 (livraisons intracom.).',
                'tax' => 'Case du montant de taxe, p. ex. 66 (TVA déductible), 61 (acquisition intracom.).',
            ],
            'flash' => [
                'saved' => 'Codes enregistrés.',
            ],
        ],
        'calendar' => [
            'menu' => 'Échéances fiscales',
            'title' => 'Échéances fiscales',
            'subtitle' => 'Échéances de TVA et état de traitement.',
            'hint' => "Les échéances sont calculées (§ 108 al. 3 AO : week-ends et jours fériés reportent au jour ouvrable suivant). Rien n'est transmis.",
            'tax_advised' => 'assisté par un conseil fiscal',
            'overdue' => 'En retard',
            'empty' => 'Aucune échéance sur la période.',
            'column' => [
                'kind' => 'Obligation',
                'due_on' => 'Échéance',
            ],
            'action' => [
                'submitted' => 'Marquer comme déposé',
            ],
        ],
        'notification' => [
            'title' => ':kind arrive à échéance',
            'message' => 'Période :period — échéance :due.',
        ],
        'no_period' => "Aucune période de déclaration n'est définie pour cette organisation (micro-entreprise § 19 UStG).",
        'prepayment_memo' => 'Acompte spécial 1/11 pour :year',
        'prepayment' => [
            'title' => "Comptabiliser l'acompte spécial",
            'submit' => "Comptabiliser l'acompte",
            'calculation' => 'Un onzième de :year : impôt :tax, annualisé :annualised → :amount.',
            'annualised_hint' => "Activité sur :months mois seulement l'an passé — ramené à une année pleine (§ 47 al. 3 UStDV).",
            'due_hint' => 'Déclaration et paiement avant le :date.',
        ],
        'title' => 'Obligations déclaratives',
        'subtitle' => 'Période de déclaration de TVA, prorogation permanente et échéances.',
        'current' => 'Actuellement : :interval',
        'default_hint' => "Sans réglage, le trimestre civil s'applique (§ 18 al. 2 phr. 1 UStG).",
        'field' => [
            'period' => 'Période',
            'remaining' => 'Acompte restant',
            'prepayment_account' => 'Compte acompte spécial',
            'money_account' => 'Compte de trésorerie',
            'interval' => 'Période de déclaration',
            'valid_from' => 'Valable à partir du',
            'year' => 'Année civile',
            'granted_on' => 'Accordée le',
            'special_prepayment' => 'Acompte spécial (1/11)',
        ],
        'hint' => [
            'prepayment_account' => 'Habituellement 1781 (SKR03) ou 3830 (SKR04) — acomptes de TVA 1/11.',
            'interval' => "C'est l'administration fiscale qui décide de la période — le programme ne fait que la consigner.",
            'valid_from' => "En règle générale un changement d'année ; un changement en cours d'année reste possible.",
            'granted_on' => "Laisser vide tant que la prorogation n'est pas accordée.",
            'special_prepayment' => "Un onzième des acomptes de l'année précédente ; déclaration et paiement avant le 10 février (§ 47 UStDV).",
        ],
        'action' => [
            'switch' => 'Changer la période',
            'switch_submit' => 'Appliquer la période',
        ],
        'error' => [
            'note_required' => '« Non requis » exige une justification.',
            'amount_positive' => 'Le montant doit être supérieur à zéro.',
            'not_a_money_account' => "Le compte choisi n'est ni un compte bancaire ni une caisse.",
            'no_extension' => 'Aucune prorogation enregistrée pour :year.',
            'unchanged' => 'Cette période de déclaration est déjà valable à cette date.',
            'later_section' => 'Une section débutant le :date existe déjà. Modifiez-la d’abord.',
        ],
        'flash' => [
            'marked' => 'Statut enregistré.',
            'prepayment_posted' => 'Acompte spécial comptabilisé.',
            'switched' => 'Période de déclaration modifiée.',
            'extension_saved' => 'Prorogation enregistrée.',
        ],
        'suggestion' => [
            'headline' => 'Proposition issue de :year (impôt :amount) : :interval.',
            'monthly' => "Au-delà de 9 000 € d'impôt l'an passé — mensuel (§ 18 al. 2 phr. 2 UStG).",
            'quarterly' => 'Entre 2 000 € et 9 000 € — trimestre civil (§ 18 al. 2 phr. 1 UStG).',
            'annual' => "Jusqu'à 2 000 € — dispense de déclaration anticipée possible (§ 18 al. 2 phr. 3 UStG).",
            'none' => 'Pas de déclaration anticipée de TVA (micro-entreprise § 19 UStG).',
            'founder_rule' => "À partir de la période d'imposition 2027, les créations d'entreprise redeviennent soumises au dépôt mensuel.",
        ],
        'extension' => [
            'short' => 'avec prorogation',
            'title' => 'Prorogation permanente',
            'active' => 'Prorogation depuis :year',
            'no_prepayment' => 'Les déclarants trimestriels obtiennent la prorogation sans acompte spécial (§ 46 UStDV).',
            'prepayment_note' => 'Acompte spécial :amount enregistré pour :year.',
        ],
    ],

    // Zusammenfassende Meldung (Feature 125, MVP-687).
    'recapitulative' => [
        'title' => 'État récapitulatif',
        'hint' => "Déclaration selon § 18a UStG. La prorogation permanente ne s'applique PAS ici — l'échéance reste le 25e jour suivant la période.",
        'due' => 'Échéance : :date',
        'interval' => 'Période : :interval',
        'total' => 'Livraisons intracommunautaires',
        'column' => [
            'vat_id' => 'N° de TVA',
        ],
        'unclear' => [
            'missing_vat_id' => 'Écriture :entry (:customer) sans n° de TVA du destinataire.',
            'unknown_customer' => 'sans client',
        ],
    ],

];

<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : import.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'entity' => [
        'customers' => 'Clients',
        'suppliers' => 'Fournisseurs',
        'articles' => 'Articles',
        'projects' => 'Projets',
        'users' => 'Utilisateurs',
        'materials' => 'Matériaux',
        'vehicles' => 'Véhicules',
        'scheduled_shifts' => 'Plannings de service',
        'tours' => 'Tournées',
        'remote_sessions' => 'Sessions de maintenance à distance',
        'attendances' => 'Pointages',
        'project_times' => 'Temps de projet',
        // MVP-707 (Vollscan H20): Altsystem-Übernahme.
        'invoices' => 'Anciennes factures (postes ouverts)',
        'quotes' => 'Devis',
        'assets' => 'Actifs',
        'contact_persons' => 'Interlocuteurs',
        'documents' => 'Documents (ZIP)',
    ],
    'template' => [
        'example_required' => "Valeur d'exemple (obligatoire)",
        'example_optional' => "Valeur d'exemple (optionnelle)",
        'download' => 'Télécharger le modèle',
    ],

    'state' => [
        'preflight' => 'Contrôle préalable',
        'awaitingApproval' => 'En attente d\'approbation',
        'running' => 'En cours',
        'succeeded' => 'Réussi',
        'partial' => 'Partiel',
        'failed' => 'Échoué',
    ],
    'errorCode' => [
        'required' => 'Champ obligatoire manquant',
        'format' => 'Erreur de format',
        'unique' => 'Valeur non unique',
        'fkMissing' => 'Référence introuvable',
        'tooLong' => 'Valeur trop longue',
        'outOfRange' => 'Valeur hors plage',
        'persist' => 'Erreur de persistance',
        'headerMissing' => 'Colonne manquante',
        'headerUnknown' => 'Colonne inconnue',
        'periodLocked' => 'Période verrouillée',
        'skipped' => 'Ignoré',
        'blocked' => 'Bloqué',
    ],
    'error' => [
        'required' => 'Le champ obligatoire :field est manquant.',
        'tooLong' => 'Le champ :field dépasse la longueur maximale de :max caractères.',
        'header' => [
            'missing' => 'La colonne requise :column est manquante dans l\'en-tête CSV.',
            'duplicate' => 'La colonne :column apparaît plusieurs fois.',
        ],
        'format' => [
            'default' => 'Le champ :field a un format invalide (:reason).',
            'email' => 'Adresse e-mail invalide.',
            'country' => 'Le code pays doit comporter 2 à 3 lettres majuscules (ISO 3166-1).',
            'currency' => 'Le code devise doit comporter 3 lettres majuscules (ISO 4217).',
            'enum' => 'La valeur n\'est pas un statut valide.',
            'parse' => 'Le fichier n\'a pas pu être analysé : :reason',
            'xlsxUnreadable' => 'Le fichier Excel est endommagé ou n\'est pas un format XLSX valide.',
            'xlsxEmpty' => 'La première feuille du fichier Excel ne contient aucune ligne.',
            'date' => 'Date invalide (attendu p. ex. « 28.05.2026, 09:42:09 »).',
            'time' => 'Heure invalide (attendu HH:MM).',
            'status' => 'La valeur n\'est pas un statut valide.',
            'amount' => 'Montant invalide.',
        ],
        'outOfRange' => [
            'rowLimit' => 'Limite de lignes (:max) dépassée — reste ignoré.',
            'contactPersons' => 'Plus de :max interlocuteurs par client/fournisseur ne sont pas prévus.',
        ],
        'fkMissing' => [
            'customer' => 'Aucun client avec le numéro :number trouvé.',
            'supplier' => 'Aucun fournisseur avec le numéro :number trouvé.',
            'asset' => 'Aucun actif avec le numéro :number trouvé.',
            'article' => 'Aucun article avec le numéro :number trouvé.',
            'projectNumber' => 'Aucun projet avec le numéro :number trouvé.',
            'customerName' => 'Aucun client unique nommé « :value » trouvé.',
            'user' => 'Aucun utilisateur avec l\'e-mail :value trouvé.',
            'project' => 'Aucun projet « :value » trouvé — ligne placée dans la boîte d\'affectation.',
        ],
        // MVP-707: Altsystem-Übernahme (Rechnungshoheit, Altrechnungen, Dokument-ZIP).
        'blocked' => [
            'invoiceSovereignty' => 'La facturation est gérée par :program — les anciennes factures locales sont bloquées pour ce client.',
        ],
        'invoice' => [
            'amountMissing' => 'Montant brut ou net (avec taux de TVA) manquant.',
            'paidExceedsTotal' => 'Le montant payé (:paid) dépasse le montant de la facture (:total).',
            'numberTaken' => 'Le numéro de facture :number est déjà utilisé.',
        ],
        'document' => [
            'manifestMissing' => 'Le fichier ZIP ne contient pas de manifest.csv.',
            'fileMissing' => 'Le fichier « :file » ne figure pas dans le ZIP.',
            'extension' => 'L\'extension « :ext » n\'est pas autorisée.',
            'mime' => 'Le contenu du fichier (:mime) n\'est pas autorisé.',
            'targetType' => 'Le type de cible doit être customer, project ou asset.',
            'noContent' => 'Les documents ne peuvent être repris que via l\'import ZIP (manifest.csv + fichiers).',
            'zipUnreadable' => 'Le fichier ZIP n\'a pas pu être lu : :reason',
            'tooLarge' => 'Le fichier « :file » dépasse la limite de :max Mo.',
            'noActor' => 'Exécution d\'import sans utilisateur déclencheur — les documents ont besoin d\'un créateur.',
        ],
        'persist' => [
            'noBookingUser' => 'Aucun utilisateur imputable trouvé dans l\'organisation.',
        ],
        // MVP-438 : verrou GoBD — pas d\'écrasement silencieux des périodes vérifiées.
        'periodLocked' => [
            'attendance' => 'Le jour :date est verrouillé par la clôture journalière ou l\'approbation mensuelle — ligne ignorée.',
            'projectTime' => 'La période :date est déjà clôturée/exportée — ligne ignorée.',
        ],
        // MVP-438 : lignes d\'avertissement iCal (mappage volontairement prudent).
        'ical' => [
            'allDay' => 'Événement toute la journée « :event » ignoré (non comptabilisable comme présence).',
            'noTime' => 'Événement « :event » sans heure ignoré.',
            'category' => 'Événement « :event » hors de la liste de catégories autorisées ignoré.',
            'transparent' => 'Événement « :event » marqué libre/absent ignoré.',
            'recurring' => 'Événement récurrent « :event » : seule l\'instance de base a été importée (l\'expansion de la série viendra plus tard).',
            'unsupportedEntity' => 'L\'import iCal n\'est pas pris en charge pour ce type d\'import.',
        ],
    ],

    // MVP-707: Upload-Hinweise je Dateiart + Texte der Altrechnungs-Übernahme.
    'upload' => [
        'csv' => 'Fichier CSV, Excel ou iCal (.csv, .xlsx, .ics, max. :mb Mo, :rows lignes)',
        'zip' => 'Fichier ZIP avec manifest.csv et les fichiers de documents (.zip, max. :mb Mo, :entries fichiers)',
        'zipHint' => 'Chaque ligne du manifest.csv (modèle ci-dessus) référence un fichier du ZIP et l\'affecte à un client, un projet ou un actif.',
    ],
    'legacy' => [
        'position' => 'Reprise de l\'ancien système — facture :number',
        'note' => 'Ancienne facture reprise depuis :source (poste ouvert d\'ouverture, sans écriture au journal).',
    ],
];

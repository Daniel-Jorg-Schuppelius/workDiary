<?php

return [
    'action' => 'Convertir un fichier de facture en facture électronique', 'title' => 'Importer un fichier de facture', 'eyebrow' => 'Assistant de facture électronique', 'submit' => 'Lire la facture',
    'intro' => 'Les factures PDF, Word et Excel sont lues sans exécuter de macros. Les valeurs reconnues doivent être vérifiées avant émission.',
    'group_source' => 'Document source', 'group_target' => 'Cible et sortie', 'group_invoice' => 'Données de facture', 'group_einvoice' => 'Facture électronique',
    'file' => 'Fichier de facture', 'file_hint' => 'PDF, DOCX, DOC, XLSX ou XLS jusqu’à 20 Mo ; l’OCR est utilisé pour les scans PDF si disponible.', 'delivery_format' => 'Format de sortie préféré',
    'review_hint' => 'L’original reste inchangé dans la GED. Les données reconnues automatiquement sont des suggestions, pas une validation.',
    'format' => ['pdf' => 'PDF', 'xrechnung' => 'XRechnung (XML)', 'zugferd' => 'ZUGFeRD (PDF hybride)', 'pdf_xrechnung' => 'PDF et XRechnung (XML)'],
    'default_line' => 'Prestations selon la facture originale :number', 'source_title' => 'Fichier original de la facture :number', 'source_description' => 'Document source inchangé de l’import de facture.',
    'success' => 'Fichier lu et créé comme brouillon de facture. Vérifiez les données et les lignes.', 'options_title' => 'Données de facture électronique', 'options_action' => 'Données e-facture', 'options_saved' => 'Données de facture enregistrées.',
    'invoice_number' => 'Numéro de facture', 'currency' => 'Devise', 'issue_date' => 'Date de facture', 'due_date' => 'Échéance', 'buyer_reference' => 'Référence acheteur / identifiant d’acheminement',
    'buyer_reference_hint' => 'Remplace pour cette facture la référence du client.', 'buyer_reference_create_hint' => 'Facultatif par facture ; la fiche client est utilisée si vide.',
    'imported_notice' => 'Prérempli depuis un fichier de facture', 'imported_detail' => 'Score de reconnaissance : :confidence %. Vérifiez numéro, dates, montants, taxe et lignes avec l’original.', 'original' => 'Fichier original',
    'preferred_format' => 'Sortie préférée :', 'flexibility_hint' => 'PDF, XRechnung et ZUGFeRD restent disponibles séparément.', 'mail_hint' => 'Choisissez le format de la pièce jointe. Les brouillons sont émis automatiquement lors de l’envoi.',
    'error' => ['external_billing' => 'Une application externe gère la facturation de ce client. L’import local est verrouillé.', 'duplicate' => 'Ce fichier a déjà été importé.', 'no_text' => 'Aucune donnée de facture n’a pu être lue.', 'unsupported_format' => 'PDF, DOCX, DOC, XLSX et XLS sont pris en charge.', 'unreadable' => 'Le fichier est endommagé ou n’a pas pu être lu en toute sécurité.', 'proforma' => 'Les factures pro forma ne peuvent être envoyées qu’au format PDF.'],
    'warning' => ['missing_number' => 'Le numéro de facture n’a pas été reconnu de façon fiable.', 'missing_issued_on' => 'La date de facture n’a pas été reconnue de façon fiable.', 'missing_net' => 'Le montant net n’a pas été reconnu de façon fiable.', 'totals_mismatch' => 'Les montants net, taxe et brut reconnus sont incohérents.', 'duplicate_number' => 'Le numéro reconnu existe déjà ; un numéro local libre a été utilisé.'],
];

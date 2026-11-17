<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming_pdf.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// PDF groupé et GiroCode dans la réception des factures (Feature 208, MVP-1662/1663).
return [
    'split' => [
        'title' => 'Scinder le PDF groupé',
        'card_title' => 'PDF groupé',
        'gate' => 'PDF groupé : le scinder d’abord ou le confirmer comme une seule facture.',
        'part_description' => 'Partie de « :title », pages :from–:to',
        'document_title' => 'PDF groupé :file (:count factures)',
        'document_description' => 'Original inchangé, scindé en :count factures reçues.',
        'state' => [
            'pending' => 'Les pages sont en cours de vérification pour plusieurs factures. Le transfert et le paiement attendent jusque-là.',
            'proposed' => 'Le PDF contient probablement :count factures. Le transfert et le paiement attendent votre décision.',
            'single' => 'Les :pages pages appartiennent probablement à une seule facture. S’il y en a tout de même plusieurs, scindez-les à la main.',
            'dismissed' => 'Confirmé comme une seule facture.',
            'splitting' => 'La scission s’effectue en arrière-plan.',
            'too_large' => 'Plus de :max pages — pas de vérification de plusieurs factures.',
            'failed' => 'La vérification de plusieurs factures a échoué ; la réception compte comme une seule facture.',
        ],
        'action' => [
            'reset' => 'Réinitialiser la scission',
            'reset_confirm' => 'La scission dure anormalement longtemps. La réinitialiser et réessayer ?',
            'open' => 'Vérifier les limites',
            'split' => 'Scinder',
            'dismiss' => 'C’est une seule facture',
            'dismiss_confirm' => 'Conserver la réception comme une seule facture ? Le transfert et le paiement se poursuivent ensuite.',
        ],
        'dialog' => [
            'intro' => 'Marquez les pages sur lesquelles commence une nouvelle facture. Chaque facture devient une réception distincte avec son propre PDF partiel ; l’original reste inchangé.',
            'page' => 'Page :page',
            'starts_here' => 'Une nouvelle facture commence ici',
            'first_page' => 'Première facture',
            'no_image' => 'Pas d’aperçu',
            'images_missing' => 'Les images d’aperçu manquent — vous pouvez tout de même marquer les pages.',
            'signals' => 'Indices',
        ],
        'signal' => [
            'page_one' => '« Page 1 sur … »',
            'continued' => 'page de suite',
            'new_number' => 'nouveau numéro de facture',
            'same_number' => 'même numéro de facture',
            'new_sender' => 'nouvel expéditeur',
            'after_total' => 'après un bloc de totaux',
            'new_letterhead' => 'autre en-tête',
        ],
        'blocked' => [
            'split' => 'Cette réception a été scindée comme PDF groupé — ce sont ses parties qui sont traitées.',
            'part' => 'Cette réception est déjà une partie d’un PDF groupé scindé.',
            'no_bundle' => 'Aucune vérification de PDF groupé ouverte pour cette réception.',
            'pending' => 'Les pages sont encore en cours de vérification.',
            'running' => 'La scission est déjà en cours.',
            'too_large' => 'Le PDF est trop volumineux pour être scindé.',
            'status' => 'Seules les réceptions ouvertes (reçues ou avec question) peuvent être scindées.',
            'transferred' => 'La réception a déjà été transférée à la comptabilité — la scinder là-bas.',
            'payment' => 'La réception figure dans un lot de paiements.',
            'linked' => 'La réception comporte des retenues ou des liens (immobilisation, poste ouvert, recours, avoir).',
            'hold' => 'Une mesure de conservation s’applique à la réception.',
        ],
        'error' => [
            'too_many_parts' => 'Au plus :max factures par PDF groupé.',
            'parts_too_large' => 'Les PDF partiels seraient trop volumineux ensemble — veuillez scinder le fichier avant le téléversement.',
            'stalled' => 'La scission ne s’est pas terminée et a été réinitialisée.',
            'invalid_page' => 'Les numéros de page ne correspondent pas au document.',
            'single' => 'Marquez au moins une autre page de début — sinon cela reste une seule facture.',
            'original_missing' => 'L’original manque dans la gestion documentaire.',
            'original_changed' => 'L’original enregistré ne correspond plus au hachage de la réception — pas de scission.',
            'tool_missing' => 'Il manque un outil PDF pour extraire les pages (mutool, pdftk ou poppler).',
            'extract_failed' => 'Les pages :from–:to n’ont pas pu être extraites.',
            'nothing_created' => 'Toutes les parties existent déjà comme réceptions — rien n’a été scindé.',
            'failed' => 'La scission a échoué.',
            'original_protected' => 'L’original d’un PDF groupé est conservé tant qu’une de ses parties existe.',
        ],
        'flash' => [
            'reset' => 'Scission réinitialisée.',
            'done' => 'PDF groupé scindé en :count factures.',
            'queued' => 'Scission en :count factures lancée — les réceptions apparaîtront bientôt dans la liste.',
            'dismissed' => 'Conservé comme une seule facture.',
        ],
        'parts' => [
            'title' => 'Parties de ce PDF groupé',
            'intro' => 'L’original reste inchangé ; chaque partie est une réception distincte avec son propre PDF partiel.',
            'pages' => 'Pages :from–:to',
            'protected' => 'L’original ne peut pas être supprimé tant que des parties existent.',
        ],
        'part_of' => 'Partie d’un PDF groupé, pages :from–:to.',
        'open_original' => 'Ouvrir l’original',
    ],
    'girocode' => [
        'title' => 'GiroCode',
        'pending' => 'Le GiroCode est lu en arrière-plan.',
        'status' => [
            'found' => 'GiroCode lu (page :page).',
            'none' => 'Aucun GiroCode trouvé.',
            'unreadable' => 'GiroCode illisible.',
            'invalid' => 'Code QR avec identifiant GiroCode, mais contenu invalide.',
        ],
        'reason' => [
            'page_size' => 'Les dimensions de page sont illisibles ou trop grandes pour être rastérisées.',
            'changed' => 'L’original enregistré ne correspond plus à la réception.',
            'tool_missing' => 'L’outil de lecture (zbarimg) manque sur le serveur.',
            'too_large' => 'Le fichier est trop volumineux pour être lu.',
            'scan_failed' => 'Une erreur s’est produite lors de la lecture.',
        ],
        'field' => [
            'name' => 'Bénéficiaire',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'amount' => 'Montant',
            'reference' => 'Motif du paiement',
        ],
        'iban_applied' => 'L’IBAN du GiroCode a été repris, car le document n’en indiquait aucun.',
        'finding' => [
            'new_bank_details' => 'L’IBAN provient uniquement du GiroCode ; aucune coordonnée bancaire du fournisseur n’est enregistrée pour le comparer.',
            'iban_differs_invoice' => 'L’IBAN du GiroCode diffère de l’IBAN de la facture.',
            'iban_differs_supplier' => 'L’IBAN du GiroCode n’appartient à aucune coordonnée bancaire enregistrée du fournisseur.',
            'multiple_codes' => 'Le document porte plusieurs GiroCodes avec des IBAN différents.',
            'iban_invalid' => 'La clé de contrôle de l’IBAN du GiroCode est fausse — l’IBAN n’a pas été repris.',
            'amount_differs' => 'Le montant du GiroCode diffère du montant brut.',
            'name_differs' => 'Le nom du bénéficiaire du GiroCode ne correspond pas au fournisseur.',
            'unreadable' => 'GiroCode illisible.',
            'invalid' => 'GiroCode invalide.',
        ],
        'blocking' => 'Coordonnées bancaires différentes — une fraude connue. Bloqué dans le lot de paiements jusqu’à ce qu’une personne habilitée à valider confirme l’écart.',
        'confirmed' => 'Écart confirmé le :date.',
    ],
];

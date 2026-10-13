<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : numbering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vergabe von Kunden- und Lieferantennummern durch ein Buchhaltungsprogramm (Phase 141, MVP-1130–1134),
// Seite „Nummernkreise“ (Phase 144, MVP-1147–1149).
return [
    'source' => [
        'local' => 'workDiary',
        'inactive' => ':program (inactif – workDiary attribue)',
        'column' => 'Attribué par',
        'page_hint' => 'Si un logiciel de comptabilité attribue les numéros de client ou de fournisseur, un nouvel enregistrement reçoit son numéro lors du transfert. Les numéros existants restent inchangés. Les numéros de facture, d\'avoir et d\'annulation sont attribués par le logiciel de la voie de facturation, ou par Lexoffice si l\'option y est activée. Si un logiciel attribue les numéros, le format est verrouillé.',
        'billing' => ':program (voie de facturation)',
        'deviation' => ':program pour un client|:program pour :count clients',
    ],
    'field' => [
        'locked_hint' => ':program attribue le numéro lors du transfert.',
        'row' => ':field (:scope)',
    ],
    'separators' => [
        'prefix' => 'Séparateur après le préfixe, toutes les plages',
        'year' => 'Séparateur après l\'année, toutes les plages',
        'apply' => 'Appliquer à toutes les plages',
        'hint' => 'Applique les deux séparateurs à toutes les plages attribuées par workDiary et enregistre la page. Un champ vide signifie : aucun séparateur. Les numéros déjà attribués restent inchangés.',
    ],
    'preview' => [
        'program' => 'Défini par :program',
        'locked_hint' => 'Le format et le numéro sont définis par :program.',
        'next_remote' => 'Prochain dans :program : :number',
        'last_adopted' => 'Dernier de :program : :number',
        'pending' => 'Un contact attend son numéro.|:count contacts attendent leur numéro.',
    ],
    'issue' => [
        'no_issuer' => ':program ne peut pas transférer :scope. Choisissez sur la page « Plages de numéros » qui attribue le numéro.',
        'quote_pending' => ':program attribue le numéro du devis lors de l\'envoi ; d\'ici là, il porte un numéro provisoire.',
        'draft_number' => 'Ce document porte un numéro provisoire et :program ne peut pas le reprendre. Indiquez sur la page « Plages de numéros » qui attribue le numéro.',
        'transfer_failed' => 'Le transfert vers :program a échoué ; le document reste un brouillon. :error',
        'issue_first' => '« :document » porte encore un numéro provisoire. Émettez d\'abord le document – le logiciel de comptabilité attribue alors le numéro.',
        'invoice_pending' => ':program attribue le numéro à l\'émission ; WorkDiary transmet alors le document. D\'ici là, il porte un numéro provisoire.',
    ],
    'flash' => [
        'saved' => 'Une plage de numéros enregistrée.|:count plages de numéros enregistrées.',
        'unchanged' => 'Aucune modification.',
        'transfer_pending' => 'Document « :document » créé. À l\'émission, WorkDiary le transmet à :program, qui lui attribue aussi son numéro.',
        'issued' => 'Numéro :number repris de :program.',
        'pending' => 'Le numéro de :program suivra lors du prochain transfert : :reason',
        'conflict' => 'Le numéro :number de :program est déjà attribué à :holder. Vous trouverez le cas dans la boîte de rapprochement.',
    ],
    'conflict' => [
        'title' => 'Le numéro :number de :program est déjà attribué à :holder',
    ],
    'error' => [
        'taken_remote' => 'Le numéro :number est déjà attribué à « :name » dans :program.',
        'taken_local' => 'Le numéro :number est déjà attribué à :holder. Veuillez d\'abord y modifier le numéro.',
        'no_number' => ':program n\'a renvoyé aucun numéro.',
    ],
];

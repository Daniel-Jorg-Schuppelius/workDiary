<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'error' => [
        'sevdesk_not_configured' => 'sevDesk n\'est pas configuré pour cette organisation (jeton API manquant).',
        'sevdesk_outcome_unclear' => 'Résultat de la remise sevDesk incertain (délai dépassé après l\'envoi) — ne pas réessayer aveuglément ; le prochain passage rapproche via le marqueur source.',
    ],
    'sevdesk' => [
        'introduction' => 'Nous vous facturons comme suit nos livraisons et prestations pour la période du :from au :to.',
        'tax_text' => 'TVA :rate %',
    ],
];

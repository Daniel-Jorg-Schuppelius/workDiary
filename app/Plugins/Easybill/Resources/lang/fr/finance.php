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
    'easybill' => [
        'introduction' => 'Nous vous facturons comme suit nos livraisons et prestations pour la période du :from au :to.',
        'unit_hour' => 'h',
        'unit_piece' => 'pcs',
    ],
    'error' => [
        'easybill_not_configured' => 'easybill n\'est pas configuré pour cette organisation (clé API manquante).',
        'easybill_outcome_unclear' => 'Résultat du transfert easybill incertain (délai dépassé après l\'envoi) — ne pas réessayer à l\'aveugle ; la prochaine exécution réconcilie via le marqueur source.',
    ],
];

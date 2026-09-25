<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : rental.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'terms' => [
        'title' => 'Condizioni di noleggio',
        'signed' => 'Contratto :contract, versione :revision, firmato il :date',
        'missing' => 'Per questo cliente non esistono condizioni di noleggio firmate.',
        'missing_required' => 'Nessuna condizione di noleggio firmata: la consegna è possibile solo dopo.',
        'create_agreement' => 'Creare le condizioni di noleggio',
        'required' => 'La consegna richiede condizioni di noleggio firmate dal cliente (impostazione dell’organizzazione).',
    ],
];

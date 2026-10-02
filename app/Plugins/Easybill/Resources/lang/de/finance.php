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
        'introduction' => 'Unsere Lieferungen/Leistungen im Zeitraum :from – :to stellen wir Ihnen wie folgt in Rechnung.',
        'unit_hour' => 'Std.',
        'unit_piece' => 'Stk.',
    ],
    'error' => [
        'easybill_not_configured' => 'easybill ist für diese Organisation nicht konfiguriert (API-Key fehlt).',
        'easybill_outcome_unclear' => 'Ausgang der easybill-Übergabe unklar (Zeitüberschreitung nach dem Senden) — nicht blind wiederholen; der nächste Lauf gleicht über den Quellmarker ab.',
    ],
];

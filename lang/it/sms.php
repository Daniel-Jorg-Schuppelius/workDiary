<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sms.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// SMS-Kanal (Feature 147, MVP-730). Texte bewusst knapp: eine SMS trägt
// 160 Zeichen, und der Bestätigungstext darf nie zwei Segmente kosten.

return [
    'verification_code' => 'workDiary: il tuo codice di conferma è :code. È valido per 10 minuti.',
    'opt_in_hint' => 'Riceverai SMS di allarme solo dopo aver confermato il tuo numero di cellulare. Puoi revocare il consenso in qualsiasi momento.',
    'code_sent' => 'Un codice di conferma è stato inviato al tuo numero di cellulare.',
    'code_invalid' => 'Il codice non è corretto o è scaduto.',
    'opt_in_active' => 'Gli SMS di allarme sono attivi.',
    'opt_in_revoked' => 'Gli SMS di allarme sono disattivati.',
    'section' => 'SMS di allarme',
    'status_active' => 'Attivo — numero di cellulare confermato',
    'status_inactive' => 'Non attivo',
    'no_gateway' => 'Per questa organizzazione non è attivo alcun gateway SMS.',
    'no_mobile' => 'Inserisci prima un numero di cellulare.',
    'send_code' => 'Richiedi codice',
    'code' => 'Codice di conferma',
    'confirm' => 'Conferma',
    'revoke' => 'Revoca',
];

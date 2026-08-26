<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : construction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Comunicazioni VOB/B',
    'subtitle' => 'Denunce di impedimento e segnalazioni di riserve con prova di ricezione.',
    'empty' => 'Nessuna comunicazione registrata.',
    'dialog_hint' => 'I fatti sono il cuore della comunicazione: sintetici, verificabili e datati. I riferimenti normativi sono testo — WorkDiary non fornisce consulenza legale.',
    'disclaimer' => 'I riferimenti normativi sono blocchi di testo e non consulenza legale. Se un termine decorra o i tempi di esecuzione si prolunghino lo decidono le parti contrattuali.',

    'kind' => [
        'obstruction' => 'Denuncia di impedimento',
        'concern' => 'Segnalazione di riserve',
    ],

    'legal' => [
        'obstruction' => '§ 6 comma 1 VOB/B',
        'concern' => '§ 4 comma 3 VOB/B',
    ],

    'status' => [
        'draft' => 'Bozza',
        'sent' => 'Inviata',
        'acknowledged' => 'Ricezione confermata',
    ],

    'column' => [
        'number' => 'Numero',
        'kind' => 'Tipo',
        'subject' => 'Oggetto',
        'project' => 'Cantiere',
        'occurred_on' => 'Data',
        'status' => 'Stato',
    ],

    'filter' => [
        'kind' => 'Tipo',
        'status' => 'Stato',
    ],

    'field' => [
        'site' => 'Luogo di intervento',
        'customer' => 'Committente',
        'diary_entry' => 'Origine (voce di diario)',
        'recipient_name' => 'Destinatario',
        'recipient_email' => 'E-mail del destinatario',
        'facts' => 'Fatti',
        'facts_hint' => 'Che cosa impedisce esattamente i lavori o motiva la riserva? Causa, prestazione interessata, momento.',
        'impact_schedule' => 'Effetti sui tempi di esecuzione',
        'impact_cost' => 'Effetti sui costi',
        'claims_time_extension' => 'Richiesta proroga dei termini',
        'claims_time_extension_hint' => 'Solo un’annotazione sulla comunicazione — WorkDiary non sposta alcun termine.',
        'legal_reference' => 'Riferimento normativo',
        'legal_reference_hint' => 'Compare come testo nella comunicazione.',
        'acknowledged_note' => 'Annotazione sulla ricezione',
    ],

    'section' => [
        'context' => 'Assegnazione',
        'weather' => 'Meteo del giorno in questione',
        'delivery' => 'Prova di ricezione',
        'acknowledge' => 'Conferma di ricezione',
    ],

    'action' => [
        'edit' => 'Modifica',
        'pdf' => 'PDF',
        'send' => 'Invia',
        'acknowledge' => 'Conferma ricezione',
    ],

    'badge' => [
        'time_extension' => 'Richiesta proroga dei termini',
    ],

    'note' => [
        'time_extension' => 'Annotazione: è stata richiesta una proroga dei termini. I termini in WorkDiary restano invariati — una proroga ha effetto solo se concordata tra le parti e registrata qui.',
        'time_extension_short' => 'Una proroga richiesta è un’annotazione; WorkDiary non sposta i termini automaticamente.',
    ],

    'delivery' => [
        'none' => 'Nessuna prova di ricezione registrata.',
        'method' => 'Modalità di consegna',
        'method_registered_mail' => 'Raccomandata',
        'method_courier' => 'Corriere',
        'method_handover' => 'Consegna a mani',
        'method_fax' => 'Fax',
        'method_portal' => 'Portale gare/cantiere',
        'delivered_at' => 'Consegnata il',
        'recipient' => 'Destinatario',
        'reference' => 'Numero di ricevuta/spedizione',
        'record' => 'Registra consegna',
    ],

    'mail' => [
        'title' => 'Inviare :label :nr per e-mail',
    ],

    'pdf' => [
        'number' => 'Numero',
        'subject' => 'Oggetto',
        'occurred_on' => 'Data',
        'project' => 'Cantiere',
        'site' => 'Luogo di intervento',
        'legal_reference' => 'Riferimento normativo',
        'facts' => 'Fatti',
        'impact_schedule' => 'Effetti sui tempi di esecuzione',
        'impact_cost' => 'Effetti sui costi',
        'weather' => 'Meteo del giorno in questione',
        'weather_values' => 'Valori misurati',
        'weather_source' => 'Fonte',
        'time_extension' => 'Richiesta proroga dei termini',
        'time_extension_text' => 'Chiediamo una proroga del termine di esecuzione pari alla durata dell’impedimento.',
        'disclaimer' => 'La presente comunicazione richiama le disposizioni pertinenti come blocco di testo. Non sostituisce una verifica legale.',
    ],

    'error' => [
        'frozen' => 'Una comunicazione inviata è definitiva e non può più essere modificata.',
    ],

    'created' => 'Comunicazione creata.',
    'updated' => 'Comunicazione salvata.',
    'deleted' => 'Bozza eliminata.',
    'delivery_recorded' => 'Prova di ricezione registrata.',
    'acknowledged' => 'Ricezione confermata.',
];

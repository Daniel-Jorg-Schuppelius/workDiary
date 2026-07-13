<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : wage_types.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => "Voci retributive & consegna export",
        'index_subtitle' => "Mappare le voci retributive interne sui numeri di voce del programma paghe di destinazione e configurare la consegna automatica per profilo di export.",
        'mappings_help' => "Come funziona la mappatura delle voci retributive?",
        'mappings_help_text' => "Durante l'export dei tempi, la voce di ogni riga viene risolta prima tramite questa mappatura, poi tramite la voce della regola di maggiorazione; le ore normali senza mappatura mantengono la voce predefinita del profilo. Se una riga di maggiorazione o assenza non ha alcuna assegnazione, l'export si interrompe con un messaggio di errore invece di produrre un file errato.",
        'create' => "Crea mappatura voce retributiva",
        'edit' => "Modifica mappatura voce retributiva",
        'empty' => "Nessuna mappatura presente — restano attive le voci predefinite dei profili.",
        'delivery' => "Consegna automatica",
        'delivery_help_text' => "Gli export completati vengono consegnati automaticamente per profilo via e-mail e/o SFTP all'ufficio paghe; la prova (quando/dove) è registrata sull'export.",
        'delivery_edit' => "Configura consegna — :profile",
    ],

    'field' => [
        'basics' => "Mappatura",
        'profile' => "Profilo di export",
        'wage_type' => "Voce retributiva interna",
        'wage_type_help' => "Voci standard dell'export dei tempi più i tipi di maggiorazione della vostra organizzazione.",
        'external_code' => "Voce di destinazione (esterna)",
        'external_code_help' => "Numero di voce nel programma paghe di destinazione — numerico fino a 4 cifre per DATEV/Lexware.",
        'standard_types' => "Voci standard",
        'surcharge_types' => "Tipi di maggiorazione (organizzazione)",
        'choose' => "– selezionare –",
        'mail' => "Invio e-mail",
        'mail_toggle' => "Inviare il file di export via e-mail al completamento",
        'mail_recipients' => "Destinatari",
        'mail_recipients_help' => "Separare più indirizzi con virgola, punto e virgola o a capo.",
        'sftp' => "Caricamento SFTP",
        'sftp_toggle' => "Caricare il file di export via SFTP al completamento",
        'sftp_host' => "Host",
        'sftp_port' => "Porta",
        'sftp_username' => "Nome utente",
        'sftp_password' => "Password",
        'sftp_password_help' => "Lasciare vuoto per mantenere la password salvata.",
        'sftp_root' => "Directory di destinazione",
        'sftp_root_help' => "Vuoto = directory home dell'utente SFTP.",
        'enabled' => "Attivo",
        'disabled' => "Disattivato",
    ],

    'action' => [
        'create' => "Crea",
        'edit' => "Modifica",
        'save' => "Salva",
        'delete' => "Elimina",
        'delete_confirm' => "Eliminare davvero questa mappatura? Gli export esistenti restano invariati; i futuri export tornano alla voce predefinita.",
        'configure' => "Configura",
    ],

    'flash' => [
        'created' => "Mappatura voce retributiva creata.",
        'updated' => "Mappatura voce retributiva aggiornata.",
        'deleted' => "Mappatura voce retributiva eliminata.",
        'delivery_saved' => "Configurazione di consegna salvata.",
    ],

    'validation' => [
        'external_code_format' => "La voce di destinazione non ha un formato valido per il profilo di export scelto (DATEV/Lexware: numerico, 1–4 cifre).",
        'wage_type_unique' => "Esiste già una mappatura per questa voce in questo profilo.",
        'recipients_required' => "L'invio e-mail richiede almeno un indirizzo destinatario.",
        'password_required' => "Il caricamento SFTP richiede una password.",
    ],

    'error' => [
        'missing_mappings' => "Export interrotto: le seguenti voci retributive non hanno una voce di destinazione nel programma paghe: :types. Creare una mappatura in «Voci retributive & consegna export» o impostare la voce sulla regola di maggiorazione.",
    ],

    'delivery' => [
        'title_evidence' => "Consegna automatica",
        'evidence_mail' => "E-mail a :to",
        'evidence_sftp' => "SFTP verso :target",
        'note_auto' => "Consegnato automaticamente (:channels).",
        'file_missing' => "File di export non trovato — consegna saltata.",
        'abandoned' => "Consegna automatica definitivamente fallita dopo più tentativi.",
    ],

    'mail' => [
        'subject' => "Export tempi :profile :period",
        'heading' => "Export tempi per le paghe",
        'body' => "In allegato trovate l'export dei tempi del profilo :profile per il periodo :period.",
        'meta' => ":rows righe · SHA-256 :hash",
    ],
];

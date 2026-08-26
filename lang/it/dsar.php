<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dsar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'portal' => [
        'title' => 'Richiesta in materia di protezione dei dati',
        'subtitle' => 'I suoi diritti come interessato',
        'footer' => 'Questa pagina serve esclusivamente all’esercizio dei suoi diritti di interessato. Non inserisca dati di pagamento o credenziali di accesso.',
    ],

    'landing' => [
        'title' => 'Presentare una richiesta di protezione dei dati',
        'intro' => 'Con questa procedura gli interessati possono esercitare i diritti previsti dal Regolamento generale sulla protezione dei dati.',
        'no_link' => 'Per presentare una richiesta occorre il link del titolare del trattamento. Si rivolga all’organizzazione i cui dati la riguardano.',
        'rights' => 'Tipi di richiesta possibili',
    ],

    'legal_note' => 'Le indicazioni hanno carattere informativo e non costituiscono consulenza legale. Fa fede il testo di legge.',
    'privacy_notice' => 'I suoi dati vengono utilizzati esclusivamente per trattare questa richiesta, sono conservati cifrati e vengono cancellati alla scadenza del periodo di conservazione. La base giuridica è l’art. 6, par. 1, lett. c) del GDPR in combinato disposto con gli artt. da 15 a 21 del GDPR.',
    'identity_hint' => 'Prima di fornire informazioni il titolare verifica la sua identità (art. 12, par. 6 del GDPR) e può contattarla separatamente a tal fine.',

    'form' => [
        'title' => 'Presentare la richiesta',
        'what' => 'Di che cosa si tratta?',
        'what_text' => 'Può chiedere l’accesso ai dati che la riguardano, la loro rettifica o cancellazione, la limitazione del trattamento, la portabilità dei dati oppure opporsi al trattamento.',
        'submit' => 'Invia richiesta',
    ],

    'field' => [
        'type' => 'Tipo di richiesta',
        'full_name' => 'Nome e cognome',
        'email' => 'Indirizzo e-mail per la risposta',
        'reference' => 'Numero di pratica, cliente o personale (facoltativo)',
        'message' => 'La sua richiesta',
        'attachments' => 'Allegati (facoltativo)',
        'attachments_hint' => 'Al massimo :max file, ciascuno fino a :size MB.',
        'honeypot' => 'Non compilare',
        'privacy_ack' => 'Ho letto l’informativa sulla privacy e fornisco i dati secondo scienza e coscienza.',
    ],

    'receipt' => [
        'title' => 'Richiesta ricevuta',
        'headline' => 'La sua richiesta è stata ricevuta.',
        'number' => 'Numero di pratica: :nr',
        'mail_sent' => 'All’indirizzo indicato è stata inviata una conferma di ricezione. Il termine di legge decorre dalla ricezione odierna.',
        'back' => 'Torna al modulo',
    ],

    'confirmed' => [
        'title' => 'Indirizzo confermato',
        'headline' => 'Grazie — il suo indirizzo e-mail è confermato.',
        'text' => 'La conferma è stata annotata nella pratica :nr.',
        'no_deadline_effect' => 'Il termine di trattamento decorre invariato dalla ricezione della richiesta; la conferma non lo posticipa.',
    ],

    'mail' => [
        'subject' => 'Conferma di ricezione della sua richiesta di protezione dei dati :nr',
        'headline' => 'La sua richiesta di protezione dei dati è stata ricevuta',
        'intro' => 'Con questo indirizzo e-mail è stata presentata una richiesta di protezione dei dati con il numero di pratica :nr.',
        'deadline' => 'Il termine di legge decorre dalla ricezione e scade il :date.',
        'confirm_button' => 'Conferma indirizzo e-mail',
        'confirm_note' => 'La conferma attesta che questo indirizzo è raggiungibile. Non sostituisce la verifica della sua identità: il titolare la contatterà separatamente. Il clic non incide sul termine.',
        'not_you' => 'Se non ha presentato lei questa richiesta, ignori questa e-mail. Nessuna informazione viene fornita senza verifica dell’identità.',
    ],

    'subject' => [
        'email' => 'E-mail: :value',
        'reference' => 'Numero di pratica: :value',
    ],

    'internal' => [
        'from_portal' => 'Ingresso dal portale',
        'portal_banner' => 'Questa richiesta proviene dal portale pubblico per gli interessati. I dati identificativi sono un’autodichiarazione non verificata.',
        'contact_email' => 'Indirizzo per la risposta',
        'email_confirmed' => 'confermato il :date',
        'email_unconfirmed' => 'non confermato',
        'identity_required' => 'Prima di fornire informazioni l’identità deve essere verificata e confermata (ingresso dal portale).',
    ],

    'admin' => [
        'nav' => 'Portale interessati',
        'title' => 'Gestire il portale interessati',
        'subtitle' => 'Configurare il modulo pubblico per le richieste degli interessati.',
        'link' => 'Link pubblico',
        'link_hint' => 'Pubblichi questo link nella sua informativa sulla privacy. Non è deducibile dal nome dell’organizzazione.',
        'rotate' => 'Ruota il link',
        'rotate_confirm' => 'Ruotare davvero il link? I link già pubblicati diventeranno non validi.',
        'not_created' => 'Non è ancora stato creato alcun portale interessati. Salvi per crearne uno con un link casuale.',
        'settings' => 'Impostazioni',
        'visibility' => 'Visibilità',
        'is_enabled' => 'Portale attivo (raggiungibile pubblicamente)',
        'allow_attachments' => 'Consenti allegati',
        'presentation' => 'Presentazione',
        'intro_text' => 'Testo introduttivo (facoltativo)',
        'default_locale' => 'Lingua predefinita (facoltativa, ad es. it)',
        'saved' => 'Portale interessati salvato.',
        'rotated' => 'Il link del portale è stato ruotato. I link già pubblicati non sono più validi.',
    ],
];

<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : external.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'party' => [
        'subcontractor' => 'Subappaltatore',
        'inspector' => 'Ispettore',
        'expert' => 'Perito',
        'other' => 'Altro',
    ],
    'ability' => [
        'view' => 'Visualizzare',
        'comment' => 'Commentare',
        'upload' => 'Caricare file',
        'confirm' => 'Confermare',
    ],
    'status' => [
        'invited' => 'Invitato',
        'accessed' => 'Accesso effettuato',
        'expired' => 'Scaduto',
        'revoked' => 'Revocato',
    ],
    'subject' => [
        'order' => 'Commessa',
        'generic' => 'Elemento',
    ],
    'panel' => [
        'title' => 'Partecipanti esterni',
        'invite' => 'Invita',
        'empty' => 'Nessun partecipante esterno ancora invitato.',
        'link_once' => 'Copia questo link una sola volta e invialo al partecipante esterno — non verrà più mostrato.',
    ],
    'col' => [
        'name' => 'Nome',
        'party' => 'Tipo',
        'abilities' => 'Diritti',
        'status' => 'Stato',
        'expires' => 'Valido fino al',
    ],
    'group' => [
        'contact' => 'Contatto',
        'abilities' => 'Azioni consentite',
        'validity' => 'Validità',
    ],
    'field' => [
        'name' => 'Nome',
        'email' => 'E-mail (facoltativa)',
        'role' => 'Ruolo',
        'party' => 'Tipo',
        'ttl_days' => 'Validità (giorni)',
    ],
    'hint' => [
        'role' => 'es. Elettricista, ispettore TÜV',
        'abilities' => 'La visualizzazione è sempre consentita. Le azioni aggiuntive sono applicate rigorosamente lato server.',
        'ttl_days' => 'Da 1 a 180 giorni. L’accesso scade automaticamente in seguito.',
    ],
    'invite' => [
        'title' => 'Invita un partecipante esterno',
        'eyebrow' => 'Partecipanti esterni',
        'submit' => 'Invita e genera link',
        'once_hint' => 'Il link di accesso viene mostrato una sola volta dopo la creazione — viene memorizzato solo l’hash.',
    ],
    'revoke' => [
        'action' => 'Revoca',
        'title' => 'Revoca accesso',
        'message' => 'L’accesso esterno verrà bloccato immediatamente. Continuare?',
        'confirm' => 'Revoca',
    ],
    'flash' => [
        'invited_emailed' => "Partecipante esterno «:name» invitato — link di accesso inviato via e-mail.",
        'invited' => 'Partecipante esterno «:name» invitato.',
        'revoked' => 'Accesso esterno revocato.',
    ],
    'public' => [
        'title' => 'Accesso esterno',
        'hello' => 'Ciao :name',
        'expires_note' => 'Questo accesso è valido fino al :date.',
        'view_only' => 'Questo accesso è limitato alla sola visualizzazione.',
        'comment_heading' => 'Lascia un commento',
        'comment_placeholder' => 'La tua osservazione …',
        'comment_submit' => 'Invia commento',
        'comment_saved' => 'Commento salvato.',
        'upload_heading' => 'Carica file o foto',
        'upload_hint' => 'Consentiti: JPG, PNG, GIF, WEBP, PDF (max. 25 MB).',
        'upload_submit' => 'Carica',
        'upload_saved' => 'File caricato.',
        'upload_rejected' => 'Tipo di file non consentito.',
        'confirm_heading' => 'Conferma / Accettazione',
        'confirm_note_placeholder' => 'Osservazione facoltativa per la conferma …',
        'confirm_accept' => 'Confermo la correttezza dei dati.',
        'confirm_submit' => 'Conferma',
        'confirmed' => 'Conferma salvata.',
    ],

    // Rang 28 / Feature 023: Kontaktprofile + Einladungs-Mail (Paritäts-Nachzug).
    'mail' => [
        'subject' => "Il tuo accesso ai documenti condivisi",
        'heading' => "Accesso esterno",
        'intro' => "Ciao :name, sei stato invitato a documenti condivisi. Il link qui sotto ti dà accesso senza login:",
        'button' => "Apri l'accesso",
        'expires' => "L'accesso è valido fino al :date.",
        'note' => "Non condividere questo link — è personale e a tempo limitato.",
    ],
    'contact' => [
        'title' => "Profili di contatti esterni",
        'intro' => "Partecipanti esterni ricorrenti (subappaltatori, ispettori …) come anagrafica riutilizzabile.",
        'new' => "Nuovo profilo",
        'edit' => "Modifica profilo",
        'eyebrow' => "Profili di contatti esterni",
        'submit' => "Salva",
        'notes' => "Note",
        'delete' => "Elimina",
        'confirm_delete' => "Eliminare questo profilo di contatto? Gli inviti esistenti restano validi.",
        'empty' => "Ancora nessun profilo di contatto.",
        'pick' => "Scegli un profilo esistente (facoltativo)",
        'pick_none' => "— Inserisci nuovo —",
        'save_as' => "Salva questi dati come profilo riutilizzabile",
        'flash' => [
            'created' => "Profilo di contatto creato.",
            'updated' => "Profilo di contatto aggiornato.",
            'deleted' => "Profilo di contatto eliminato.",
        ],
    ],
];

<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : training.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'section' => 'Formazione',
    'nav' => [
        'courses' => 'Catalogo dei corsi',
        'requirements' => 'Matrice degli obblighi',
        'assignments' => 'Piano formativo',
    ],
    'title' => [
        'courses' => 'Catalogo dei corsi',
        'requirements' => 'Matrice degli obblighi',
        'assignments' => 'Piano formativo',
    ],
    'subtitle' => [
        'courses' => 'Corsi con ente, durata, validità e base giuridica — le attestazioni restano nel registro sicurezza.',
        'requirements' => 'Quale ruolo o area di attività deve quale corso; da qui nasce il piano per persona.',
        'assignments' => 'Chi deve quale corso ed entro quando — con l’attestazione derivante dalla formazione.',
    ],

    'field' => [
        'code' => 'Codice corso',
        'title' => 'Titolo',
        'provider_kind' => 'Ente',
        'provider_name' => 'Nome dell’ente',
        'duration_minutes' => 'Durata (minuti)',
        'validity_months' => 'Validità (mesi)',
        'is_mandatory' => 'Corso obbligatorio',
        'legal_basis' => 'Base giuridica',
        'cost' => 'Costo',
        'cost_amount' => 'Costo (informativo)',
        'cost_currency' => 'Valuta',
        'lead_days' => 'Preavviso (giorni)',
        'notes' => 'Note',
        'is_active' => 'Attivo',
        'course' => 'Corso',
        'version' => 'Versione del corso',
        'versions' => 'Versioni del corso',
        'version_label' => 'Etichetta della versione',
        'valid_from' => 'Valido dal',
        'content_summary' => 'Sintesi dei contenuti',
        'subject' => 'Destinatari',
        'subject_kind' => 'Tipo di destinatari',
        'subject_role' => 'Ruolo',
        'subject_team' => 'Area di attività (team)',
        'first_due_days' => 'Prima scadenza (giorni)',
        'user' => 'Persona',
        'due_at' => 'Scadenza',
        'fulfilled_at' => 'Attestato il',
        'proof' => 'Attestazione',
        'state' => 'Stato',
        'source' => 'Origine',
        'requirements_count' => 'Assegnazioni',
        'assignments_count' => 'Voci di piano',
    ],

    'action' => [
        'create_course' => 'Crea corso',
        'create_requirement' => 'Crea assegnazione',
        'create_assignment' => 'Crea voce di piano',
        'create_version' => 'Crea versione',
        'sync_assignments' => 'Aggiorna piano',
        'edit' => 'Modifica',
        'save' => 'Salva',
        'delete' => 'Elimina',
        'show' => 'Apri',
        'back' => 'Indietro',
    ],

    'filter' => [
        'all' => 'Tutti',
        'mandatory_only' => 'Solo obbligatori',
        'state' => 'Stato',
        'subject_kind' => 'Destinatari',
    ],

    'kpi' => [
        'mandatory' => 'Corsi obbligatori',
        'active_requirements' => 'Assegnazioni attive',
        'overdue' => 'Scaduti',
    ],

    'empty' => [
        'courses' => 'Nessun corso nel catalogo.',
        'versions' => 'Nessuna versione del corso creata.',
        'requirements' => 'Nessun obbligo assegnato.',
        'assignments' => 'Nessuna voce di piano formativo.',
    ],

    'hint' => [
        'cost_informational' => 'I costi sono solo informativi — non generano registrazioni né documenti.',
        'instruction_course' => 'Con il riferimento al corso questa partecipazione vale come attestazione per il piano formativo.',
        'no_second_guard' => 'Il piano formativo segnala e analizza; il blocco resta allo stato di qualifica.',
        'proof_in_register' => 'Le attestazioni vengono registrate esclusivamente come formazione nel registro sicurezza.',
        'sync' => 'L’aggiornamento crea le voci mancanti e rimuove quelle non più richieste e senza attestazione.',
    ],

    'confirm' => [
        'delete_course' => 'Eliminare il corso?',
        'delete_version' => 'Eliminare la versione?',
        'delete_requirement' => 'Eliminare l’assegnazione?',
        'delete_assignment' => 'Eliminare la voce di piano?',
    ],

    'flash' => [
        'course_created' => 'Corso creato.',
        'course_updated' => 'Corso aggiornato.',
        'course_deleted' => 'Corso eliminato.',
        'version_created' => 'Versione creata.',
        'version_deleted' => 'Versione eliminata.',
        'requirement_created' => 'Assegnazione creata.',
        'requirement_updated' => 'Assegnazione aggiornata.',
        'requirement_deleted' => 'Assegnazione eliminata.',
        'assignment_created' => 'Voce di piano creata.',
        'assignment_deleted' => 'Voce di piano eliminata.',
        'assignments_synced' => 'Piano aggiornato: :created aggiunte, :removed rimosse.',
    ],

    'error' => [
        'delete_with_proof' => 'Per questo corso esistono attestazioni — può essere solo disattivato.',
        'delete_last_version' => 'L’ultima versione del corso non può essere eliminata.',
        'delete_version_in_use' => 'Questa versione è attestata in una formazione e resta invariata.',
    ],

    'report' => [
        'title' => 'Analisi della formazione',
        'nav' => 'Formazione',
        'subtitle' => 'Grado di adempimento per team, ruolo e corso alla data di riferimento — base della prova di competenza.',
        'total' => 'Totale',
        'team' => 'Team',
        'role' => 'Ruolo',
        'course' => 'Corso',
        'no_team' => 'Senza team',
        'no_role' => 'Senza ruolo',
        'rate' => 'Grado di adempimento',
        'rate_by_team' => 'Grado di adempimento per team',
        'rate_by_course' => 'Grado di adempimento per corso',
        'by_team' => 'Per team',
        'by_role' => 'Per ruolo',
        'by_course' => 'Per corso',
        'kpi' => [
            'assignments' => 'Voci di piano',
            'fulfilled' => 'Adempiute',
            'due' => 'In scadenza',
            'overdue' => 'Scadute',
            'rate' => 'Grado di adempimento',
        ],
        'empty' => 'Nessuna voce di piano per il filtro selezionato.',
    ],
];

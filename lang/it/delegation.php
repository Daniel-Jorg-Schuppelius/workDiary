<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : delegation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vertretung bei Abwesenheit (Feature 202, MVP-1647).
return [
    'title' => 'Sostituzioni',
    'subtitle' => 'Chi sostituisce chi durante un’assenza. Il sostituto vede il lavoro assegnato nella coda di lavoro e ne riceve le notifiche, senza ottenere diritti aggiuntivi.',
    'create' => 'Crea sostituzione',
    'from_absence' => 'Crea sostituzione',
    'filter' => [
        'state' => 'Periodo',
        'current' => 'In corso e pianificate',
        'past' => 'Terminate e scadute',
        'all' => 'Tutta l’organizzazione',
    ],
    'empty' => [
        'title' => 'Nessuna sostituzione',
        'message' => 'Qui compaiono le sostituzioni a cui Lei partecipa.',
    ],
    'column' => [
        'represented' => 'Persona sostituita',
        'deputy' => 'Sostituto',
        'period' => 'Periodo',
        'state' => 'Stato',
        'work' => 'Assume',
    ],
    'open_end' => 'fino a nuovo avviso',
    'state' => [
        'active' => 'In corso',
        'upcoming' => 'Pianificata',
        'past' => 'Scaduta',
        'ended' => 'Terminata',
    ],
    'ended_by' => 'Terminata da :name il :date',
    'end' => 'Termina sostituzione',
    'end_confirm' => 'Terminare ora la sostituzione? Il lavoro tornerà solo alla persona sostituita.',
    'dialog' => [
        'title' => 'Crea sostituzione',
        'submit' => 'Crea sostituzione',
        'no_rights_hint' => 'Il sostituto vede e gestisce il lavoro assegnato e ne riceve le notifiche. Non ottiene diritti aggiuntivi né accesso a tempi, assenze o fascicolo personale.',
        'people' => 'Persone',
        'deputy_hint' => 'Precompilato con il sostituto designato della persona.',
        'choose_deputy' => 'Selezioni',
        'from' => 'Dal',
        'until' => 'Al',
        'until_hint' => 'Senza data di fine, la sostituzione vale finché qualcuno non la termina.',
        'kind' => 'Motivo',
        'note' => 'Nota',
        'work_heading' => 'Ciò che il sostituto assume da :name',
        'work_none' => 'Al momento nessun lavoro assegnato aperto',
        'work_hint' => 'La persona sostituita resta responsabile; dopo non c’è nulla da riassegnare.',
    ],
    'banner' => [
        'represents' => 'Lei sostituisce :names.',
        'represented_by' => 'Sostituito da :names.',
        'person_until' => ':name (fino al :date)',
        'work_queue' => 'Alla coda di lavoro',
        'manage' => 'Sostituzioni',
    ],
    'notification' => [
        'on_behalf_of' => 'In sostituzione di :name:',
        'created' => 'Lei sostituisce :name dal :from al :until.',
        'created_open' => 'Lei sostituisce :name dal :from fino a nuovo avviso.',
    ],
    'flash' => [
        'created' => 'Sostituzione da parte di :name creata.',
        'ended' => 'Sostituzione terminata.',
    ],
    'error' => [
        'past_start' => 'Una sostituzione inizia al più presto oggi.',
        'end_before_start' => 'La fine è precedente all’inizio.',
        'absence_mismatch' => 'L’assenza non appartiene a questa persona o non è approvata.',
        'overlap' => ':name ha già una sostituzione in questo periodo.',
        'deputy_absent' => ':name è a sua volta sostituito in questo periodo e non può sostituire.',
        'represented_is_deputy' => ':name sostituisce già qualcuno in questo periodo; non esistono sostituzioni a catena.',
        'already_ended' => 'Questa sostituzione è già terminata.',
        'foreign_organization' => 'Le sostituzioni sono possibili solo all’interno della propria organizzazione.',
        'self' => 'Una persona non può sostituire sé stessa.',
        'deactivated' => 'Gli account disattivati non possono sostituire né essere sostituiti.',
    ],
    // Passaggio definitivo senza uscita e selezione multipla nell'elenco ordini (MVP-1648).
    'handover' => [
        'action' => 'Passare le responsabilità',
        // Personenwahl auf der Seite „Vertretungen“ (Einstieg ohne Mitgliederliste).
        'choose_title' => 'Di chi vuole passare le responsabilità?',
        'choose_hint' => 'Scelga la persona il cui lavoro passa in modo permanente ai successori. Poi sceglierà il successore per ogni area.',
        'choose_empty' => 'Non c\'è nessuna persona di cui possa passare il lavoro.',
        'title' => 'Passare le responsabilità di :name',
        'intro' => 'Passaggio definitivo senza uscita: le aree scelte passano in modo permanente al successore; la persona resta membro. Per un’assenza temporanea la strada giusta è una sostituzione.',
        'areas' => 'Aree e successore',
        'hint' => 'Un successore per area oppure nessuno. Viene passato solo ciò che al salvataggio è aperto e assegnato alla persona; il numero per area viene registrato.',
        'submit' => 'Passare definitivamente',
        'empty' => 'Al momento non è assegnato a questa persona nulla che si possa passare.',
        'single_records' => 'I singoli ordini si passano nell’elenco ordini con la selezione «Cambia responsabile».',
        'help' => 'Guida',
        'flash' => '{0} Nulla è stato passato.|{1} Un elemento di :name passato.|[2,*] :count elementi di :name passati.',
        'area' => [
            'assigned_orders' => '{1} Un ordine assegnato|[2,*] :count ordini assegnati',
            'assigned_tasks' => '{1} Un’attività aperta|[2,*] :count attività aperte',
            'assigned_service_tickets' => '{1} Un ticket di assistenza assegnato|[2,*] :count ticket di assistenza assegnati',
            'assigned_procedure_runs' => '{1} Un’esecuzione di procedura in corso|[2,*] :count esecuzioni di procedura in corso',
        ],
        'error' => [
            'inactive' => 'Si passa solo il lavoro di membri attivi; per un’uscita vale la finestra di uscita.',
            'successor' => 'Il successore deve essere un altro membro attivo della stessa organizzazione.',
            'none' => 'Scegliere un successore per almeno un’area.',
        ],
    ],
    'reassign' => [
        'selected' => ':n ordini selezionati',
        'choose' => 'Scegli il nuovo responsabile…',
        'submit' => 'Cambia responsabile',
        'select_entry' => 'Seleziona l’ordine «:title»',
        'unknown_person' => 'Questa persona non esiste nella Sua organizzazione o è disattivata.',
        'done' => '{0} Nessun responsabile modificato.|{1} Un ordine ora è presso :name.|[2,*] :count ordini ora sono presso :name.',
        'skipped' => '{1} Un ordine è stato saltato (nessun diritto di modifica, già responsabile o qualifica mancante).|[2,*] :count ordini sono stati saltati (nessun diritto di modifica, già responsabile o qualifica mancante).',
    ],
    'demo' => [
        'note' => 'Esempio: sostituzione durante un corso di formazione.',
    ],
];

<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recruiting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Recruiting-Ergänzungen (MVP-924/925).
return [
    'suitability' => [
        'title' => 'Matrice di idoneità',
        'subtitle' => 'Competenze richieste per «:title» e valutazione delle candidature — un aiuto alla selezione, non una decisione automatica.',
        'requirements' => 'Competenze richieste',
        'no_requirements' => 'Nessun requisito definito finora.',
        'no_competencies' => 'Non esistono ancora competenze (catalogo della piattaforma di apprendimento).',
        'competency' => 'Competenza',
        'level' => 'Livello',
        'add' => 'Aggiungi requisito',
        'remove' => 'Rimuovi',
        'confirm_remove' => 'Rimuovere questa competenza richiesta?',
        'required' => 'Richiesto :level',
        'matrix' => 'Candidature',
        'candidate' => 'Candidato',
        'gaps' => 'Lacune',
        'score' => 'Copertura',
        'no_applications' => 'Nessuna candidatura per questa posizione.',
        'rating_title' => 'Valutazione delle competenze',
        'note' => 'Motivazione (interna)',
        'save' => 'Salva',
        'flash' => [
            'requirement' => 'Requisito salvato.',
            'requirement_removed' => 'Requisito rimosso.',
            'rated' => 'Valutazione salvata.',
        ],
    ],
    'offer' => [
        'title' => 'Proporre date',
        'slot' => 'Data :n',
        'mode' => 'Tipo di colloquio',
        'duration' => 'Durata (minuti)',
        'valid_days' => 'Valido (giorni)',
        'interviewer' => 'Intervistatore',
        'send' => 'Invia invito',
        'ics_title' => 'Colloquio di lavoro',
        'public_title' => 'Scegliere la data del colloquio',
        'public_intro' => 'Scelga una data (:minutes minuti, :mode).',
        'choose' => 'Date proposte',
        'confirm' => 'Conferma questa data',
        'confirmed_title' => 'Data confermata',
        'confirmed_text' => 'Attendiamo il colloquio del :when con :org. È in arrivo una conferma con l\'evento di calendario.',
        'flash' => [
            'sent' => 'Invito con :count date inviato.',
        ],
        'error' => [
            'no_email' => 'Questa candidatura non ha un indirizzo e-mail valido.',
            'no_slots' => 'Indichi almeno una data futura.',
            'unavailable' => 'Questa data non è più disponibile.',
        ],
        'mail' => [
            'subject' => 'Il suo colloquio: scelga una data (:title)',
            'body' => "Gentile :name,\n\nsaremmo lieti di conoscerla. Scelga entro il :until una delle seguenti date:\n:slots\n\nScelga la data: :url",
            'confirmed_subject' => 'Conferma del suo colloquio',
            'confirmed_body' => "Gentile :name,\n\nil suo colloquio si terrà il :when (:mode). In allegato trova l'appuntamento come evento di calendario.",
        ],
    ],
];

<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : isms.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'section' => 'SGSI',
        'risks' => 'Registro dei rischi',
        'controls' => 'Catalogo delle misure',
        'soa' => 'DdA',
    ],

    'subtitle' => [
        'risks' => 'Identificare, valutare (5×5) e trattare i rischi per la sicurezza delle informazioni.',
        'controls' => 'Gestire le misure e documentare la dichiarazione di applicabilità per ciascuna misura.',
    ],

    'field' => [
        'risk_no' => 'N.',
        'title' => 'Titolo',
        'description' => 'Descrizione',
        'category' => 'Categoria',
        'asset_ref' => 'Riferimento (sistema/processo/sede)',
        'threat' => 'Minaccia',
        'likelihood' => 'Probabilità',
        'impact' => 'Impatto',
        'score' => 'Punteggio',
        'treatment' => 'Trattamento',
        'status' => 'Stato',
        'owner' => 'Responsabile',
        'review_due_on' => 'Riesame previsto',
        'controls' => 'Misure collegate',
        'code' => 'Codice',
        'source' => 'Origine',
        'applicable' => 'Applicabile',
        'justification' => 'Giustificazione',
        'implementation_status' => 'Stato di attuazione',
        'evidence_note' => 'Nota di evidenza',
        'risks' => 'Rischi collegati',
    ],

    'group' => [
        'risk' => 'Rischio',
        'assessment' => 'Valutazione e trattamento',
        'control' => 'Misura',
        'soa' => 'Dichiarazione di applicabilità',
    ],

    'action' => [
        'create_risk' => 'Aggiungi rischio',
        'edit_risk' => 'Modifica rischio',
        'create_control' => 'Aggiungi misura',
        'edit_control' => 'Modifica misura',
        'edit' => 'Modifica',
        'save' => 'Salva',
        'delete' => 'Elimina',
        'transition' => 'Cambia stato',
        'import_catalog' => 'Carica catalogo Allegato A',
        'back' => 'Indietro',
        'print' => 'Stampa / salva PDF',
    ],

    'filter' => [
        'all' => 'Tutti',
        'sort' => 'Ordinamento',
        'sort_score' => 'Punteggio più alto prima',
        'sort_review' => 'Data di riesame',
        'sort_newest' => 'Più recenti prima',
        'applicable_yes' => 'Applicabile',
        'applicable_no' => 'Non applicabile',
    ],

    'scale' => [
        'likelihood' => [
            1 => 'molto raro',
            2 => 'raro',
            3 => 'possibile',
            4 => 'probabile',
            5 => 'molto probabile',
        ],
        'impact' => [
            1 => 'trascurabile',
            2 => 'lieve',
            3 => 'rilevante',
            4 => 'grave',
            5 => 'critico',
        ],
    ],

    'matrix' => [
        'title' => 'Matrice dei rischi (rischi aperti)',
        'cell' => 'Probabilità :likelihood × impatto :impact — :count rischio/i',
        'axes' => 'Righe: probabilità (1–5) · Colonne: impatto (1–5)',
        'legend' => 'Legenda',
        'low' => 'Basso (punteggio ≤ 6)',
        'medium' => 'Medio (punteggio 7–12)',
        'high' => 'Alto (punteggio > 12)',
        'review_due' => '{1} 1 riesame in scadenza|[2,*] :count riesami in scadenza',
    ],

    'hint' => [
        'asset_ref' => 'es. sistema ERP, sala server, data center …',
        'threat' => 'Quale minaccia/vulnerabilità è alla base?',
        'controls' => 'Selezione multipla (tenere premuto Ctrl/Cmd)',
        'no_controls_yet' => 'Nessuna misura presente — caricare prima il catalogo Allegato A o creare misure proprie.',
        'code' => 'es. M-01 (misura propria)',
        'justification' => 'obbligatoria se non applicabile',
        'evidence_note' => 'Riferimento a evidenza/documento',
    ],

    'flash' => [
        'risk_created' => 'Il rischio è stato aggiunto.',
        'risk_updated' => 'Il rischio è stato aggiornato.',
        'risk_transitioned' => 'Lo stato del rischio è stato modificato.',
        'risk_deleted' => 'Il rischio è stato eliminato.',
        'control_created' => 'La misura è stata aggiunta.',
        'control_updated' => 'La misura è stata aggiornata.',
        'control_deleted' => 'La misura è stata eliminata.',
        'catalog_imported' => 'Catalogo Allegato A caricato (:count nuove misure).',
    ],

    'error' => [
        'invalid_transition' => 'Il passaggio di stato da ":from" a ":to" non è consentito.',
        'justification_required' => 'Per le misure non applicabili è richiesta una giustificazione nella DdA.',
    ],

    'soa' => [
        'document_title' => 'Dichiarazione di applicabilità',
        'heading' => 'Dichiarazione di applicabilità (DdA)',
        'generated_at' => 'Aggiornato al',
        'control_count' => ':count misure',
        'yes' => 'Sì',
        'no' => 'No',
        'disclaimer' => 'Riferimento: ISO/IEC 27001:2022 Allegato A (solo codici e titoli brevi propri — nessun testo normativo). La valutazione di conformità spetta a un organismo di certificazione indipendente.',
    ],

    'empty_risks' => 'Nessun rischio registrato finora.',
    'empty_risks_title' => 'Nessun rischio trovato',
    'empty_controls' => 'Nessuna misura presente.',
    'empty_controls_title' => 'Nessuna misura trovata',
    'empty_controls_hint_catalog' => 'Nessuna misura presente — usare «Carica catalogo Allegato A» per importare il catalogo di riferimento ISO/IEC 27001 (93 misure).',
    'empty_controls_linked' => 'Nessuna misura collegata.',
    'empty_filtered' => 'Nessuna voce trovata per i filtri attuali.',
    'confirm_delete_risk' => 'Eliminare davvero questo rischio?',
    'confirm_delete_control' => 'Eliminare davvero questa misura? I collegamenti ai rischi verranno rimossi.',
    'confirm_import_catalog' => 'Caricare il catalogo di riferimento ISO/IEC 27001:2022 Allegato A (93 misure, solo codice + titolo breve) in questa organizzazione? Le misure esistenti restano invariate.',
];

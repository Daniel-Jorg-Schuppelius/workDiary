<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_round.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfmittelrunden (Feature 075, MVP-899).
return [
    'nav' => 'Giri di verifica',
    'title' => 'Giri di verifica',
    'subtitle' => 'Elenco delle verifiche in scadenza di una sede o di un gruppo — scansioni l\'oggetto e registri il risultato.',
    'open' => 'Crea giro',
    'show' => 'Apri giro',
    'name' => 'Denominazione',
    'due_until' => 'Scadenza entro',
    'progress' => 'Eseguite',
    'status' => 'Stato',
    'none_title' => 'Ancora nessun giro di verifica',
    'none' => 'Crei un giro per una sede o un gruppo.',
    'location' => 'Sede',
    'category' => 'Gruppo (categoria)',
    'profile' => 'Profilo di verifica',
    'customer' => 'Cliente',
    'any' => 'Tutti',
    'form_hint' => 'Il giro include tutti gli obblighi di verifica attivi in scadenza entro la data. Quelli in scadenza successiva non vengono aggiunti.',
    'empty' => 'Per questa selezione nessuna verifica scade entro la data.',
    'opened' => 'Giro creato con :count verifiche.',
    'scan' => 'Scansioni l\'oggetto',
    'scan_submit' => 'Apri',
    'scan_unknown' => 'Codice oggetto sconosciuto.',
    'scan_not_in_round' => '«:asset» non fa parte di questo giro.',
    'scan_done' => 'Tutte le verifiche di questo oggetto sono eseguite nel giro.',
    'scan_several' => 'Per questo oggetto sono aperte più verifiche:',
    'kpi_done' => 'Eseguite',
    'kpi_missing' => 'Mancanti',
    'kpi_overdue' => 'Scadute',
    'asset' => 'Oggetto',
    'due_on' => 'Scadenza',
    'overdue' => 'Scaduta',
    'pending' => 'Aperta',
    'capture' => 'Registra verifica',
    'capture_submit' => 'Salva verifica',
    'result' => 'Risultato',
    'note' => 'Nota',
    'signature_name' => 'Firma (nome)',
    'certificate_hint' => 'Questo profilo richiede un certificato per «superata». In tal caso registri la verifica nel calendario delle verifiche.',
    'recorded' => 'Verifica documentata.',
    'close' => 'Chiudi giro',
    'confirm_close' => 'Chiudere il giro? :missing verifiche sono ancora aperte e restano mancanti.',
    'closed' => 'Il giro è chiuso.',
    'closed_flash' => 'Giro chiuso.',
    'already_done' => 'Questa verifica è già registrata nel giro.',
];

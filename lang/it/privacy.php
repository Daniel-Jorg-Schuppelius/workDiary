<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : privacy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'dsr_extension' => [
        'closed' => 'Il termine può essere prorogato solo per una richiesta aperta.',
        'first_deadline_passed' => 'Il primo termine è scaduto. La proroga deve essere comunicata alla persona entro tale termine e non è più possibile.',
        'not_later' => 'Il nuovo termine deve essere successivo a quello attuale.',
        'too_late' => 'Il termine può essere prorogato al massimo di altri due mesi, cioè fino al :date.',
    ],
    'retention_area' => [
        'privacy_incidents' => 'Violazioni dei dati (chiuse)',
        'incoming_invoices_rejected' => 'Fatture in entrata rifiutate',
        'audit_logs' => 'Registro di audit',
        'exports' => 'Esportazioni paghe e tempi',
        'gobd_financial' => 'Dati rilevanti ai fini fiscali',
        'claims' => 'Reclami (chiusi)',
        'location_trip_suggestions' => 'Suggerimenti di tragitto dalla traccia di posizione (evasi)',
        'location_time_suggestions' => 'Suggerimenti di tempo dalla traccia di posizione (evasi)',
        'appointment_requests' => 'Richieste di appuntamento (decise)',
        'marketplace_buyers' => 'Dati acquirenti degli ordini marketplace',
        'leads' => 'Lead (non convertiti)',
        'applications' => 'Candidature (respinte/ritirate)',
        'privacy_requests' => 'Richieste degli interessati (chiuse)',
        'cti_calls' => 'Metadati delle chiamate CTI',
        'idea_maps' => 'Mappe delle idee (cestino)',
        'dictations' => 'Dettature vocali (trascrizioni)',
        'problem_reports' => 'Segnalazioni di problemi (chiuse)',
        'driver_license_checks' => 'Controlli della patente',
        'documents_invoice' => 'Fatture (DMS)',
        'passenger_rides' => 'Fascicoli delle corse (dati di luogo e passeggeri)',
        'remote_pending_sessions' => 'Sessioni di assistenza remota (importate/scartate)',
        'employee_records' => 'Anagrafica del personale (dipendenti cessati)',
        'portal_accounts' => 'Accessi al portale (disattivati)',
        'personnel_files' => 'Fascicoli del personale (dipendenti cessati)',
        'customer_master' => 'Anagrafica clienti (senza operazioni)',
        'customer_intakes' => 'Richieste dei clienti (respinte o ritirate)',
        'time_records' => 'Dati grezzi dell’orario di lavoro (presenze)',
        'location_points' => 'Dati grezzi di posizione (punti GPS)',
        'documents_general' => 'Documenti (senza rilevanza fiscale o commerciale)',
        'learning_records' => 'Piattaforma di apprendimento (iscrizioni, tempo di studio, tentativi)',
        'learning_certificates' => 'Certificati della piattaforma di apprendimento (pseudonimizzazione)',
    ],

    'requirement' => [
        'avv_required' => 'DPA con il responsabile del trattamento',
        'avv_current' => 'DPA valido (non scaduto)',
        'gvv_required' => 'Accordo di contitolarità con il contitolare',
        'dpia_required' => 'DPIA in caso di DPIA necessaria',
        'tom_assigned' => 'TOM per attività di trattamento',
        'tom_proof_current' => 'Prove TOM valide (non scadute)',
    ],
    // Datenkategorien der Datenschutz-Übersicht (Schlüssel == config/privacy.php categories.*.code).
    'category' => [
        'employees' => ['label' => 'Collaboratori', 'retention' => 'fino alla fine del contratto', 'delete_path' => 'Amministratore dell’organizzazione → Membri'],
        'working_time' => ['label' => 'Orario di lavoro', 'retention' => '10 anni (GoBD)', 'delete_path' => 'bloccato dopo la chiusura, non eliminabile'],
        'absences' => ['label' => 'Assenze retributive', 'retention' => 'secondo contratto collettivo/legge', 'delete_path' => 'Amministratore dell’organizzazione dopo il termine'],
        'diary' => ['label' => 'Registro ordini', 'retention' => '5 anni (configurabile)', 'delete_path' => 'Amministratore dell’organizzazione'],
        'tours' => ['label' => 'Giri / sedi', 'retention' => '2 anni (proposta)', 'delete_path' => 'cancellazione automatica'],
        'expenses' => ['label' => 'Spese / costi di viaggio', 'retention' => '10 anni (GoBD)', 'delete_path' => 'bloccato, archiviato'],
        'customers' => ['label' => 'Anagrafica clienti', 'retention' => 'fino alla fine del rapporto commerciale + termine', 'delete_path' => 'Amministratore dell’organizzazione'],
        'attachments' => ['label' => 'Allegati', 'retention' => 'con il record principale', 'delete_path' => 'insieme al record principale'],
        'signatures' => ['label' => 'Firme', 'retention' => 'come l’ordine', 'delete_path' => 'insieme all’ordine'],
        'qualifications' => ['label' => 'Qualifiche', 'retention' => 'fino alla fine del contratto', 'delete_path' => 'Amministratore dell’organizzazione'],
        'push' => ['label' => 'Abbonamenti push', 'retention' => 'alla disiscrizione', 'delete_path' => 'automaticamente al logout/alla pulizia'],
        'audit' => ['label' => 'Registro di audit', 'retention' => '24 mesi (proposta)', 'delete_path' => 'processo di rotazione del sistema'],
    ],
    'retention_years' => ':years anni',
    // Befunde der Lückenanalyse (ComplianceAnalysisService, gespeichert in der Sprache der Organisation).
    'gap' => [
        'avv_missing' => 'Responsabile del trattamento «:name» senza accordo sul trattamento dei dati',
        'avv_expiring' => 'L’accordo sul trattamento dei dati «:name» sta per scadere o è scaduto',
        'gvv_missing' => 'Contitolare «:name» senza accordo di contitolarità',
        'dpia_missing' => '«:name» richiede una DPIA ma non ha una DPIA conclusa',
        'tom_missing' => '«:name» senza misure tecniche e organizzative assegnate',
        'tom_proof_expiring' => 'Le prove delle misure tecniche e organizzative scadono o sono scadute: :names',
    ],
    'legal_region' => [
        'label' => 'Ambito giuridico',
        'hint' => 'Determina i termini di conservazione delle proposte di eliminazione e le indicazioni della documentazione delle procedure. Una modifica vale per le future proposte di eliminazione.',
    ],
    'tom' => [
        'implementation_status' => 'Stato di attuazione',
        'protection_level' => 'Esigenza di protezione',
        'protection_level_none' => '— non definita —',
        'state_save' => 'Salva stato',
        'state_saved' => 'Stato di attuazione ed esigenza di protezione salvati.',
    ],
    'retention' => [
        'years_label' => 'Periodo di conservazione per :area in anni',
        'years_unit' => 'anni',
        'years_default' => 'Vuoto = valore predefinito della giurisdizione (:years anni)',
        'years_own' => 'periodo proprio dell\'organizzazione',
        'years_saved' => 'Periodo di conservazione per «:area» salvato.',
    ],
];

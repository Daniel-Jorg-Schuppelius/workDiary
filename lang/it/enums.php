<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : enums.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'billbee' => [
        'order_state' => [
            1 => 'Ordinato',
            2 => 'Confermato',
            3 => 'Pagato',
            4 => 'Spedito',
            5 => 'Reclamo',
            6 => 'Eliminato',
            7 => 'Completato',
            8 => 'Annullato',
            9 => 'Archiviato',
            11 => '1º sollecito',
            12 => '2º sollecito',
            13 => 'Imballato',
            14 => 'Offerto',
            15 => 'Promemoria di pagamento',
            16 => 'In fulfillment',
        ],
    ],
    'ai' => [
        'family' => ['llm' => 'Modello linguistico (LLM)', 'translation' => 'Traduzione'],
        'verb' => ['formulate' => 'Formulare', 'summarize' => 'Riassumere', 'classify' => 'Classificare', 'explain' => 'Spiegare', 'find' => 'Trovare', 'translate' => 'Tradurre', 'extract' => 'Estrarre'],
        'provider' => ['anthropic' => 'Anthropic Claude', 'openai' => 'OpenAI', 'gemini' => 'Google Gemini', 'azure_openai' => 'Azure OpenAI', 'openai_compatible' => 'Compatibile OpenAI (generico)', 'ollama' => 'Ollama (locale)', 'deepl' => 'DeepL', 'azure_translator' => 'Azure Translator', 'google_translate' => 'Google Cloud Translation', 'libretranslate' => 'LibreTranslate (locale)', 'fake' => 'Provider di test'],
        'connection_status' => ['draft' => 'Bozza', 'active' => 'Attivo', 'blocked' => 'Bloccato'],
        'memory_type' => ['glossary' => 'Glossario', 'style_rule' => 'Regola di stile', 'example' => 'Coppia di esempio'],
        'sensitivity' => ['low' => 'Bassa', 'medium' => 'Media', 'high' => 'Alta'],
    ],
    'domain' => [
        'environment' => ['ote' => 'OT&E (test/pilota)', 'production' => 'Produzione'],
        'connection_status' => ['draft' => 'Bozza', 'active' => 'Attivo', 'blocked' => 'Bloccato'],
        'sync_status' => ['current' => 'Aggiornato', 'stale' => 'Obsoleto', 'pending' => 'In sospeso', 'conflict' => 'Conflitto', 'unknown' => 'Incerto'],
        'renewal_mode' => ['autorenew' => 'Rinnovo automatico', 'autoexpire' => 'Scadenza automatica', 'autodelete' => 'Eliminazione automatica', 'renewonce' => 'Rinnova una volta'],
        'command_status' => ['draft' => 'Bozza', 'approved' => 'Approvato', 'pending' => 'In sospeso', 'confirmed' => 'Confermato', 'failed' => 'Fallito', 'unknown' => 'Incerto', 'conflict' => 'Conflitto'],
        'capability_area' => ['authentication' => 'Autenticazione', 'subuser' => 'Sottoutente', 'domains' => 'Domini', 'contacts' => 'Contatti', 'nameservers' => 'Nameserver', 'dns' => 'Zone DNS', 'events' => 'Eventi', 'renewal' => 'Rinnovo', 'transfer' => 'Trasferimento', 'accounting' => 'Contabilità', 'invoices' => 'Fatture'],
    ],
    'asset' => [
        'defect-severity' => [
            'low' => 'Bassa',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Critica',
        ],
        'defect-status' => [
            'open' => 'Aperto',
            'inRepair' => 'In riparazione',
            'resolved' => 'Risolto',
            'writtenOff' => 'Dismesso',
        ],
        'ownership' => [
            'org' => 'Organizzazione',
            'customer' => 'Cliente',
            'external' => 'Esterno',
        ],
    ],
    'classification' => [
        'requirement-phase' => [
            'onCreate' => 'Alla creazione',
            'beforeComplete' => 'Prima del completamento',
            'beforeSign' => 'Prima della firma',
        ],
        'requirement-severity' => [
            'hard' => 'Bloccante',
            'soft' => 'Avviso',
        ],
    ],
    'room_requirement_kind' => [
        'hygieneLevel' => 'Livello di igiene',
        'specialCleaning' => 'Pulizia speciale',
        'accessRestriction' => 'Restrizione di accesso',
        'itInventory' => 'Inventario IT',
        'technicalInspection' => 'Verifica tecnica',
        'operatorDuty' => 'Obbligo del gestore',
        'other' => 'Altro',
    ],
    'event' => [
        'type' => [
            'training' => 'Formazione',
            'workshop' => 'Workshop',
            'conference' => 'Conferenza',
            'meeting' => 'Riunione',
            'internal_briefing' => 'Briefing interno',
            'external_visit' => 'Visita esterna',
        ],
        'status' => [
            'planned' => 'Pianificato',
            'confirmed' => 'Confermato',
            'in_progress' => 'In corso',
            'completed' => 'Completato',
            'cancelled' => 'Annullato',
        ],
        'visibility' => [
            'internal' => 'Interno',
            'external' => 'Esterno',
            'public' => 'Pubblico',
        ],
        'participant' => [
            'role' => [
                'organizer' => 'Organizzatore',
                'trainer' => 'Formatore',
                'attendee' => 'Partecipante',
                'optional' => 'Facoltativo',
            ],
            'status' => [
                'invited' => 'Invitato',
                'accepted' => 'Accettato',
                'declined' => 'Rifiutato',
                'attended' => 'Presente',
                'no_show' => 'Assente',
                'waitlisted' => 'Lista di attesa',
            ],
        ],
        'reminder' => [
            'channel' => [
                'mail' => 'E-mail',
                'webpush' => 'Push',
                'database' => 'Nell\'app',
            ],
        ],
    ],
    'vehicle' => [
        'type' => [
            'car' => 'Auto',
            'van' => 'Furgone',
            'truck' => 'Camion',
            'bicycle' => 'Bicicletta',
            'other' => 'Altro',
        ],
        'propulsion' => [
            'diesel' => 'Diesel',
            'petrol' => 'Benzina',
            'gas' => 'Gas',
            'hybrid' => 'Ibrido',
            'electric' => 'Elettrico',
            'muscle' => 'Forza muscolare',
            'other' => 'Altro',
        ],
        'ownership' => [
            'owned' => 'Di proprietà',
            'leased' => 'Leasing',
            'rental' => 'Noleggio',
        ],
    ],
    'diary' => [
        'dispatch_status' => [
            'unplanned' => 'Non pianificato',
            'planned' => 'Pianificato',
            'confirmed' => 'Confermato',
            'enRoute' => 'In viaggio',
            'done' => 'Completato',
        ],
    ],
    'sickness' => [
        'kind' => [
            'initial' => 'Certificato iniziale',
            'follow_up' => 'Certificato di proseguimento',
        ],
    ],
    'tour' => [
        'status' => [
            'draft' => 'Bozza',
            'planned' => 'Pianificato',
            'in_progress' => 'In corso',
            'completed' => 'Completato',
            'cancelled' => 'Annullato',
        ],
    ],
    'activity' => [
        'category_type' => [
            'admin' => 'Amministrazione',
            'training' => 'Formazione',
            'meeting' => 'Riunione',
            'internal' => 'Interno',
            'travel' => 'Trasferta',
            'break' => 'Pausa',
            'absence' => 'Assenza',
            'standby' => 'Reperibilità',
            'other' => 'Altro',
        ],
    ],
    'vacation' => [
        'type' => [
            'vacation' => 'Ferie',
            'sick' => 'Malattia',
            'special' => 'Permesso speciale',
            'unpaid' => 'Non retribuito',
        ],
        'status' => [
            'pending' => 'In attesa',
            'approved' => 'Approvato',
            'rejected' => 'Rifiutato',
            'cancelled' => 'Annullato',
        ],
    ],
    'cloud_intake' => [
        'provider' => [
            'dropbox' => 'Dropbox',
            'microsoft' => 'Microsoft OneDrive/SharePoint',
            'google' => 'Google Drive',
            'nextcloud' => 'Nextcloud',
        ],
        'connection_status' => [
            'draft' => 'Bozza',
            'active' => 'Attiva',
            'reauth_required' => 'Nuova autenticazione',
            'blocked' => 'Bloccata',
            'disabled' => 'Disattivata',
        ],
        'route_target' => [
            'incoming_invoice' => 'Fatture in entrata',
            'document' => 'Documento (DMS)',
            'b2b_order' => 'Ordine B2B (openTRANS)',
            'gaeb_package' => 'Documenti di gara (pacchetto GAEB)',
        ],
        'item_status' => [
            'imported' => 'Importato',
            'inbox' => 'Inbox',
            'rejected' => 'Rifiutato',
            'duplicate' => 'Duplicato',
            'source_gone' => 'Origine rimossa',
        ],
    ],
    'product' => [
        'status' => [
            'active' => 'Attivo',
            'phasing_out' => 'In esaurimento',
            'discontinued' => 'Fuori produzione',
        ],
    ],
    'project' => [
        'status' => [
            'active' => 'Attivo',
            'paused' => 'In pausa',
            'archived' => 'Archiviato',
        ],
    ],
    'access' => [
        'medium_status' => ['in_stock' => 'A magazzino', 'issued' => 'Consegnato', 'lost' => 'Smarrito', 'blocked' => 'Bloccato', 'retired' => 'Dismesso'],
        'medium_type' => ['transponder' => 'Transponder', 'card' => 'Tessera', 'code' => 'Codice'],
    ],
    'sales' => [
        'lead_status' => ['new' => 'Nuovo', 'contacted' => 'Contattato', 'qualified' => 'Qualificato', 'converted' => 'Convertito', 'discarded' => 'Scartato'],
        'lead_source' => ['referral' => 'Passaparola', 'web' => 'Web', 'trade_fair' => 'Fiera', 'phone' => 'Telefono', 'other' => 'Altro'],
    ],
    // Sicherheitseinbehalte (Feature 113, MVP-602).
    // Bürgschaftsregister (Feature 114, MVP-603).
    // Gewährleistungsfristen (Feature 115, MVP-604).
    'warranty_side' => [
        'owed' => 'Responsabilità propria',
        'claimable' => 'Esigibile (subappalto)',
    ],
    'warranty_basis' => [
        'bgb_5y' => 'BGB, 5 anni',
        'vob_4y' => 'VOB/B, 4 anni',
        'custom' => 'Liberamente pattuito',
    ],
    'warranty_status' => [
        'open' => 'Aperto',
        'closed' => 'Chiuso',
        'claimed' => 'Contestato',
    ],
    // Pflichtnachweise (Feature 117, MVP-606).
    'credential_status' => [
        'ok' => 'Completo',
        'expiring' => 'In scadenza',
        'missing' => 'Mancante',
        'expired' => 'Scaduto',
    ],
    'guarantee_direction' => [
        'issued' => 'Prestata',
        'received' => 'Ricevuta',
    ],
    'guarantee_kind' => [
        'performance' => 'Fideiussione di buona esecuzione',
        'warranty' => 'Fideiussione di garanzia',
        'advance_payment' => 'Fideiussione per anticipo',
        'defects' => 'Fideiussione per vizi',
    ],
    'guarantee_status' => [
        'active' => 'Attiva',
        'returned' => 'Restituita',
        'drawn' => 'Escussa',
        'expired' => 'Scaduta',
    ],
    'payment_run_kind' => [
        'credit_transfer' => 'Bonifico cumulativo',
        'direct_debit' => 'Addebito cumulativo',
    ],
    'payment_run_status' => [
        'draft' => 'Bozza',
        'released' => 'Approvata',
        'exported' => 'Esportata',
        'cancelled' => 'Annullata',
    ],
    'sepa_mandate_kind' => [
        'one_off' => 'Una tantum',
        'recurring' => 'Ricorrente',
    ],
    'sepa_mandate_status' => [
        'active' => 'Attivo',
        'revoked' => 'Revocato',
        'expired' => 'Scaduto',
    ],
    'retention_base' => [
        'net' => 'Importo netto',
        'gross' => 'Importo lordo',
    ],
    'retention_kind' => [
        'warranty' => 'Ritenuta a garanzia',
        'performance' => 'Ritenuta di esecuzione',
    ],
    'retention_status' => [
        'open' => 'Aperta',
        'released' => 'Liberata',
        'secured' => 'Sostituita da fideiussione',
    ],
    'sync_command' => [
        'status' => ['applied' => 'Applicato', 'duplicate' => 'Duplicato', 'conflict' => 'Conflitto', 'rejected' => 'Respinto'],
    ],
    'task' => [
        'status' => [
            'open' => 'Aperto',
            'in_progress' => 'In corso',
            'done' => 'Completato',
        ],
        'priority' => [
            'low' => 'Bassa',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente',
        ],
    ],
    'timesheet' => [
        'status' => [
            'draft' => 'Bozza',
            'submitted' => 'Inviato',
            'signed' => 'Firmato',
            'locked' => 'Bloccato',
        ],
        'kind' => [
            'project' => 'Progetto',
            'personal_day' => 'Giornata personale',
        ],
    ],
    'time_entry' => [
        'kind' => [
            'work' => 'Lavoro',
            'travel' => 'Trasferta',
            'standby' => 'Reperibilità',
        ],
    ],
    'expense' => [
        'status' => [
            'draft' => 'Bozza',
            'pending' => 'Inviato',
            'approved' => 'Approvato',
            'rejected' => 'Rifiutato',
            'cancelled' => 'Annullato',
            'reimbursed' => 'Rimborsato',
            'invoiced' => 'Fatturato',
        ],
        'payment_method' => [
            'private_paid' => 'Pagato privatamente',
            'company_card' => 'Carta aziendale',
            'cash' => 'Cassa',
            'bank_transfer' => 'Bonifico bancario',
        ],
    ],
    'per_diem' => [
        'day_kind' => [
            'departure_day' => 'Giorno di partenza',
            'full_day' => 'Giornata intera di viaggio',
            'return_day' => 'Giorno di rientro',
            'single_day' => 'Trasferta di un giorno',
        ],
        'trip_status' => [
            'draft' => 'Bozza',
            'converted' => 'Convertito in spesa',
            'cancelled' => 'Annullato',
        ],
    ],
    'notification' => [
        'event' => [
            'crisis' => [
                'alert' => 'Allarme di crisi',
            ],
            'claim' => [
                'escalation' => 'Reclamo in ritardo',
            ],
            'rental' => [
                'returnOverdue' => 'Restituzione noleggio in ritardo',
                'requested' => 'Richiesta di noleggio ricevuta dal portale',
            ],
            'assetFinance' => [
                'deadline' => 'Scadenza leasing',
            ],
            'contract' => [
                'deadlineDue' => 'Scadenza contrattuale',
            ],
            'accounting' => [
                'recurringOverdue' => 'Operazione ricorrente scaduta',
                'filingDue' => 'Scadenza dichiarativa in arrivo',
            ],
            'invoice' => [
                'recurringDraft' => 'Bozza di fattura da piano di fatturazione',
            ],
            'fleet' => [
                'licenseCheckDue' => 'Controllo della patente in scadenza',
            ],
            'drivingTime' => [
                'violation' => 'Rilievo tempi di guida/riposo',
            ],
            'recruiting' => [
                'applicationReceived' => 'Candidatura pubblica ricevuta',
            ],
            'assetCompliance' => [
                'inspectionDue' => 'Verifica in scadenza/scaduta',
            ],
            'ticket' => [
                'assigned' => 'Ticket assegnato',
                'customerReplied' => 'Il cliente ha risposto',
                'waitingExpired' => 'Richiamo ticket scaduto',
            ],
            'problem' => [
                'effectivenessDue' => 'Verifica di efficacia di un problema in scadenza',
            ],
            'openIssue' => [
                'assigned' => 'Punto aperto assegnato',
                'dueSoon' => 'Punto aperto in scadenza',
                'overdue' => 'Punto aperto scaduto',
            ],
            'communication' => [
                'followupDueSoon' => 'Follow-up in scadenza',
                'followupOverdue' => 'Follow-up scaduto',
            ],
            'document' => [
                'expiringSoon' => 'Documento in scadenza',
                'expired' => 'Documento scaduto',
            ],
            'timeCorrection' => [
                'requested' => 'Richiesta di correzione orari inviata',
                'decided' => 'Richiesta di correzione orari decisa',
            ],
            'overtime' => [
                'requested' => 'Richiesta di straordinario presentata',
                'decided' => 'Richiesta di straordinario decisa',
            ],
            'vacation' => [
                'requested' => 'Richiesta di ferie inviata',
                'decided' => 'Richiesta di ferie decisa',
            ],
            'attendance' => [
                'unclearCase' => 'Caso da chiarire (timbrature)',
            ],
            'monthClosure' => [
                'submitted' => 'Chiusura mensile inviata',
                'decided' => 'Chiusura mensile decisa',
            ],
            'isms' => [
                'certificateExpiring' => 'Certificato ISMS in scadenza',
                'correctiveActionOverdue' => 'Azione correttiva ISMS in ritardo',
                'riskReviewDue' => 'Riesame del rischio ISMS in scadenza',
                'vulnerabilityOverdue' => 'Vulnerabilità ISMS in ritardo',
                'incidentCritical' => 'Incidente di sicurezza ISMS critico',
                'supplierReviewOverdue' => 'Riesame fornitore ISMS scaduto',
            ],
            'sla' => [
                'atRisk' => 'Scadenza SLA a rischio',
                'breached' => 'Scadenza SLA violata',
                'quotaWarning' => 'Quota SLA quasi esaurita',
            ],
            'asset' => [
                'returnOverdue' => 'Restituzione asset in ritardo',
            ],
            'tender' => [
                'submissionDueSoon' => 'Scadenza offerta in avvicinamento',
                'submissionOverdue' => 'Scadenza offerta superata',
                'bindingExpiring' => 'Termine di vincolo in scadenza',
            ],
            'safety' => [
                'criticalEvent' => 'Evento di sicurezza critico',
                'assessmentReviewDue' => 'Valutazione dei rischi: revisione in scadenza',
                'instructionDue' => 'Formazione sulla sicurezza da ripetere',
                'checkupDue' => 'Sorveglianza sanitaria in scadenza',
            ],
            'training' => [
                'due' => 'Corso obbligatorio in scadenza',
            ],
            'qualification' => [
                'expiring' => 'Qualifica in scadenza',
            ],
            'shiftExchange' => [
                'requested' => 'Scambio turno richiesto',
                'decided' => 'Scambio turno deciso',
            ],
            'customer' => [
                'queryRaised' => 'Il cliente ha posto una domanda',
            ],
            'ideaMap' => [
                'shared' => 'Mappa delle idee condivisa con te',
            ],
            'shipment' => [
                'deliveryProblem' => 'Problema di consegna di una spedizione',
            ],
            'cti' => [
                'incomingCall' => 'Chiamata in arrivo',
            ],
            'maintenance' => [
                'dueSoon' => 'Manutenzione/controllo in scadenza',
                'overdue' => 'Manutenzione/controllo scaduto',
            ],
            'domain' => [
                'expiring' => 'Dominio in scadenza / rinnovo non riuscito',
                'transferChanged' => 'Stato trasferimento dominio cambiato',
                'syncFailed' => 'Sincronizzazione dominio non riuscita',
                'highRiskAction' => 'Azione dominio ad alto rischio approvata',
            ],
            'finance' => [
                'retentionReleaseDue' => 'Liberazione ritenuta dovuta',
                'guaranteeExpiring' => 'Fideiussione in scadenza',
                'guaranteeReturnDue' => 'Restituzione fideiussione dovuta',
                'transferFailed' => 'Trasferimento fatturazione non riuscito',
                'bankImportFailed' => 'Import bancario non riuscito',
                'reconciliationReview' => 'Riconciliazione pagamenti da chiarire',
            ],
            'investment' => [
                'decisionDue' => 'Decisione di investimento in scadenza',
                'decided' => 'Richiesta di investimento decisa',
            ],
            'inventory' => [
                'lotExpiring' => 'Lotto in scadenza (TMC)',
            ],
            'operations' => [
                'backupOverdue' => 'Backup in ritardo',
                'backupFailed' => 'Backup non riuscito',
                'restoreTestOverdue' => 'Test di ripristino in ritardo',
                'updateAvailable' => 'Aggiornamento disponibile',
                'updateSecurity' => 'Aggiornamento di sicurezza disponibile',
                'licenseExpiring' => 'Licenza in scadenza',
                'credentialExpiring' => 'Credenziale/token in scadenza',
                'connectionFailing' => 'Connessione non funzionante',
                'componentEol' => 'Componente a fine vita (EOL)',
                'pluginDisabled' => 'Plugin disattivato automaticamente',
                'schedulerOverdue' => 'Attività pianificata in ritardo',
                'queueDegraded' => 'Coda compromessa',
                'maintenanceScheduled' => 'Finestra di manutenzione annunciata',
                'problemReportReceived' => 'Nuova segnalazione di problema ricevuta',
                'cloudIntakeReauth' => 'Ingresso cloud: nuovo accesso necessario',
                'cloudIntakeQuarantined' => 'Ingresso cloud: importazioni rifiutate',
            ],
            'quote' => [
                'followUpDue' => 'Preventivo: sollecito da fare',
                'expiringWithoutReaction' => 'Preventivo in scadenza senza risposta',
            ],
            'weather' => [
                'warning' => 'Allerta meteo per un intervento',
            ],
            'warranty' => [
                'expiring' => 'Garanzia in scadenza',
                'subcontractorEndsFirst' => 'Il termine del subappaltatore scade prima del proprio',
            ],
            'supplier' => ['credentialExpiring' => 'Documento obbligatorio in scadenza'],
            'security' => [
                'integrity' => 'Integrità del codice sorgente',
                'threat' => 'Rilevamento attacchi',
                'newDevice' => 'Accesso da nuovo dispositivo',
                'lockout' => 'Account temporaneamente bloccato',
            ],
            'diary' => [
                'commentCreated' => 'Nuovo commento nel registro ordini',
                'problem' => 'Voce del registro con problema',
                'completed' => 'Voce del registro completata',
                'attachmentAdded' => 'Nuovo allegato nel registro ordini',
            ],
            'emergency' => ['assigned' => 'Servizio di emergenza assegnato'],
            'timesheet' => ['signed' => 'Foglio ore firmato'],
            'chat' => [
                'message' => 'Messaggio di chat',
                'reminder' => 'Promemoria di chat',
            ],
        ],
        'channel' => [
            'inApp' => 'In-app',
            'mail' => 'E-mail',
            'push' => 'Push',
            'teams' => 'Microsoft Teams',
            'mattermost' => 'Mattermost',
            'calendar' => 'Calendario',
            'sms' => 'SMS',
        ],
        'sms_status' => [
            'sent' => 'Inviato',
            'delivered' => 'Consegnato',
            'failed' => 'Non riuscito',
            'blocked' => 'Non inviato',
        ],
    ],

    'customer-query' => [
        'status' => [
            'open' => 'Aperta',
            'answered' => 'Risposta',
            'closed' => 'Chiusa',
        ],
    ],

    'shift' => [
        'availability_kind' => [
            'available' => 'Disponibile',
            'unavailable' => 'Non disponibile',
            'preferred' => 'Preferito',
        ],
        'preference' => [
            'want' => 'Desiderio',
            'avoid' => 'Avversione',
            'off' => 'Giorno libero desiderato',
        ],
        'exchange_status' => [
            'requested' => 'Richiesto',
            'accepted' => 'Accettato',
            'approved' => 'Approvato',
            'rejected' => 'Rifiutato',
            'cancelled' => 'Ritirato',
        ],
    ],

    'sla' => [
        'status' => [
            'none' => 'Nessuno SLA',
            'met' => 'SLA rispettato',
            'onTrack' => 'SLA nei tempi',
            'atRisk' => 'SLA a rischio',
            'breached' => 'SLA violato',
        ],
        'violationKind' => [
            'responseTime' => 'Tempo di reazione',
            'resolutionTime' => 'Tempo di risoluzione',
        ],
        'quotaPeriod' => [
            'month' => 'Mese',
            'quarter' => 'Trimestre',
            'year' => 'Anno',
        ],
    ],

    'training' => [
        'provider-kind' => [
            'internal' => 'Interno',
            'external' => 'Esterno',
        ],
        'requirement-subject' => [
            'role' => 'Ruolo',
            'team' => 'Area di attività (team)',
        ],
        'assignment-state' => [
            'fulfilled' => 'Adempiuto',
            'planned' => 'Pianificato',
            'due' => 'In scadenza',
            'overdue' => 'Scaduto',
        ],
    ],

    'safety' => [
        'assessment-status' => [
            'draft' => 'Bozza',
            'inReview' => 'In revisione',
            'approved' => 'Approvata',
            'archived' => 'Archiviata',
        ],
        'checkup-kind' => [
            'mandatory' => 'Visita obbligatoria',
            'offered' => 'Visita offerta',
            'requested' => 'Visita su richiesta',
        ],
        'signature-method' => [
            'confirmed' => 'Clic di conferma',
            'drawn' => 'Firma (immagine)',
        ],
        'kind' => [
            'accident' => 'Infortunio',
            'nearMiss' => 'Quasi infortunio',
            'hazard' => 'Pericolo',
            'defect' => 'Difetto',
        ],
        'severity' => [
            'low' => 'Bassa',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Critica',
        ],
        'status' => [
            'reported' => 'Segnalato',
            'investigating' => 'In indagine',
            'measuresDefined' => 'Misure definite',
            'closed' => 'Chiuso',
        ],
    ],

    'open-issue' => [
        'status' => [
            'open' => 'Aperto',
            'inProgress' => 'In corso',
            'blocked' => 'Bloccato',
            'done' => 'Completato',
            'wontDo' => 'Non verrà fatto',
            'reopened' => 'Riaperto',
        ],
        'severity' => [
            'low' => 'Bassa',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Critica',
        ],
        'source' => [
            'manual' => 'Manuale',
            'protocolDefect' => 'Dal protocollo',
            'communicationFollowup' => 'Dalla comunicazione',
            'procedureDeviation' => 'Da deviazione di procedura',
            'customerRejection' => 'Rifiuto del cliente',
            'patrolDeviation' => 'Da scostamento di ronda',
        ],
        'visibility' => [
            'internal' => 'Interno',
            'customer' => 'Visibile al cliente',
        ],
    ],
    'communication' => [
        'type' => [
            'call' => 'Telefonata',
            'email' => 'E-mail',
            'meeting' => 'Incontro in loco',
            'videocall' => 'Videoconferenza',
            'chat' => 'Chat / messaggistica',
            'internal' => 'Consultazione interna',
            'decision' => 'Decisione',
            'letter' => 'Lettera / fax',
            'other' => 'Altro',
        ],
        'direction' => [
            'inbound' => 'In entrata',
            'outbound' => 'In uscita',
            'internal' => 'Interna',
        ],
        'visibility' => [
            'internal' => 'Interna',
            'customer' => 'Visibile al cliente',
        ],
        'party' => [
            'internal' => 'Interno',
            'customer' => 'Cliente',
            'thirdParty' => 'Terzi',
        ],
    ],
    'knowledge' => [
        'status' => [
            'draft' => 'Bozza',
            'published' => 'Pubblicato',
            'archived' => 'Archiviato',
        ],
        'visibility' => [
            'internal' => 'Interno (intera organizzazione)',
            'team' => 'Limitato al team',
        ],
    ],
    'form' => [
        'template_status' => [
            'draft' => 'Bozza',
            'active' => 'Attivo',
            'archived' => 'Archiviato',
        ],
        'field_type' => [
            'text' => 'Testo',
            'textarea' => 'Testo su più righe',
            'number' => 'Numero',
            'date' => 'Data',
            'select' => 'Selezione',
            'checkbox' => 'Casella di controllo',
            'photo' => 'Foto',
            'file' => 'File',
            'signature' => 'Firma',
        ],
    ],
    // Digitale Personalakte (Feature 141, MVP-708) — bewusst ohne Gesundheitskategorie.
    'hr_document_category' => [
        'contract' => 'Contratto di lavoro',
        'amendment' => 'Modifica contrattuale / accordo integrativo',
        'certificate' => 'Attestato / certificato',
        'training' => 'Formazione / qualifica',
        'warning' => 'Richiamo scritto',
        'idDocument' => 'Documento d\'identità / prova',
        'payrollReference' => 'Documento di riferimento paga',
        'other' => 'Altro',
    ],
    'document' => [
        'type' => [
            'contract' => 'Contratto',
            'testReport' => 'Rapporto di prova',
            'certificate' => 'Certificato',
            'manual' => 'Manuale',
            'datasheet' => 'Scheda tecnica',
            'manufacturerDoc' => 'Documento del produttore',
            'permit' => 'Autorizzazione',
            'insurance' => 'Assicurazione',
            'invoice' => 'Fattura',
            'other' => 'Altro',
        ],
        'status' => [
            'draft' => 'Bozza',
            'active' => 'Attivo',
            'expired' => 'Scaduto',
            'archived' => 'Archiviato',
        ],
    ],
    'protocol' => [
        'status' => [
            'draft' => 'Bozza',
            'in_review' => 'In revisione',
            'signed' => 'Firmato',
            'archived' => 'Archiviato',
            'superseded' => 'Sostituito',
        ],
        'type' => [
            'acceptance' => 'Accettazione',
            'service' => 'Intervento di assistenza',
            'maintenance' => 'Manutenzione',
            'handover' => 'Consegna',
            'defect' => 'Rapporto di difetto',
            'inspection' => 'Ispezione',
            'siteVisit' => 'Sopralluogo',
            'other' => 'Altro',
        ],
        'visibility' => [
            'internal' => 'Interno',
            'customer' => 'Visibile al cliente',
        ],
        'item-result' => [
            'ok' => 'OK',
            'notok' => 'Non OK',
            'n_a' => 'Non applicabile',
            'open' => 'Aperto',
        ],
        'signature-role' => [
            'customer' => 'Cliente',
            'contractor' => 'Appaltatore',
            'witness' => 'Testimone',
        ],
        'signature-method' => [
            'onscreen' => 'Firma su schermo',
            'portal' => 'Portale cliente',
            'emailLink' => 'Link e-mail',
            'paper' => 'Carta',
        ],
        'item-type' => [
            'group' => 'Sezione',
            'text' => 'Testo libero',
            'boolean' => 'Elemento sì/no',
            'choice' => 'Scelta singola',
            'multichoice' => 'Scelta multipla',
            'number' => 'Misurazione / numero',
            'range' => 'Intervallo target',
            'date' => 'Data',
            'datetime' => 'Data e ora',
            'signature' => 'Firma',
            'photo' => 'Foto obbligatoria',
            'file' => 'Documento obbligatorio',
            'defect' => 'Difetto',
            'measurement.timestamped' => 'Serie di misurazioni',
            'procedure_step' => 'Fase di procedura',
            'signoff_internal' => 'Approvazione interna',
        ],
        'item-photo-phase' => [
            'before' => 'Prima',
            'after' => 'Dopo',
            'detail' => 'Dettaglio',
            'defect' => 'Difetto',
            'reference' => 'Riferimento',
        ],
    ],
    'procedure' => [
        'risk-level' => [
            'low' => 'Basso',
            'normal' => 'Normale',
            'high' => 'Alto',
            'critical' => 'Critico',
        ],
        'step-type' => [
            'confirm' => 'Conferma',
            'text' => 'Testo',
            'number' => 'Numero/misurazione',
            'choice' => 'Scelta',
            'photo' => 'Foto',
            'file' => 'File',
            'backup' => 'Registrazione di backup',
            'signature' => 'Firma',
            'material' => 'Inserimento materiale',
            'dienstmittel' => 'Attrezzatura di servizio',
            'freigabe' => 'Approvazione (doppio controllo)',
            'messreihe' => 'Serie di misurazioni',
            'link_protocol' => 'Collega protocollo',
            'link_test' => 'Collega test',
            'wait' => 'Attesa',
        ],
        'proof-type' => [
            'backup' => 'Backup',
            'file' => 'File',
            'photo' => 'Foto',
            'measure' => 'Misurazione',
            'signature' => 'Firma',
        ],
        'run-status' => [
            'open' => 'Aperto',
            'inProgress' => 'In corso',
            'blocked' => 'Bloccato',
            'completed' => 'Completato',
            'aborted' => 'Interrotto',
        ],
        'step-run-status' => [
            'pending' => 'In attesa',
            'done' => 'Completato',
            'n_a' => 'Non applicabile',
            'failed' => 'Fallito',
            'deviated' => 'Scostamento',
            'blocked' => 'Bloccato',
        ],
        'backup-scope' => [
            'config' => 'Configurazione',
            'database' => 'Database',
            'fullSystem' => 'Sistema completo',
            'customScript' => 'Script personalizzato',
        ],
        'backup-storage-target' => [
            'attachment' => 'Allegato',
            'external' => 'Archiviazione esterna',
        ],
        'backup-verify-method' => [
            'checksum' => 'Confronto checksum',
            'restoreCheck' => 'Test di ripristino',
            'managerConfirmation' => 'Conferma della direzione',
        ],
        'deviation-type' => [
            'not_applicable' => 'Non applicabile',
            'not_possible' => 'Non possibile',
            'partial' => 'Parzialmente soddisfatto',
            'alternative_method' => 'Metodo alternativo',
            'failed_check' => 'Lettura fuori tolleranza',
            'material_substitute' => 'Materiale sostitutivo',
            'safety_block' => 'Interruzione di sicurezza',
            'customer_decline' => 'Cliente ha rifiutato',
        ],
        'deviation-severity' => [
            'low' => 'Bassa',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Critica',
        ],
        'deviation-proposed-action' => [
            'none' => 'Nessuna azione di follow-up',
            'open_issue' => 'Punto aperto',
            'new_diary_entry' => 'Nuova commessa',
            'requalify' => 'Esegui di nuovo',
            'escalate' => 'Escalation',
        ],
    ],
    'duty_plan' => [
        'status' => [
            'draft' => 'Bozza',
            'published' => 'Pubblicato',
        ],
    ],
    'export' => [
        'entity' => [
            'customers' => 'Clienti',
            'projects' => 'Progetti',
            'users' => 'Utenti',
            'materials' => 'Materiali',
            'scheduled_shifts' => 'Turni pianificati',
            'tours' => 'Giri',
        ],
        'format' => [
            'csv' => 'CSV',
            'xlsx' => 'XLSX',
        ],
        'state' => [
            'preparing' => 'In preparazione',
            'ready' => 'Pronto',
            'failed' => 'Non riuscito',
        ],
    ],
    'compliance' => [
        'finding-status' => [
            'open' => 'Aperto',
            'acknowledged' => 'Confermato',
            'resolved' => 'Risolto',
            'accepted' => 'Accettato',
        ],
    ],
    'isms' => [
        'security-incident-category' => [
            'malware' => 'Malware',
            'phishing' => 'Phishing',
            'dataLoss' => 'Perdita di dati',
            'unauthorizedAccess' => 'Accesso non autorizzato',
            'serviceOutage' => 'Interruzione del servizio',
            'misconfiguration' => 'Configurazione errata',
            'physical' => 'Incidente fisico',
            'other' => 'Altro',
        ],
        'security-incident-status' => [
            'reported' => 'Segnalato',
            'triage' => 'Triage',
            'contained' => 'Contenuto',
            'eradicated' => 'Eradicato',
            'recovered' => 'Ripristinato',
            'closed' => 'Chiuso',
        ],
        'incident-severity' => [
            'low' => 'Bassa',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Critica',
        ],
        'vulnerability-status' => [
            'open' => 'Aperta',
            'underReview' => 'In esame',
            'mitigating' => 'In mitigazione',
            'resolved' => 'Risolta',
            'accepted' => 'Accettata',
            'notAffected' => 'Non interessato',
        ],
        'exploitability' => [
            'unknown' => 'Sconosciuta',
            'underInvestigation' => 'In indagine',
            'exploitable' => 'Esploitabile',
            'notExploitable' => 'Non esploitabile',
        ],
        'vulnerability-source' => [
            'manual' => 'Manuale',
            'advisoryImport' => 'Import advisory',
        ],
        'supplier-assessment-status' => [
            'draft' => 'Bozza',
            'assessed' => 'Valutato',
            'approved' => 'Approvato',
            'flagged' => 'Segnalato',
        ],
        'advisory-format' => [
            'csaf' => 'CSAF',
            'vex' => 'VEX',
        ],
        'audit-package-status' => [
            'draft' => 'Bozza',
            'finalized' => 'Finalizzato',
        ],
        'audit-kind' => [
            'internal' => 'Interno',
            'external' => 'Esterno',
            'supplier' => 'Fornitore',
        ],
        'audit-status' => [
            'planned' => 'Pianificato',
            'inPreparation' => 'In preparazione',
            'inProgress' => 'In corso',
            'reportIssued' => 'Rapporto emesso',
            'closed' => 'Chiuso',
        ],
        'finding-kind' => [
            'nonconformityMajor' => 'Non conformità maggiore',
            'nonconformityMinor' => 'Non conformità minore',
            'observation' => 'Osservazione',
            'improvement' => 'Opportunità di miglioramento',
        ],
        'finding-status' => [
            'open' => 'Aperto',
            'inCorrection' => 'In correzione',
            'effectivenessCheck' => 'Verifica di efficacia',
            'closed' => 'Chiuso',
        ],
        'corrective-action-status' => [
            'open' => 'Aperta',
            'inProgress' => 'In corso',
            'done' => 'Attuata',
            'effective' => 'Efficace',
            'ineffective' => 'Inefficace',
        ],
        'review-status' => [
            'draft' => 'Bozza',
            'approved' => 'Approvato',
        ],
        'assessment-kind' => [
            'gross' => 'Lordo',
            'net' => 'Netto',
            'target' => 'Obiettivo',
        ],
        'assessment-status' => [
            'draft' => 'Bozza',
            'approved' => 'Approvata',
        ],
        'risk-category' => [
            'organizational' => 'Organizzativo',
            'technical' => 'Tecnico',
            'physical' => 'Fisico',
            'personnel' => 'Personale',
            'supplier' => 'Fornitore',
        ],
        'risk-treatment' => [
            'avoid' => 'Evitare',
            'mitigate' => 'Ridurre',
            'transfer' => 'Trasferire',
            'accept' => 'Accettare',
        ],
        'risk-status' => [
            'identified' => 'Identificato',
            'analyzed' => 'Analizzato',
            'treated' => 'Trattato',
            'accepted' => 'Accettato',
            'closed' => 'Chiuso',
        ],
        'requirement-source' => [
            'catalog' => 'Catalogo di riferimento',
            'custom' => 'Requisito proprio',
        ],
        'control-implementation-status' => [
            'open' => 'Aperto',
            'partial' => 'Parzialmente attuato',
            'implemented' => 'Attuato',
            'notApplicable' => 'Non applicabile',
        ],
        'software-category' => [
            'os' => 'Sistema operativo',
            'application' => 'Applicazione',
            'service' => 'Servizio',
            'library' => 'Libreria',
            'other' => 'Altro',
        ],
        'support-status' => [
            'supported' => 'Supportato',
            'extendedSupport' => 'Supporto esteso',
            'endOfLife' => 'Fine vita',
            'unknown' => 'Sconosciuto',
        ],
        'norm-conformity-status' => [
            'notAssessed' => 'Non valutato',
            'gapAnalysisDone' => 'Gap analysis effettuata',
            'inProgress' => 'In attuazione',
            'internallyAuditReady' => 'Pronto per l’audit interno',
            'externalAuditPlanned' => 'Audit esterno pianificato',
            'certified' => 'Certificato',
            'certificateSuspended' => 'Certificato sospeso',
            'certificateExpired' => 'Certificato scaduto',
        ],
    ],
    'surcharge' => [
        'kind' => [
            'night' => 'Notte',
            'saturday' => 'Sabato',
            'sunday' => 'Domenica',
            'holiday' => 'Giorno festivo',
            'custom' => 'Personalizzato',
            'oncall' => 'Reperibilità in sede',
            'standby' => 'Reperibilità telefonica',
            'overtime' => 'Straordinari',
        ],
    ],
    // Kunden-Sonderkonditionen & Abrechnungskonto (Feature 098).
    'billing' => [
        // Belegfluss (Feature 105, MVP-542)
        'direction' => [
            'outgoing' => 'In uscita',
            'incoming' => 'In entrata',
            'neutral' => 'Senza effetto monetario',
        ],
        'kind' => [
            'quote' => 'Preventivo',
            'order_confirmation' => 'Conferma d\'ordine',
            'delivery_note' => 'Bolla di consegna',
            'invoice' => 'Fattura',
            'down_payment' => 'Fattura di acconto',
            'down_payment_deduction' => 'Storno acconto',
            'credit_note' => 'Nota di credito',
            'cancellation' => 'Annullamento',
            'expense' => 'Nota spese',
            'other' => 'Altro documento',
        ],
        'origin' => [
            'local' => 'Locale',
            'lexoffice' => 'Lexoffice',
            'orgamax' => 'orgaMAX',
            'sevdesk' => 'sevDesk',
            'easybill' => 'easybill',
            'invoiceplane' => 'InvoicePlane',
            'jtl_wawi' => 'JTL-Wawi',
        ],
        'agreement-mode' => [
            'account' => 'Conto cliente (senza fattura)',
            'invoice' => 'Fattura mensile',
            'retainer' => 'Forfait (Lexoffice)',
        ],
        'rate-day-type' => [
            'weekday' => 'Giorno feriale',
            'weekend' => 'Fine settimana',
        ],
        'account-payment-source' => [
            'manual' => 'Manuale',
            'bank' => 'Banca',
            'import' => 'Importazione',
            'lexoffice' => 'Lexoffice',
        ],
    ],
    'finance' => [
        // Versteuerungsart (Feature 125, MVP-679).
        'taxation-method' => [
            'debit' => 'Per competenza',
            'credit' => 'Per cassa',
        ],
        // Steuerliche Meldepflichten (Feature 125, MVP-686).
        'filing-obligation-kind' => [
            'vat_advance' => 'Liquidazione IVA periodica',
            'special_prepayment' => 'Acconto speciale',
            'recapitulative' => 'Elenco riepilogativo',
            'annual_return' => 'Dichiarazione IVA annuale',
        ],
        'filing-obligation-status' => [
            'open' => 'Aperto',
            'submitted' => 'Presentato',
            'not_required' => 'Non necessario',
        ],
        // Voranmeldungszeitraum der Umsatzsteuer (Feature 125, MVP-684).
        'vat-filing-interval' => [
            'monthly' => 'Mensile',
            'quarterly' => 'Trimestrale',
            'annual' => 'Solo dichiarazione annuale',
            'none' => 'Nessuna dichiarazione periodica',
        ],
        // Zeilen der Anlage EÜR (Feature 125, MVP-680).
        'euer-category' => [
            'income' => "Ricavi d'esercizio",
            'income_vat' => 'IVA incassata',
            'private_use' => 'Uso privato',
            'expense' => "Spese d'esercizio",
            'depreciation' => 'Ammortamenti',
            'low_value_asset' => 'Beni di modico valore',
            'input_tax' => 'IVA a credito pagata',
            'paid_vat' => 'IVA versata',
            'limited_deductible' => 'Deduzione limitata',
            'not_deductible' => 'Non deducibile',
        ],
        // Wiederkehrende Vorgänge (Feature 125, MVP-675).
        'recurring-template-kind' => [
            'document_expectation' => 'Attesa di documento',
            'posting_template' => 'Modello di registrazione',
        ],
        'recurring-interval' => [
            'monthly' => 'Mensile',
            'quarterly' => 'Trimestrale',
            'semi_annually' => 'Semestrale',
            'annually' => 'Annuale',
        ],
        'recurring-run-status' => [
            'expected' => 'Documento atteso',
            'draft_created' => 'Bozza creata',
            'fulfilled' => 'Soddisfatto',
            'blocked' => 'Bloccato',
            'skipped' => 'Saltato',
        ],
        'recurring-template-status' => [
            'active' => 'Attivo',
            'paused' => 'Sospeso',
            'ended' => 'Terminato',
        ],
        // Offene Posten (Feature 125, MVP-674).
        'open-item-direction' => [
            'receivable' => 'Credito',
            'payable' => 'Debito',
        ],
        'open-item-status' => [
            'open' => 'Aperto',
            'partially_settled' => 'Parzialmente compensato',
            'settled' => 'Compensato',
            'disputed' => 'Contestato',
        ],
        'settlement-kind' => [
            'payment' => 'Pagamento',
            'discount' => 'Sconto',
            'retention' => 'Ritenuta',
            'write_off' => 'Stralcio',
            'overpayment' => 'Eccedenza',
            'reversal' => 'Storno',
        ],
        // Quellenadapter und Buchungsregeln (Feature 125, MVP-673).
        'posting-source-kind' => [
            'sales_invoice' => 'Fattura di vendita',
            'incoming_invoice' => 'Fattura di acquisto',
            'expense' => 'Spesa',
            'cash_entry' => 'Libro cassa',
            'payment' => 'Pagamento',
            'depreciation' => 'Ammortamento',
        ],
        'posting-account-role' => [
            'receivable' => 'Credito',
            'revenue' => 'Ricavo',
            'tax_output' => 'IVA a debito',
            'payable' => 'Debito',
            'expense' => 'Costo',
            'tax_input' => 'IVA a credito',
            'cash' => 'Cassa',
            'employee_payable' => 'Debito verso dipendenti',
            'bank' => 'Banca',
            'discount' => 'Sconto',
            'fixed_asset' => 'Conto cespite',
            'depreciation' => 'Ammortamento',
        ],
        // Buchungskern (Feature 125, MVP-672).
        'balance-side' => [
            'debit' => 'Dare',
            'credit' => 'Avere',
        ],
        'account-type' => [
            'asset' => 'Attività',
            'liability' => 'Passività',
            'equity' => 'Patrimonio netto',
            'income' => 'Ricavi',
            'expense' => 'Costi',
        ],
        'bwa-group' => [
            'revenue' => 'Ricavi delle vendite',
            'inventory_change' => 'Variazione rimanenze / lavori interni capitalizzati',
            'material' => 'Costi per materiali',
            'other_operating_income' => 'Altri ricavi operativi',
            'personnel' => 'Costi del personale',
            'premises' => 'Costi dei locali',
            'operating_taxes' => 'Imposte d\'esercizio',
            'insurance_fees' => 'Assicurazioni / contributi',
            'vehicle' => 'Costi veicoli',
            'marketing_travel' => 'Costi pubblicità / viaggi',
            'goods_dispatch' => 'Costi di spedizione merci',
            'depreciation' => 'Ammortamenti',
            'repairs' => 'Riparazioni / manutenzione',
            'other_costs' => 'Altri costi',
            'interest_expense' => 'Oneri finanziari',
            'neutral_expense' => 'Altri oneri neutri',
            'interest_income' => 'Proventi finanziari',
            'neutral_income' => 'Altri proventi neutri',
            'income_taxes' => 'Imposte sul reddito',
        ],
        'accounting-entry-status' => [
            'draft' => 'Bozza',
            'ready' => 'Verificata',
            'posted' => 'Registrata',
            'reversed' => 'Stornata',
        ],
        'tax-code-direction' => [
            'output' => 'IVA a debito',
            'input' => 'IVA a credito',
            'none' => 'Senza imposta',
        ],
        // Lokale Buchhaltung (Feature 125, MVP-671).
        'accounting-sovereignty' => [
            'preaccounting' => 'Pre-contabilità (senza libro mastro)',
            'local' => 'workDiary dirige',
            'external' => 'Sistema esterno dirige',
        ],
        'profit-determination' => [
            'euer' => 'Contabilità per cassa',
            'double_entry' => 'Contabilità in partita doppia',
        ],
        // Anlagenregister (Feature 133, MVP-698).
        'fixed-asset-status' => [
            'active' => 'Attivo',
            'disposed' => 'Dismesso',
        ],
        'depreciation-method' => [
            'linear' => 'Lineare',
        ],
        'accounting-period-status' => [
            'open' => 'Aperto',
            'soft_closed' => 'Chiuso provvisoriamente',
            'closed' => 'Chiuso',
        ],
        'billing-mode' => [
            'workdiary' => 'WorkDiary (locale)',
            'lexoffice' => 'Lexoffice guida',
            'datev' => 'DATEV guida',
            'orgamax' => 'orgaMAX guida',
            'sevdesk' => 'sevDesk guida',
            'easybill' => 'easybill guida',
        ],
        'transfer-channel' => [
            'time' => 'Prestazioni/tempo',
            'material' => 'Prodotti/materiale',
        ],
        'transfer-target' => [
            'lexoffice' => 'Lexoffice',
            'datev' => 'DATEV',
            'orgamax' => 'orgaMAX (ordine)',
            'sevdesk' => 'sevDesk (bozza di fattura)',
            'easybill' => 'easybill (bozza di fattura)',
            'file' => 'Esportazione file',
        ],
        'transfer-status' => [
            'draft' => 'Bozza',
            'confirmed' => 'Confermato',
            'transferred' => 'Trasferito',
            'failed' => 'Fallito',
            'voided' => 'Annullato',
            'cancelled' => 'Stornato',
        ],
        'chart-of-accounts' => [
            'skr03' => 'SKR03',
            'skr04' => 'SKR04',
        ],
        // GoBD-Z3-Lauf (Feature 063, MVP-722).
        'gobd-export-status' => [
            'queued' => 'In coda',
            'running' => 'In corso',
            'ready' => 'Pronto',
            'failed' => 'Non riuscito',
        ],
        'datev-batch-status' => [
            'draft' => 'Bozza',
            'exported' => 'Esportato',
        ],
        // Riconciliazione dei pagamenti (Feature 045, priorità 3).
        'bank-statement-format' => [
            'camt053' => 'CAMT.053',
            'mt940' => 'MT940',
            'ofx' => 'OFX',
            'qif' => 'QIF',
            'qxf' => 'QXF',
            'pain001' => 'PAIN.001',
            'pain008' => 'PAIN.008',
        ],
        'transaction-direction' => [
            'credit' => 'Entrata',
            'debit' => 'Uscita',
        ],
        'balance-check' => [
            'ok' => 'Catena dei saldi coerente',
            'mismatch' => 'Differenza di saldo',
            'unknown' => 'Saldi incompleti',
        ],
        'match-status' => [
            'unmatched' => 'Aperto',
            'suggested' => 'Suggerimenti',
            'matched' => 'Assegnato',
            'ignored' => 'Accantonato',
            'unassignable' => 'Non assegnabile',
            'duplicate' => 'Duplicato',
        ],
        'allocation-kind' => [
            'payment' => 'Pagamento',
            'partial' => 'Pagamento parziale',
            'overpayment' => 'Pagamento in eccesso',
            'reimbursement' => 'Rimborso',
            'chargeback' => 'Storno di addebito',
            'skonto' => 'Sconto cassa (riduzione dei ricavi)',
        ],
        'procedure-documentation-status' => [
            'draft' => 'Bozza',
            'published' => 'Pubblicata',
        ],
    ],

    // Chiusura giornaliera (MVP-015, docs/tagesabschluss.md §3/§5).
    'dayClosure' => [
        'status' => [
            'open' => 'Aperto',
            'closed' => 'Chiuso',
            'correction' => 'In correzione',
            'locked' => 'Bloccato',
        ],
    ],
    'dayCorrection' => [
        'status' => [
            'pending' => 'In attesa',
            'approved' => 'Approvata',
            'rejected' => 'Respinta',
        ],
    ],

    // Esito del test di ripristino (Feature 017).
    'backup' => [
        // Destinazioni di backup cloud (funzionalità 017, fase 32).
        'provider' => [
            'dropbox' => 'Dropbox',
            'microsoft' => 'Microsoft OneDrive/SharePoint',
            'google' => 'Google Drive',
            'nextcloud' => 'Nextcloud',
            'webdav' => 'WebDAV (server proprio)',
        ],
        'target_status' => [
            'draft' => 'Bozza',
            'active' => 'Attiva',
            'reauth_required' => 'Nuovo accesso richiesto',
            'blocked' => 'Bloccata',
            'disabled' => 'Disattivata',
        ],
        'generation_status' => [
            'building' => 'In creazione',
            'uploading' => 'Caricamento in corso',
            'committed' => 'Completata',
            'verified' => 'Verificata',
            'verify_failed' => 'Verifica fallita',
            'failed' => 'Fallita',
        ],
        'retention_class' => [
            'daily' => 'Giornaliera',
            'weekly' => 'Settimanale',
            'monthly' => 'Mensile',
        ],
        'restore-test-result' => [
            'passed' => 'Superato',
            'partial' => 'Con riserve',
            'failed' => 'Fallito',
        ],
    ],

    // Azione alla scadenza di un piano di manutenzione (Feature 010 → Rango 43).
    'maintenance' => [
        'due_action' => [
            'none' => 'Solo avviso (nessun record)',
            'ticket' => 'Crea un ticket di servizio',
        ],
    ],

    'security' => [
        'integrity_check_status' => [
            'baseline' => 'Baseline creata',
            'ok' => 'Conforme',
            'deviation' => 'Scostamento',
            'missing_baseline' => 'Nessuna baseline',
            'error' => 'Errore',
        ],
    ],

    'passenger' => [
        'operation_mode' => [
            'taxi' => 'Servizio taxi (§ 47 PBefG)',
            'rental_car' => 'Noleggio con conducente (§ 49 PBefG)',
            'pooled_on_demand' => 'Servizio a chiamata aggregato (§ 50 PBefG)',
        ],
        'ride_status' => [
            'requested' => 'Richiesta',
            'accepted' => 'Accettata',
            'assigned' => 'Assegnata',
            'en_route_pickup' => 'In arrivo al prelievo',
            'waiting' => 'In attesa',
            'occupied' => 'Occupata',
            'completed' => 'Completata',
            'cancelled' => 'Annullata',
            'no_show' => 'Passeggero assente',
            'aborted' => 'Interrotta',
        ],
        'price_kind' => [
            'tariff' => 'Tariffa',
            'fixed_price' => 'Prezzo fisso',
            'contract' => 'Prezzo contrattuale',
        ],
        'order_channel' => [
            'hail' => 'Fermata a mano / stazionamento',
            'phone' => 'Telefono',
            'app' => 'App',
            'web' => 'Web',
            'mediator' => 'Centrale di smistamento',
            'contract' => 'Contratto quadro',
        ],
    ],
    'print' => [
        'order_status' => [
            'data_check' => 'Verifica dati',
            'approved' => 'Approvato',
            'in_production' => 'In produzione',
            'quality_check' => 'Controllo qualità',
            'rework' => 'Rilavorazione',
            'ready' => 'Pronto per la consegna',
            'issued' => 'Consegnato',
            'cancelled' => 'Annullato',
        ],
        'preflight_status' => [
            'pending' => 'In sospeso',
            'passed' => 'Superato',
            'warnings' => 'Con avvisi',
            'failed' => 'Fallito',
            'overridden' => 'Derogato con motivazione',
        ],
        'output_kind' => [
            'pickup' => 'Ritiro',
            'shipping' => 'Spedizione',
            'counter' => 'Vendita al banco',
        ],
    ],
    // Lernplattform (Feature 149)
    'learning' => [
        'booking-status' => [
            'requested' => 'Richiesta',
            'confirmed' => 'Confermata',
            'rejected' => 'Rifiutata',
            'cancelled' => 'Annullata',
        ],
        'submission-status' => [
            'draft' => 'Bozza',
            'submitted' => 'Consegnato',
            'returned' => 'Restituito',
            'graded' => 'Valutato',
        ],
        'question-kind' => [
            'single' => 'Scelta singola',
            'multiple' => 'Scelta multipla',
            'true_false' => 'Vero/falso',
            'short_text' => 'Testo breve',
            'cloze' => 'Testo bucato',
            'sort' => 'Ordinamento',
            'matching' => 'Abbinamento',
            'essay' => 'Tema',
            'hotspot' => 'Marcatura su immagine',
            'matrix' => 'Assegnazione a matrice',
        ],
        'feedback-mode' => [
            'immediate' => 'Subito',
            'end' => 'Alla fine',
            'none' => 'Nessuno',
        ],
        'block-kind' => [
            'heading' => 'Titolo',
            'text' => 'Testo',
            'callout' => 'Nota',
            'checklist' => 'Lista di controllo',
            'image' => 'Immagine',
            'file' => 'File',
            'video' => 'Video',
            'embed' => 'Incorporamento',
            'knowledge' => 'Articolo della knowledge base',
        ],
        'enrollment-status' => [
            'assigned' => 'Assegnato',
            'in_progress' => 'In corso',
            'completed' => 'Completato',
            'failed' => 'Non superato',
            'expired' => 'Scaduto',
            'cancelled' => 'Annullato',
        ],
        'enrollment-source' => [
            'requirement' => 'Matrice obbligatoria',
            'manual' => 'Manuale',
            'self' => 'Auto-iscrizione',
            'booking' => 'Prenotazione',
            'rule' => 'Regola',
            'path' => 'Percorso',
        ],
        'translation-status' => [
            'draft' => 'Bozza',
            'approved' => 'Approvata',
        ],
        'progress-status' => [
            'open' => 'Aperto',
            'started' => 'Iniziato',
            'completed' => 'Completato',
        ],
        'course-status' => [
            'draft' => 'Bozza',
            'review' => 'In verifica',
            'released' => 'Pubblicato',
            'archived' => 'Archiviato',
        ],
        'audience' => [
            'internal' => 'Interno',
            'external' => 'Partecipanti esterni',
            'customer' => 'Clienti',
            'public' => 'Pubblico',
        ],
        'access-kind' => [
            'open' => 'Aperto',
            'enrolled' => 'Iscritto',
            'bookable' => 'Prenotabile',
            'closed' => 'Chiuso',
        ],
        'unit-kind' => [
            'content' => 'Contenuto',
            'quiz' => 'Verifica',
            'assignment' => 'Compito',
            'procedure' => 'Procedura',
            'event' => 'Appuntamento',
            'scorm' => 'Pacchetto SCORM',
            'survey' => 'Sondaggio',
            'external' => 'Contenuto esterno',
        ],
        'time-policy' => [
            'work_time_required' => 'Solo durante l\'orario di lavoro',
            'always_counts' => 'Conta sempre come orario di lavoro',
            'approval_required' => 'Fuori orario solo con approvazione',
            'voluntary_unpaid' => 'Volontario, non retribuito',
        ],
        'instruction-suitability' => [
            'supplementary' => 'Solo integrativo',
            'with_questions' => 'Con possibilità di domande',
            'with_presence' => 'Con parte in presenza',
        ],
    ],
    'media' => [
        'state' => [
            'pending' => 'In attesa',
            'processing' => 'In elaborazione',
            'ready' => 'Pronto',
            'failed' => 'Non riuscito',
        ],
        'rendition-kind' => [
            'video' => 'Versione video',
            'poster' => 'Immagine di anteprima',
            'subtitle' => 'Sottotitoli',
        ],
        'subtitle-source' => [
            'manual' => 'manuale',
            'machine' => 'automatica',
        ],
    ],
];

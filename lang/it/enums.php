<?php

return [
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
    'project' => [
        'status' => [
            'active' => 'Attivo',
            'paused' => 'In pausa',
            'archived' => 'Archiviato',
        ],
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
            ],
            'assetFinance' => [
                'deadline' => 'Scadenza leasing',
            ],
            'assetCompliance' => [
                'inspectionDue' => 'Verifica in scadenza/scaduta',
            ],
            'ticket' => [
                'assigned' => 'Ticket assegnato',
                'customerReplied' => 'Il cliente ha risposto',
                'waitingExpired' => 'Richiamo ticket scaduto',
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
            'monthClosure' => [
                'submitted' => 'Chiusura mensile inviata',
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
            'safety' => [
                'criticalEvent' => 'Evento di sicurezza critico',
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
                'maintenanceScheduled' => 'Finestra di manutenzione annunciata',
                'problemReportReceived' => 'Nuova segnalazione di problema ricevuta',
            ],
        ],
        'channel' => [
            'inApp' => 'In-app',
            'mail' => 'E-mail',
            'push' => 'Push',
            'teams' => 'Microsoft Teams',
            'mattermost' => 'Mattermost',
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
    ],

    'safety' => [
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
        ],
    ],
    'finance' => [
        'billing-mode' => [
            'workdiary' => 'WorkDiary (locale)',
            'lexoffice' => 'Lexoffice guida',
            'datev' => 'DATEV guida',
            'orgamax' => 'orgaMAX guida',
        ],
        'transfer-channel' => [
            'time' => 'Prestazioni/tempo',
            'material' => 'Prodotti/materiale',
        ],
        'transfer-target' => [
            'lexoffice' => 'Lexoffice',
            'datev' => 'DATEV',
            'orgamax' => 'orgaMAX (ordine)',
            'file' => 'Esportazione file',
        ],
        'transfer-status' => [
            'draft' => 'Bozza',
            'confirmed' => 'Confermato',
            'transferred' => 'Trasferito',
            'failed' => 'Fallito',
            'voided' => 'Annullato',
        ],
        'chart-of-accounts' => [
            'skr03' => 'SKR03',
            'skr04' => 'SKR04',
        ],
        'datev-batch-status' => [
            'draft' => 'Bozza',
            'exported' => 'Esportato',
        ],
        // Riconciliazione dei pagamenti (Feature 045, priorità 3).
        'bank-statement-format' => [
            'camt053' => 'CAMT.053',
            'mt940' => 'MT940',
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
];

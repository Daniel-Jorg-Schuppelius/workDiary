<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : enums.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'billbee' => [
        'order_state' => [
            1 => 'Bestellt',
            2 => 'Bestätigt',
            3 => 'Bezahlt',
            4 => 'Versandt',
            5 => 'Reklamation',
            6 => 'Gelöscht',
            7 => 'Abgeschlossen',
            8 => 'Storniert',
            9 => 'Archiviert',
            11 => '1. Mahnung',
            12 => '2. Mahnung',
            13 => 'Gepackt',
            14 => 'Angeboten',
            15 => 'Zahlungserinnerung',
            16 => 'Im Fulfillment',
        ],
    ],
    'ai' => [
        'family' => ['llm' => 'Sprachmodell (LLM)', 'translation' => 'Übersetzung'],
        'verb' => ['formulate' => 'Formulieren', 'summarize' => 'Zusammenfassen', 'classify' => 'Klassifizieren', 'explain' => 'Erklären', 'find' => 'Finden', 'translate' => 'Übersetzen', 'extract' => 'Extrahieren'],
        'provider' => ['anthropic' => 'Anthropic Claude', 'openai' => 'OpenAI', 'gemini' => 'Google Gemini', 'azure_openai' => 'Azure OpenAI', 'openai_compatible' => 'OpenAI-kompatibel (generisch)', 'ollama' => 'Ollama (lokal)', 'deepl' => 'DeepL', 'azure_translator' => 'Azure Translator', 'google_translate' => 'Google Cloud Translation', 'libretranslate' => 'LibreTranslate (lokal)', 'fake' => 'Test-Provider'],
        'connection_status' => ['draft' => 'Entwurf', 'active' => 'Aktiv', 'blocked' => 'Gesperrt'],
        'memory_type' => ['glossary' => 'Glossar', 'style_rule' => 'Stil-Regel', 'example' => 'Beispielpaar'],
        'sensitivity' => ['low' => 'Niedrig', 'medium' => 'Mittel', 'high' => 'Hoch'],
    ],
    'domain' => [
        'environment' => ['ote' => 'OT&E (Test/Pilot)', 'production' => 'Produktiv'],
        'connection_status' => ['draft' => 'Entwurf', 'active' => 'Aktiv', 'blocked' => 'Gesperrt'],
        'sync_status' => ['current' => 'Aktuell', 'stale' => 'Veraltet', 'pending' => 'Ausstehend', 'conflict' => 'Konflikt', 'unknown' => 'Unklar'],
        'renewal_mode' => ['autorenew' => 'Auto-Renew', 'autoexpire' => 'Auto-Expire', 'autodelete' => 'Auto-Delete', 'renewonce' => 'Einmal verlängern'],
        'command_status' => ['draft' => 'Entwurf', 'approved' => 'Freigegeben', 'pending' => 'Ausstehend', 'confirmed' => 'Bestätigt', 'failed' => 'Fehlgeschlagen', 'unknown' => 'Unklar', 'conflict' => 'Konflikt'],
        'capability_area' => ['authentication' => 'Authentifizierung', 'subuser' => 'Subuser', 'domains' => 'Domains', 'contacts' => 'Kontakte', 'nameservers' => 'Nameserver', 'dns' => 'DNS-Zonen', 'events' => 'Ereignisse', 'renewal' => 'Renewal', 'transfer' => 'Transfer', 'accounting' => 'Accounting', 'invoices' => 'Rechnungen'],
    ],
    'asset' => [
        'defect-severity' => [
            'low' => 'Niedrig',
            'medium' => 'Mittel',
            'high' => 'Hoch',
            'critical' => 'Kritisch',
        ],
        'defect-status' => [
            'open' => 'Offen',
            'inRepair' => 'In Reparatur',
            'resolved' => 'Behoben',
            'writtenOff' => 'Ausgebucht',
        ],
        'ownership' => [
            'org' => 'Organisation',
            'customer' => 'Kunde',
            'external' => 'Extern',
        ],
    ],
    'classification' => [
        'requirement-phase' => [
            'onCreate' => 'Bei Erstellung',
            'beforeComplete' => 'Vor Abschluss',
            'beforeSign' => 'Vor Signatur',
        ],
        'requirement-severity' => [
            'hard' => 'Blockierend',
            'soft' => 'Hinweis',
        ],
    ],
    'room_requirement_kind' => [
        'hygieneLevel' => 'Hygienestufe',
        'specialCleaning' => 'Sonderreinigung',
        'accessRestriction' => 'Zugangsbeschränkung',
        'itInventory' => 'IT-Inventar',
        'technicalInspection' => 'Technische Prüfung',
        'operatorDuty' => 'Betreiberpflicht',
        'other' => 'Sonstige',
    ],
    'event' => [
        'type' => [
            'training' => 'Schulung',
            'workshop' => 'Workshop',
            'conference' => 'Konferenz',
            'meeting' => 'Besprechung',
            'internal_briefing' => 'Interne Unterweisung',
            'external_visit' => 'Externer Besuch',
        ],
        'status' => [
            'planned' => 'Geplant',
            'confirmed' => 'Bestätigt',
            'in_progress' => 'Läuft',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Abgesagt',
        ],
        'visibility' => [
            'internal' => 'Intern',
            'external' => 'Extern',
            'public' => 'Öffentlich',
        ],
        'participant' => [
            'role' => [
                'organizer' => 'Organisator',
                'trainer' => 'Trainer',
                'attendee' => 'Teilnehmer',
                'optional' => 'Optional',
            ],
            'status' => [
                'invited' => 'Eingeladen',
                'accepted' => 'Zugesagt',
                'declined' => 'Abgesagt',
                'attended' => 'Anwesend',
                'no_show' => 'Nicht erschienen',
            ],
        ],
        'reminder' => [
            'channel' => [
                'mail' => 'E-Mail',
                'webpush' => 'Push-Nachricht',
                'database' => 'In-App',
            ],
        ],
    ],
    'vehicle' => [
        'type' => [
            'car' => 'PKW',
            'van' => 'Transporter',
            'truck' => 'LKW',
            'bicycle' => 'Fahrrad',
            'other' => 'Sonstiges',
        ],
        'propulsion' => [
            'diesel' => 'Diesel',
            'petrol' => 'Benzin',
            'gas' => 'Gas',
            'hybrid' => 'Hybrid',
            'electric' => 'Elektro',
            'muscle' => 'Muskelkraft',
            'other' => 'Sonstiges',
        ],
        'ownership' => [
            'owned' => 'Eigentum',
            'leased' => 'Leasing',
            'rental' => 'Mietwagen',
        ],
    ],
    'diary' => [
        'dispatch_status' => [
            'unplanned' => 'Ungeplant',
            'planned' => 'Geplant',
            'confirmed' => 'Bestätigt',
            'enRoute' => 'Unterwegs',
            'done' => 'Erledigt',
        ],
    ],
    'sickness' => [
        'kind' => [
            'initial' => 'Erstbescheinigung',
            'follow_up' => 'Folgebescheinigung',
        ],
    ],
    'tour' => [
        'status' => [
            'draft' => 'Entwurf',
            'planned' => 'Geplant',
            'in_progress' => 'In Arbeit',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Abgebrochen',
        ],
    ],
    'activity' => [
        'category_type' => [
            'admin' => 'Verwaltung',
            'training' => 'Schulung',
            'meeting' => 'Besprechung',
            'internal' => 'Intern',
            'travel' => 'Reise',
            'break' => 'Pause',
            'absence' => 'Abwesenheit',
            'standby' => 'Bereitschaft',
            'other' => 'Sonstiges',
        ],
    ],
    'vacation' => [
        'type' => [
            'vacation' => 'Urlaub',
            'sick' => 'Krank',
            'special' => 'Sonderurlaub',
            'unpaid' => 'Unbezahlt',
        ],
        'status' => [
            'pending' => 'Ausstehend',
            'approved' => 'Genehmigt',
            'rejected' => 'Abgelehnt',
            'cancelled' => 'Storniert',
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
            'draft' => 'Entwurf',
            'active' => 'Aktiv',
            'reauth_required' => 'Neu anmelden',
            'blocked' => 'Blockiert',
            'disabled' => 'Deaktiviert',
        ],
        'route_target' => [
            'incoming_invoice' => 'Eingangsrechnungen',
            'document' => 'Dokument (DMS)',
            'b2b_order' => 'B2B-Bestellung (openTRANS)',
            'gaeb_package' => 'Vergabeunterlagen (GAEB-Paket)',
        ],
        'item_status' => [
            'imported' => 'Übernommen',
            'inbox' => 'Inbox',
            'rejected' => 'Abgelehnt',
            'duplicate' => 'Dublette',
            'source_gone' => 'Quelle entfernt',
        ],
    ],
    'product' => [
        'status' => [
            'active' => 'Aktiv',
            'phasing_out' => 'Auslaufend',
            'discontinued' => 'Abgekündigt',
        ],
    ],
    'project' => [
        'status' => [
            'active' => 'Aktiv',
            'paused' => 'Pausiert',
            'archived' => 'Archiviert',
        ],
    ],
    'sync_command' => [
        'status' => ['applied' => 'Übernommen', 'duplicate' => 'Wiederholung', 'conflict' => 'Konflikt', 'rejected' => 'Abgewiesen'],
    ],
    'task' => [
        'status' => [
            'open' => 'Offen',
            'in_progress' => 'In Arbeit',
            'done' => 'Erledigt',
        ],
        'priority' => [
            'low' => 'Niedrig',
            'medium' => 'Mittel',
            'high' => 'Hoch',
            'urgent' => 'Dringend',
        ],
    ],
    'timesheet' => [
        'status' => [
            'draft' => 'Entwurf',
            'submitted' => 'Eingereicht',
            'signed' => 'Signiert',
            'locked' => 'Gesperrt',
        ],
        'kind' => [
            'project' => 'Projekt',
            'personal_day' => 'Persönlicher Tag',
        ],
    ],
    'time_entry' => [
        'kind' => [
            'work' => 'Arbeit',
            'travel' => 'Anfahrt',
            'standby' => 'Bereitschaft',
        ],
    ],
    'expense' => [
        'status' => [
            'draft' => 'Entwurf',
            'pending' => 'Eingereicht',
            'approved' => 'Genehmigt',
            'rejected' => 'Abgelehnt',
            'cancelled' => 'Storniert',
            'reimbursed' => 'Erstattet',
            'invoiced' => 'Abgerechnet',
        ],
        'payment_method' => [
            'private_paid' => 'Privat verauslagt',
            'company_card' => 'Firmenkarte',
            'cash' => 'Barkasse',
            'bank_transfer' => 'Banküberweisung',
        ],
    ],
    'per_diem' => [
        'day_kind' => [
            'departure_day' => 'Anreisetag',
            'full_day' => 'Voller Reisetag',
            'return_day' => 'Abreisetag',
            'single_day' => 'Eintagesreise',
        ],
        'trip_status' => [
            'draft' => 'Entwurf',
            'converted' => 'In Spese überführt',
            'cancelled' => 'Storniert',
        ],
    ],
    'notification' => [
        'event' => [
            'crisis' => [
                'alert' => 'Krisenalarm',
            ],
            'claim' => [
                'escalation' => 'Reklamation überfällig',
            ],
            'rental' => [
                'returnOverdue' => 'Verleih-Rückgabe überfällig',
            ],
            'assetFinance' => [
                'deadline' => 'Leasingfrist fällig',
            ],
            'contract' => [
                'deadlineDue' => 'Vertragsfrist fällig',
            ],
            'invoice' => [
                'recurringDraft' => 'Rechnungsentwurf aus Abrechnungsplan',
            ],
            'fleet' => [
                'licenseCheckDue' => 'Führerscheinkontrolle fällig',
            ],
            'recruiting' => [
                'applicationReceived' => 'Öffentliche Bewerbung eingegangen',
            ],
            'assetCompliance' => [
                'inspectionDue' => 'Prüfung fällig/überfällig',
            ],
            'ticket' => [
                'assigned' => 'Ticket zugewiesen',
                'customerReplied' => 'Kunde hat geantwortet',
                'waitingExpired' => 'Ticket-Wiedervorlage fällig',
            ],
            'problem' => [
                'effectivenessDue' => 'Wirksamkeitsprüfung eines Problems fällig',
            ],
            'openIssue' => [
                'assigned' => 'Offener Punkt zugewiesen',
                'dueSoon' => 'Offener Punkt bald fällig',
                'overdue' => 'Offener Punkt überfällig',
            ],
            'communication' => [
                'followupDueSoon' => 'Folgeaktion bald fällig',
                'followupOverdue' => 'Folgeaktion überfällig',
            ],
            'document' => [
                'expiringSoon' => 'Dokument läuft bald ab',
                'expired' => 'Dokument abgelaufen',
            ],
            'timeCorrection' => [
                'requested' => 'Zeit-Korrekturantrag eingereicht',
                'decided' => 'Zeit-Korrekturantrag entschieden',
            ],
            'overtime' => [
                'requested' => 'Überstunden-Antrag eingereicht',
                'decided' => 'Überstunden-Antrag entschieden',
            ],
            'vacation' => [
                'requested' => 'Urlaubsantrag eingereicht',
                'decided' => 'Urlaubsantrag entschieden',
            ],
            'attendance' => [
                'unclearCase' => 'Ungeklärter Fall (Stempelzeiten)',
            ],
            'monthClosure' => [
                'submitted' => 'Monatsabschluss eingereicht',
                'decided' => 'Monatsabschluss entschieden',
            ],
            'isms' => [
                'certificateExpiring' => 'ISMS-Zertifikat läuft bald ab',
                'correctiveActionOverdue' => 'ISMS-Korrekturmaßnahme überfällig',
                'riskReviewDue' => 'ISMS-Risiko-Review fällig',
                'vulnerabilityOverdue' => 'ISMS-Schwachstelle überfällig',
                'incidentCritical' => 'Kritischer ISMS-Sicherheitsvorfall',
                'supplierReviewOverdue' => 'ISMS-Lieferanten-Review überfällig',
            ],
            'sla' => [
                'atRisk' => 'SLA-Frist gefährdet',
                'breached' => 'SLA-Frist verletzt',
                'quotaWarning' => 'SLA-Kontingent bald erschöpft',
            ],
            'asset' => [
                'returnOverdue' => 'Asset-Rückgabe überfällig',
            ],
            'tender' => [
                'submissionDueSoon' => 'Angebotsfrist rückt näher',
                'submissionOverdue' => 'Angebotsfrist überschritten',
                'bindingExpiring' => 'Bindefrist läuft ab',
            ],
            'safety' => [
                'criticalEvent' => 'Kritisches Sicherheitsereignis',
            ],
            'qualification' => [
                'expiring' => 'Qualifikation läuft bald ab',
            ],
            'shiftExchange' => [
                'requested' => 'Schichttausch beantragt',
                'decided' => 'Schichttausch entschieden',
            ],
            'customer' => [
                'queryRaised' => 'Kunde hat eine Rückfrage gestellt',
            ],
            'ideaMap' => [
                'shared' => 'Ideenlandkarte für Sie freigegeben',
            ],
            'shipment' => [
                'deliveryProblem' => 'Zustellproblem bei einer Sendung',
            ],
            'cti' => [
                'incomingCall' => 'Eingehender Anruf',
            ],
            'maintenance' => [
                'dueSoon' => 'Wartung/Prüfung wird fällig',
                'overdue' => 'Wartung/Prüfung überfällig',
            ],
            'domain' => [
                'expiring' => 'Domain läuft ab / Verlängerung fehlgeschlagen',
                'transferChanged' => 'Domain-Transferstatus geändert',
                'syncFailed' => 'Domain-Sync fehlgeschlagen',
                'highRiskAction' => 'Hochrisiko-Domainaktion freigegeben',
            ],
            'finance' => [
                'transferFailed' => 'Fakturierungs-Übergabe fehlgeschlagen',
                'bankImportFailed' => 'Bankimport fehlgeschlagen',
                'reconciliationReview' => 'Zahlungsabgleich braucht Klärung',
            ],
            'investment' => [
                'decisionDue' => 'Investitionsentscheidung fällig',
                'decided' => 'Investitionsantrag entschieden',
            ],
            'inventory' => [
                'lotExpiring' => 'Charge läuft ab (MHD)',
            ],
            'operations' => [
                'backupOverdue' => 'Backup überfällig',
                'backupFailed' => 'Backup fehlgeschlagen',
                'restoreTestOverdue' => 'Restore-Test überfällig',
                'updateAvailable' => 'Update verfügbar',
                'updateSecurity' => 'Sicherheitsupdate verfügbar',
                'licenseExpiring' => 'Lizenz läuft bald ab',
                'credentialExpiring' => 'Zugang/Token läuft bald ab',
                'connectionFailing' => 'Verbindung gestört',
                'componentEol' => 'Komponente ohne Support (EOL)',
                'pluginDisabled' => 'Plugin automatisch deaktiviert',
                'schedulerOverdue' => 'Geplante Aufgabe überfällig',
                'maintenanceScheduled' => 'Wartungsfenster angekündigt',
                'problemReportReceived' => 'Neue Fehlermeldung eingegangen',
            ],
            'security' => [
                'integrity' => 'Quelltext-Integrität',
                'threat' => 'Angriffserkennung',
                'newDevice' => 'Anmeldung von neuem Gerät',
                'lockout' => 'Konto vorübergehend gesperrt',
            ],
        ],
        'channel' => [
            'inApp' => 'In-App',
            'mail' => 'E-Mail',
            'push' => 'Push',
            'teams' => 'Microsoft Teams',
            'mattermost' => 'Mattermost',
            'calendar' => 'Kalender',
        ],
    ],

    'customer-query' => [
        'status' => [
            'open' => 'Offen',
            'answered' => 'Beantwortet',
            'closed' => 'Geschlossen',
        ],
    ],

    'shift' => [
        'availability_kind' => [
            'available' => 'Verfügbar',
            'unavailable' => 'Nicht verfügbar',
            'preferred' => 'Bevorzugt',
        ],
        'preference' => [
            'want' => 'Wunsch',
            'avoid' => 'Abneigung',
            'off' => 'Freiwunsch',
        ],
        'exchange_status' => [
            'requested' => 'Beantragt',
            'accepted' => 'Angenommen',
            'approved' => 'Freigegeben',
            'rejected' => 'Abgelehnt',
            'cancelled' => 'Zurückgezogen',
        ],
    ],

    'sla' => [
        'status' => [
            'none' => 'Kein SLA',
            'met' => 'SLA erfüllt',
            'onTrack' => 'SLA im Plan',
            'atRisk' => 'SLA gefährdet',
            'breached' => 'SLA verletzt',
        ],
        'violationKind' => [
            'responseTime' => 'Reaktionszeit',
            'resolutionTime' => 'Lösungszeit',
        ],
        'quotaPeriod' => [
            'month' => 'Monat',
            'quarter' => 'Quartal',
            'year' => 'Jahr',
        ],
    ],

    'safety' => [
        'kind' => [
            'accident' => 'Unfall',
            'nearMiss' => 'Beinaheunfall',
            'hazard' => 'Gefährdung',
            'defect' => 'Mangel',
        ],
        'severity' => [
            'low' => 'Niedrig',
            'medium' => 'Mittel',
            'high' => 'Hoch',
            'critical' => 'Kritisch',
        ],
        'status' => [
            'reported' => 'Gemeldet',
            'investigating' => 'In Untersuchung',
            'measuresDefined' => 'Maßnahmen definiert',
            'closed' => 'Geschlossen',
        ],
    ],

    'open-issue' => [
        'status' => [
            'open' => 'Offen',
            'inProgress' => 'In Bearbeitung',
            'blocked' => 'Blockiert',
            'done' => 'Erledigt',
            'wontDo' => 'Wird nicht erledigt',
            'reopened' => 'Wiedereröffnet',
        ],
        'severity' => [
            'low' => 'Niedrig',
            'medium' => 'Mittel',
            'high' => 'Hoch',
            'critical' => 'Kritisch',
        ],
        'source' => [
            'manual' => 'Manuell',
            'protocolDefect' => 'Aus Protokoll',
            'communicationFollowup' => 'Aus Kommunikation',
            'procedureDeviation' => 'Aus Verfahrensabweichung',
            'customerRejection' => 'Kunden-Ablehnung',
        ],
        'visibility' => [
            'internal' => 'Intern',
            'customer' => 'Kunden-sichtbar',
        ],
    ],
    'communication' => [
        'type' => [
            'call' => 'Telefonat',
            'email' => 'E-Mail',
            'meeting' => 'Vor-Ort-Gespräch',
            'videocall' => 'Videokonferenz',
            'chat' => 'Chat / Messenger',
            'internal' => 'Interne Rücksprache',
            'decision' => 'Entscheidung',
            'letter' => 'Brief / Fax',
            'other' => 'Sonstige',
        ],
        'direction' => [
            'inbound' => 'Eingehend',
            'outbound' => 'Ausgehend',
            'internal' => 'Intern',
        ],
        'visibility' => [
            'internal' => 'Intern',
            'customer' => 'Kunden-sichtbar',
        ],
        'party' => [
            'internal' => 'Intern',
            'customer' => 'Kunde',
            'thirdParty' => 'Dritte',
        ],
    ],
    'knowledge' => [
        'status' => [
            'draft' => 'Entwurf',
            'published' => 'Veröffentlicht',
            'archived' => 'Archiviert',
        ],
        'visibility' => [
            'internal' => 'Intern (gesamte Organisation)',
            'team' => 'Teambezogen',
        ],
    ],
    'form' => [
        'template_status' => [
            'draft' => 'Entwurf',
            'active' => 'Aktiv',
            'archived' => 'Archiviert',
        ],
        'field_type' => [
            'text' => 'Text',
            'textarea' => 'Mehrzeiliger Text',
            'number' => 'Zahl',
            'date' => 'Datum',
            'select' => 'Auswahl',
            'checkbox' => 'Checkbox',
            'photo' => 'Foto',
            'file' => 'Datei',
            'signature' => 'Unterschrift',
        ],
    ],
    'document' => [
        'type' => [
            'contract' => 'Vertrag',
            'testReport' => 'Prüfbericht',
            'certificate' => 'Zertifikat',
            'manual' => 'Bedienungsanleitung',
            'datasheet' => 'Datenblatt',
            'manufacturerDoc' => 'Herstellerdokument',
            'permit' => 'Genehmigung',
            'insurance' => 'Versicherung',
            'invoice' => 'Rechnung',
            'other' => 'Sonstiges',
        ],
        'status' => [
            'draft' => 'Entwurf',
            'active' => 'Aktiv',
            'expired' => 'Abgelaufen',
            'archived' => 'Archiviert',
        ],
    ],
    'protocol' => [
        'status' => [
            'draft' => 'Entwurf',
            'in_review' => 'In Prüfung',
            'signed' => 'Unterschrieben',
            'archived' => 'Archiviert',
            'superseded' => 'Ersetzt',
        ],
        'type' => [
            'acceptance' => 'Abnahme',
            'service' => 'Serviceeinsatz',
            'maintenance' => 'Wartung',
            'handover' => 'Übergabe',
            'defect' => 'Mangelaufnahme',
            'inspection' => 'Begehung',
            'siteVisit' => 'Vor-Ort-Termin',
            'other' => 'Sonstiges',
        ],
        'visibility' => [
            'internal' => 'Intern',
            'customer' => 'Kunden-sichtbar',
        ],
        'item-result' => [
            'ok' => 'In Ordnung',
            'notok' => 'Nicht in Ordnung',
            'n_a' => 'Nicht anwendbar',
            'open' => 'Offen',
        ],
        'signature-role' => [
            'customer' => 'Kunde',
            'contractor' => 'Auftragnehmer',
            'witness' => 'Zeuge',
        ],
        'signature-method' => [
            'onscreen' => 'Bildschirm-Unterschrift',
            'portal' => 'Kundenportal',
            'emailLink' => 'E-Mail-Link',
            'paper' => 'Papier',
        ],
        'item-type' => [
            'group' => 'Abschnitt',
            'text' => 'Freitext',
            'boolean' => 'Ja/Nein-Punkt',
            'choice' => 'Auswahl',
            'multichoice' => 'Mehrfachauswahl',
            'number' => 'Messwert / Zahl',
            'range' => 'Soll-Bereich',
            'date' => 'Datum',
            'datetime' => 'Datum & Uhrzeit',
            'signature' => 'Unterschrift',
            'photo' => 'Pflichtfoto',
            'file' => 'Pflichtdokument',
            'defect' => 'Mangel',
            'measurement.timestamped' => 'Messreihe',
            'procedure_step' => 'Prozedur-Schritt',
            'signoff_internal' => 'Interne Freigabe',
        ],
        'item-photo-phase' => [
            'before' => 'Vorher',
            'after' => 'Nachher',
            'detail' => 'Detail',
            'defect' => 'Mangel',
            'reference' => 'Referenz',
        ],
    ],
    'procedure' => [
        'risk-level' => [
            'low' => 'Niedrig',
            'normal' => 'Normal',
            'high' => 'Hoch',
            'critical' => 'Kritisch',
        ],
        'step-type' => [
            'confirm' => 'Bestätigung',
            'text' => 'Text',
            'number' => 'Zahl/Messwert',
            'choice' => 'Auswahl',
            'photo' => 'Foto',
            'file' => 'Datei',
            'backup' => 'Backup-Nachweis',
            'signature' => 'Unterschrift',
            'material' => 'Materialerfassung',
            'dienstmittel' => 'Dienstmittel',
            'freigabe' => 'Freigabe (Vier-Augen)',
            'messreihe' => 'Messreihe',
            'link_protocol' => 'Protokoll verlinken',
            'link_test' => 'Test verlinken',
            'wait' => 'Wartezeit',
        ],
        'proof-type' => [
            'backup' => 'Backup',
            'file' => 'Datei',
            'photo' => 'Foto',
            'measure' => 'Messwert',
            'signature' => 'Unterschrift',
        ],
        'run-status' => [
            'open' => 'Offen',
            'inProgress' => 'In Bearbeitung',
            'blocked' => 'Blockiert',
            'completed' => 'Abgeschlossen',
            'aborted' => 'Abgebrochen',
        ],
        'step-run-status' => [
            'pending' => 'Offen',
            'done' => 'Erledigt',
            'n_a' => 'Nicht zutreffend',
            'failed' => 'Fehlgeschlagen',
            'deviated' => 'Abweichung',
            'blocked' => 'Blockiert',
        ],
        'backup-scope' => [
            'config' => 'Konfiguration',
            'database' => 'Datenbank',
            'fullSystem' => 'Komplettsystem',
            'customScript' => 'Eigenes Skript',
        ],
        'backup-storage-target' => [
            'attachment' => 'Anhang',
            'external' => 'Externe Ablage',
        ],
        'backup-verify-method' => [
            'checksum' => 'Checksum-Vergleich',
            'restoreCheck' => 'Restore-Test',
            'managerConfirmation' => 'Bestätigung Geschäftsleitung',
        ],
        'deviation-type' => [
            'not_applicable' => 'Nicht anwendbar',
            'not_possible' => 'Nicht möglich',
            'partial' => 'Teilweise erfüllt',
            'alternative_method' => 'Alternative Methode',
            'failed_check' => 'Prüfwert außerhalb Toleranz',
            'material_substitute' => 'Materialersatz',
            'safety_block' => 'Sicherheitsabbruch',
            'customer_decline' => 'Kunde lehnt ab',
        ],
        'deviation-severity' => [
            'low' => 'Gering',
            'medium' => 'Mittel',
            'high' => 'Hoch',
            'critical' => 'Kritisch',
        ],
        'deviation-proposed-action' => [
            'none' => 'Keine Folgeaktion',
            'open_issue' => 'Offener Punkt',
            'new_diary_entry' => 'Neuer Auftrag',
            'requalify' => 'Erneut durchlaufen',
            'escalate' => 'Eskalation',
        ],
    ],
    'duty_plan' => [
        'status' => [
            'draft' => 'Entwurf',
            'published' => 'Veröffentlicht',
        ],
    ],
    'export' => [
        'entity' => [
            'customers' => 'Kunden',
            'projects' => 'Projekte',
            'users' => 'Benutzer',
            'materials' => 'Materialien',
            'scheduled_shifts' => 'Geplante Schichten',
            'tours' => 'Touren',
        ],
        'format' => [
            'csv' => 'CSV',
            'xlsx' => 'XLSX',
        ],
        'state' => [
            'preparing' => 'In Vorbereitung',
            'ready' => 'Bereit',
            'failed' => 'Fehlgeschlagen',
        ],
    ],
    'compliance' => [
        'finding-status' => [
            'open' => 'Offen',
            'acknowledged' => 'Quittiert',
            'resolved' => 'Behoben',
            'accepted' => 'Akzeptiert',
        ],
    ],
    'isms' => [
        'security-incident-category' => [
            'malware' => 'Schadsoftware',
            'phishing' => 'Phishing',
            'dataLoss' => 'Datenverlust',
            'unauthorizedAccess' => 'Unbefugter Zugriff',
            'serviceOutage' => 'Dienstausfall',
            'misconfiguration' => 'Fehlkonfiguration',
            'physical' => 'Physischer Vorfall',
            'other' => 'Sonstiges',
        ],
        'security-incident-status' => [
            'reported' => 'Gemeldet',
            'triage' => 'Bewertung',
            'contained' => 'Eingedämmt',
            'eradicated' => 'Bereinigt',
            'recovered' => 'Wiederhergestellt',
            'closed' => 'Geschlossen',
        ],
        'incident-severity' => [
            'low' => 'Niedrig',
            'medium' => 'Mittel',
            'high' => 'Hoch',
            'critical' => 'Kritisch',
        ],
        'vulnerability-status' => [
            'open' => 'Offen',
            'underReview' => 'In Prüfung',
            'mitigating' => 'In Behebung',
            'resolved' => 'Behoben',
            'accepted' => 'Akzeptiert',
            'notAffected' => 'Nicht betroffen',
        ],
        'exploitability' => [
            'unknown' => 'Unbekannt',
            'underInvestigation' => 'In Untersuchung',
            'exploitable' => 'Ausnutzbar',
            'notExploitable' => 'Nicht ausnutzbar',
        ],
        'vulnerability-source' => [
            'manual' => 'Manuell',
            'advisoryImport' => 'Advisory-Import',
        ],
        'supplier-assessment-status' => [
            'draft' => 'Entwurf',
            'assessed' => 'Bewertet',
            'approved' => 'Freigegeben',
            'flagged' => 'Auffällig',
        ],
        'advisory-format' => [
            'csaf' => 'CSAF',
            'vex' => 'VEX',
        ],
        'audit-package-status' => [
            'draft' => 'Entwurf',
            'finalized' => 'Finalisiert',
        ],
        'audit-kind' => [
            'internal' => 'Intern',
            'external' => 'Extern',
            'supplier' => 'Lieferant',
        ],
        'audit-status' => [
            'planned' => 'Geplant',
            'inPreparation' => 'In Vorbereitung',
            'inProgress' => 'In Durchführung',
            'reportIssued' => 'Bericht erstellt',
            'closed' => 'Abgeschlossen',
        ],
        'finding-kind' => [
            'nonconformityMajor' => 'Hauptabweichung',
            'nonconformityMinor' => 'Nebenabweichung',
            'observation' => 'Beobachtung',
            'improvement' => 'Verbesserungspotenzial',
        ],
        'finding-status' => [
            'open' => 'Offen',
            'inCorrection' => 'In Korrektur',
            'effectivenessCheck' => 'Wirksamkeitsprüfung',
            'closed' => 'Geschlossen',
        ],
        'corrective-action-status' => [
            'open' => 'Offen',
            'inProgress' => 'In Bearbeitung',
            'done' => 'Umgesetzt',
            'effective' => 'Wirksam',
            'ineffective' => 'Nicht wirksam',
        ],
        'review-status' => [
            'draft' => 'Entwurf',
            'approved' => 'Freigegeben',
        ],
        'assessment-kind' => [
            'gross' => 'Brutto',
            'net' => 'Netto',
            'target' => 'Ziel',
        ],
        'assessment-status' => [
            'draft' => 'Entwurf',
            'approved' => 'Freigegeben',
        ],
        'risk-category' => [
            'organizational' => 'Organisatorisch',
            'technical' => 'Technisch',
            'physical' => 'Physisch',
            'personnel' => 'Personell',
            'supplier' => 'Lieferant',
        ],
        'risk-treatment' => [
            'avoid' => 'Vermeiden',
            'mitigate' => 'Vermindern',
            'transfer' => 'Übertragen',
            'accept' => 'Akzeptieren',
        ],
        'risk-status' => [
            'identified' => 'Identifiziert',
            'analyzed' => 'Analysiert',
            'treated' => 'Behandelt',
            'accepted' => 'Akzeptiert',
            'closed' => 'Geschlossen',
        ],
        'requirement-source' => [
            'catalog' => 'Referenzkatalog',
            'custom' => 'Eigene Anforderung',
        ],
        'control-implementation-status' => [
            'open' => 'Offen',
            'partial' => 'Teilweise umgesetzt',
            'implemented' => 'Umgesetzt',
            'notApplicable' => 'Nicht anwendbar',
        ],
        'software-category' => [
            'os' => 'Betriebssystem',
            'application' => 'Anwendung',
            'service' => 'Dienst',
            'library' => 'Bibliothek',
            'other' => 'Sonstiges',
        ],
        'support-status' => [
            'supported' => 'Unterstützt',
            'extendedSupport' => 'Erweiterter Support',
            'endOfLife' => 'End-of-Life',
            'unknown' => 'Unbekannt',
        ],
        'norm-conformity-status' => [
            'notAssessed' => 'Nicht bewertet',
            'gapAnalysisDone' => 'Lückenanalyse durchgeführt',
            'inProgress' => 'In Umsetzung',
            'internallyAuditReady' => 'Intern auditbereit',
            'externalAuditPlanned' => 'Externes Audit geplant',
            'certified' => 'Zertifiziert',
            'certificateSuspended' => 'Zertifikat ausgesetzt',
            'certificateExpired' => 'Zertifikat abgelaufen',
        ],
    ],
    'surcharge' => [
        'kind' => [
            'night' => 'Nacht',
            'saturday' => 'Samstag',
            'sunday' => 'Sonntag',
            'holiday' => 'Feiertag',
            'custom' => 'Benutzerdefiniert',
            'oncall' => 'Bereitschaft',
            'standby' => 'Rufbereitschaft',
            'overtime' => 'Überstunden',
        ],
    ],
    // Kunden-Sonderkonditionen & Abrechnungskonto (Feature 098).
    'billing' => [
        // Belegfluss (Feature 105, MVP-542)
        'direction' => [
            'outgoing' => 'Ausgang',
            'incoming' => 'Eingang',
            'neutral' => 'Ohne Geldwirkung',
        ],
        'kind' => [
            'quote' => 'Angebot',
            'order_confirmation' => 'Auftragsbestätigung',
            'delivery_note' => 'Lieferschein',
            'invoice' => 'Rechnung',
            'down_payment' => 'Abschlagsrechnung',
            'down_payment_deduction' => 'Abschlagsverrechnung',
            'credit_note' => 'Gutschrift',
            'cancellation' => 'Storno',
            'expense' => 'Auslage',
            'other' => 'Sonstiger Beleg',
        ],
        'origin' => [
            'local' => 'Lokal',
            'lexoffice' => 'Lexoffice',
        ],
        'agreement-mode' => [
            'account' => 'Kundenkonto (rechnungslos)',
            'invoice' => 'Monatliche Rechnung',
            'retainer' => 'Pauschale (Lexoffice)',
        ],
        'rate-day-type' => [
            'weekday' => 'Werktag',
            'weekend' => 'Wochenende',
        ],
        'account-payment-source' => [
            'manual' => 'Manuell',
            'bank' => 'Bank',
            'import' => 'Import',
            'lexoffice' => 'Lexoffice',
        ],
    ],
    'finance' => [
        'billing-mode' => [
            'workdiary' => 'WorkDiary (lokal)',
            'lexoffice' => 'Lexoffice führt',
            'datev' => 'DATEV führt',
            'orgamax' => 'orgaMAX führt',
            'sevdesk' => 'sevDesk führt',
            'easybill' => 'easybill führt',
        ],
        'transfer-channel' => [
            'time' => 'Leistungen/Zeit',
            'material' => 'Produkte/Material',
        ],
        'transfer-target' => [
            'lexoffice' => 'Lexoffice',
            'datev' => 'DATEV',
            'orgamax' => 'orgaMAX (Auftrag)',
            'sevdesk' => 'sevDesk (Rechnungsentwurf)',
            'easybill' => 'easybill (Rechnungsentwurf)',
            'file' => 'Datei-Export',
        ],
        'transfer-status' => [
            'draft' => 'Entwurf',
            'confirmed' => 'Bestätigt',
            'transferred' => 'Übergeben',
            'failed' => 'Fehlgeschlagen',
            'voided' => 'Verworfen',
            'cancelled' => 'Storniert',
        ],
        // DATEV-Buchungsstapel (Feature 045, Priorität 2).
        'chart-of-accounts' => [
            'skr03' => 'SKR03',
            'skr04' => 'SKR04',
        ],
        'datev-batch-status' => [
            'draft' => 'Entwurf',
            'exported' => 'Exportiert',
        ],
        // Zahlungsabgleich (Feature 045, Priorität 3).
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
            'credit' => 'Geldeingang',
            'debit' => 'Geldausgang',
        ],
        'balance-check' => [
            'ok' => 'Saldenkette stimmig',
            'mismatch' => 'Saldendifferenz',
            'unknown' => 'Salden unvollständig',
        ],
        'match-status' => [
            'unmatched' => 'Offen',
            'suggested' => 'Vorschläge',
            'matched' => 'Zugeordnet',
            'ignored' => 'Beiseitegelegt',
            'unassignable' => 'Nicht zuordenbar',
            'duplicate' => 'Dublette',
        ],
        'allocation-kind' => [
            'payment' => 'Zahlung',
            'partial' => 'Teilzahlung',
            'overpayment' => 'Überzahlung',
            'reimbursement' => 'Erstattung',
            'chargeback' => 'Rücklastschrift',
            'skonto' => 'Skonto (Erlösschmälerung)',
        ],
    ],

    // Tagesabschluss (MVP-015, docs/tagesabschluss.md §3/§5).
    'dayClosure' => [
        'status' => [
            'open' => 'Offen',
            'closed' => 'Abgeschlossen',
            'correction' => 'In Korrektur',
            'locked' => 'Gesperrt',
        ],
    ],
    'dayCorrection' => [
        'status' => [
            'pending' => 'Ausstehend',
            'approved' => 'Freigegeben',
            'rejected' => 'Abgelehnt',
        ],
    ],

    // Restore-Test-Ergebnis (Feature 017).
    'backup' => [
        // Cloud-Backupziele (Feature 017, Phase 32).
        'provider' => [
            'dropbox' => 'Dropbox',
            'microsoft' => 'Microsoft OneDrive/SharePoint',
            'google' => 'Google Drive',
            'nextcloud' => 'Nextcloud',
        ],
        'target_status' => [
            'draft' => 'Entwurf',
            'active' => 'Aktiv',
            'reauth_required' => 'Neu anmelden',
            'blocked' => 'Blockiert',
            'disabled' => 'Deaktiviert',
        ],
        'generation_status' => [
            'building' => 'Wird erstellt',
            'uploading' => 'Wird hochgeladen',
            'committed' => 'Abgeschlossen',
            'verified' => 'Verifiziert',
            'verify_failed' => 'Verifikation fehlgeschlagen',
            'failed' => 'Fehlgeschlagen',
        ],
        'retention_class' => [
            'daily' => 'Täglich',
            'weekly' => 'Wöchentlich',
            'monthly' => 'Monatlich',
        ],
        'restore-test-result' => [
            'passed' => 'Bestanden',
            'partial' => 'Mit Auflagen',
            'failed' => 'Fehlgeschlagen',
        ],
    ],

    // Fälligkeits-Aktion eines Wartungsplans (Feature 010 → Rang 43).
    'maintenance' => [
        'due_action' => [
            'none' => 'Nur Hinweis (kein Vorgang)',
            'ticket' => 'Service-Ticket anlegen',
        ],
    ],

    'security' => [
        'integrity_check_status' => [
            'baseline' => 'Baseline erzeugt',
            'ok' => 'In Ordnung',
            'deviation' => 'Abweichung',
            'missing_baseline' => 'Keine Baseline',
            'error' => 'Fehler',
        ],
    ],

    'passenger' => [
        'operation_mode' => [
            'taxi' => 'Taxenverkehr (§ 47 PBefG)',
            'rental_car' => 'Mietwagenverkehr (§ 49 PBefG)',
            'pooled_on_demand' => 'Gebündelter Bedarfsverkehr (§ 50 PBefG)',
        ],
        'ride_status' => [
            'requested' => 'Angefragt',
            'accepted' => 'Angenommen',
            'assigned' => 'Disponiert',
            'en_route_pickup' => 'Anfahrt',
            'waiting' => 'Wartend',
            'occupied' => 'Besetzt',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Storniert',
            'no_show' => 'Fahrgast nicht erschienen',
            'aborted' => 'Abgebrochen',
        ],
        'price_kind' => [
            'tariff' => 'Tarif',
            'fixed_price' => 'Festpreis',
            'contract' => 'Vertragspreis',
        ],
        'order_channel' => [
            'hail' => 'Winkkunde / Halteplatz',
            'phone' => 'Telefon',
            'app' => 'App',
            'web' => 'Web',
            'mediator' => 'Vermittlungszentrale',
            'contract' => 'Rahmenvertrag',
        ],
    ],
    'print' => [
        'order_status' => [
            'data_check' => 'Datenprüfung',
            'approved' => 'Freigegeben',
            'in_production' => 'In Produktion',
            'quality_check' => 'Qualitätskontrolle',
            'rework' => 'Nacharbeit',
            'ready' => 'Bereit zur Ausgabe',
            'issued' => 'Ausgegeben',
            'cancelled' => 'Storniert',
        ],
        'preflight_status' => [
            'pending' => 'Ausstehend',
            'passed' => 'Bestanden',
            'warnings' => 'Mit Warnungen',
            'failed' => 'Fehlgeschlagen',
            'overridden' => 'Begründet übersteuert',
        ],
        'output_kind' => [
            'pickup' => 'Abholung',
            'shipping' => 'Versand',
            'counter' => 'Tresenverkauf',
        ],
    ],
];

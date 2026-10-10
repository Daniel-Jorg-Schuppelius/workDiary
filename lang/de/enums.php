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
    'ai' => [
        'family' => ['llm' => 'Sprachmodell (LLM)', 'translation' => 'Übersetzung'],
        'verb' => ['formulate' => 'Formulieren', 'summarize' => 'Zusammenfassen', 'classify' => 'Klassifizieren', 'explain' => 'Erklären', 'find' => 'Finden', 'translate' => 'Übersetzen', 'extract' => 'Extrahieren'],
        'provider' => ['anthropic' => 'Anthropic Claude', 'openai' => 'OpenAI', 'gemini' => 'Google Gemini', 'azure_openai' => 'Azure OpenAI', 'openai_compatible' => 'OpenAI-kompatibel (generisch)', 'ollama' => 'Ollama (lokal)', 'deepl' => 'DeepL', 'azure_translator' => 'Azure Translator', 'google_translate' => 'Google Cloud Translation', 'libretranslate' => 'LibreTranslate (lokal)', 'fake' => 'Test-Provider'],
        'connection_status' => ['draft' => 'Entwurf', 'active' => 'Aktiv', 'blocked' => 'Gesperrt'],
        'memory_type' => ['glossary' => 'Glossar', 'style_rule' => 'Stil-Regel', 'example' => 'Beispielpaar'],
        'sensitivity' => ['low' => 'Niedrig', 'medium' => 'Mittel', 'high' => 'Hoch'],
        'ai_text_suggestion_status' => ['proposed' => 'Vorgeschlagen', 'accepted' => 'Angenommen', 'edited' => 'Geändert', 'rejected' => 'Abgelehnt', 'expired' => 'Abgelaufen'],
    ],
    'domain' => [
        'environment' => ['ote' => 'OT&E (Test/Pilot)', 'production' => 'Produktiv'],
        'connection_status' => ['draft' => 'Entwurf', 'active' => 'Aktiv', 'blocked' => 'Gesperrt'],
        'sync_status' => ['current' => 'Aktuell', 'stale' => 'Veraltet', 'pending' => 'Ausstehend', 'conflict' => 'Konflikt', 'unknown' => 'Unklar'],
        'renewal_mode' => ['autorenew' => 'Auto-Renew', 'autoexpire' => 'Auto-Expire', 'autodelete' => 'Auto-Delete', 'renewonce' => 'Einmal verlängern'],
        'command_status' => ['draft' => 'Entwurf', 'approved' => 'Freigegeben', 'pending' => 'Ausstehend', 'confirmed' => 'Bestätigt', 'failed' => 'Fehlgeschlagen', 'unknown' => 'Unklar', 'conflict' => 'Konflikt'],
        'capability_area' => ['authentication' => 'Authentifizierung', 'subuser' => 'Subuser', 'domains' => 'Domains', 'contacts' => 'Kontakte', 'nameservers' => 'Nameserver', 'dns' => 'DNS-Zonen', 'events' => 'Ereignisse', 'renewal' => 'Renewal', 'transfer' => 'Transfer', 'accounting' => 'Accounting', 'invoices' => 'Rechnungen'],
    ],
    'approval' => [
        'step-kind' => ['commercial' => 'Kaufmännisch', 'technical' => 'Fachlich', 'hr' => 'HR', 'management' => 'Geschäftsleitung'],
        'approval_decision' => ['approved' => 'Freigegeben', 'rejected' => 'Abgelehnt'],
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
        'asset_block_reason' => ['defect' => 'Defekt', 'safety' => 'Arbeitsschutz', 'recall' => 'Rückruf', 'inspection_overdue' => 'Prüfung überfällig', 'inspection_failed' => 'Prüfung nicht bestanden', 'rental_damage' => 'Verleihschaden', 'policy_hold' => 'Interne Sperre', 'manual' => 'Manuell gesperrt', 'maintenance' => 'Wartung', 'other' => 'Sonstiger Grund'],
        'asset_status' => ['active' => 'Aktiv', 'in_maintenance' => 'In Wartung', 'in_repair' => 'In Reparatur', 'blocked' => 'Gesperrt', 'reserved' => 'Reserviert', 'loan_out' => 'Ausgeliehen', 'replaced' => 'Ersetzt', 'decommissioned' => 'Außer Betrieb', 'lost' => 'Verloren'],
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
        'classification_domain' => ['entry_type' => 'Auftragstypen', 'activity' => 'Tätigkeiten', 'defect_type' => 'Fehlertypen', 'root_cause' => 'Ursachen', 'result' => 'Ergebnisse', 'priority' => 'Prioritäten', 'goodwill_reason' => 'Kulanzgründe', 'rework_reason' => 'Nacharbeitsgründe', 'product_group' => 'Produktgruppen', 'dienstmittel_type' => 'Dienstmitteltypen', 'allergen' => 'Allergene', 'trade' => 'Gewerke', 'permit_type' => 'Genehmigungsarten', 'waste_code' => 'Abfallschlüssel (AVV)', 'customer_group' => 'Kundengruppen'],
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
                'waitlisted' => 'Warteliste',
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
    'asset_inspection_round' => [
        'status' => [
            'open' => 'Offen',
            'closed' => 'Abgeschlossen',
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
    'access' => [
        'medium_status' => ['in_stock' => 'Im Lager', 'issued' => 'Ausgegeben', 'lost' => 'Verloren', 'blocked' => 'Gesperrt', 'retired' => 'Ausgemustert'],
        'medium_type' => ['transponder' => 'Transponder', 'card' => 'Karte', 'code' => 'Code'],
    ],
    'sales' => [
        'lead_status' => ['new' => 'Neu', 'contacted' => 'Kontaktiert', 'qualified' => 'Qualifiziert', 'converted' => 'Konvertiert', 'discarded' => 'Verworfen'],
        'lead_source' => ['referral' => 'Empfehlung', 'web' => 'Web', 'trade_fair' => 'Messe', 'phone' => 'Telefon', 'booking' => 'Terminbuchung', 'other' => 'Sonstige'],
    ],
    // Sicherheitseinbehalte (Feature 113, MVP-602).
    // Bürgschaftsregister (Feature 114, MVP-603).
    // Gewährleistungsfristen (Feature 115, MVP-604).
    'warranty_side' => [
        'owed' => 'Eigene Haftung',
        'claimable' => 'Einforderbar (Sub)',
    ],
    'warranty_basis' => [
        'bgb_5y' => 'BGB, 5 Jahre',
        'vob_4y' => 'VOB/B, 4 Jahre',
        'custom' => 'Frei vereinbart',
    ],
    'warranty_status' => [
        'open' => 'Offen',
        'closed' => 'Abgeschlossen',
        'claimed' => 'Gerügt',
    ],
    // Pflichtnachweise (Feature 117, MVP-606).
    'credential_status' => [
        'ok' => 'Vollständig',
        'expiring' => 'Läuft ab',
        'missing' => 'Fehlt',
        'expired' => 'Abgelaufen',
    ],
    'guarantee_direction' => [
        'issued' => 'Gestellt',
        'received' => 'Erhalten',
    ],
    'guarantee_kind' => [
        'performance' => 'Vertragserfüllungsbürgschaft',
        'warranty' => 'Gewährleistungsbürgschaft',
        'advance_payment' => 'Anzahlungsbürgschaft',
        'defects' => 'Mängelansprüchebürgschaft',
    ],
    'guarantee_status' => [
        'active' => 'Aktiv',
        'returned' => 'Zurückgegeben',
        'drawn' => 'Gezogen',
        'expired' => 'Abgelaufen',
    ],
    'payment_run_kind' => [
        'credit_transfer' => 'Sammelüberweisung',
        'direct_debit' => 'Sammeleinzug',
    ],
    'payment_run_status' => [
        'draft' => 'Entwurf',
        'released' => 'Freigegeben',
        'exported' => 'Exportiert',
        'cancelled' => 'Storniert',
    ],
    'sepa_mandate_kind' => [
        'one_off' => 'Einmalig',
        'recurring' => 'Wiederkehrend',
    ],
    'sepa_mandate_status' => [
        'active' => 'Aktiv',
        'revoked' => 'Widerrufen',
        'expired' => 'Abgelaufen',
    ],
    'retention_base' => [
        'net' => 'Nettobetrag',
        'gross' => 'Bruttobetrag',
    ],
    'retention_kind' => [
        'warranty' => 'Gewährleistungseinbehalt',
        'performance' => 'Vertragserfüllungseinbehalt',
    ],
    'retention_status' => [
        'open' => 'Offen',
        'released' => 'Freigegeben',
        'secured' => 'Durch Bürgschaft abgelöst',
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
            'club' => [
                'eventReminder' => 'Erinnerung an Vereinstermin',
                'eventRescheduled' => 'Vereinstermin verschoben',
                'eventCancelled' => 'Vereinstermin abgesagt',
                'waitlistPromoted' => 'Von der Vereins-Warteliste nachgerückt',
                'horseUnavailable' => 'Pferd nicht verfügbar — Reitstunde neu planen',
            ],
            'learning' => [
                'enrolled' => 'Schulung zugewiesen',
                'dueSoon' => 'Schulung bald fällig',
                'overdue' => 'Schulung überfällig',
                'submissionReceived' => 'Abgabe wartet auf Bewertung',
                'graded' => 'Bewertung liegt vor',
                'certificateIssued' => 'Zertifikat ausgestellt',
                'waitlistPromoted' => 'Von der Warteliste nachgerückt',
                'bookingDecided' => 'Kursbuchung entschieden',
                'timeApprovalRequested' => 'Lernzeit wartet auf Freigabe',
                'questionAsked' => 'Frage an den Trainer gestellt',
            ],
            'crisis' => [
                'alert' => 'Krisenalarm',
            ],
            'claim' => [
                'escalation' => 'Reklamation überfällig',
                'pattern' => 'Auffälliges Reklamationsmuster',
                'customerNote' => 'Nachreichung zu einer Reklamation eingegangen',
            ],
            'procedure' => [
                'deviationEscalated' => 'Prozedur-Abweichung eskaliert',
            ],
            'report' => [
                'warning' => 'Frühwarnung aus den Auswertungen',
            ],
            'rental' => [
                'returnOverdue' => 'Verleih-Rückgabe überfällig',
                'requested' => 'Verleih-Anfrage aus dem Portal eingegangen',
                'geofenceDeviation' => 'Verliehenes Gerät außerhalb des Einsatzorts',
            ],
            'appointment' => [
                'requested' => 'Terminanfrage aus dem Portal eingegangen',
                'canceled' => 'Termin im Portal storniert',
            ],
            'hrFile' => [
                'ackRequested' => 'Lesebestätigung für die Personalakte erbeten',
                'submissionReceived' => 'Unterlage für eine Personalakte eingereicht',
                'submissionDecided' => 'Eingereichte Unterlage entschieden',
            ],
            'assetFinance' => [
                'deadline' => 'Leasingfrist fällig',
            ],
            'contract' => [
                'deadlineDue' => 'Vertragsfrist fällig',
                'signatureReceived' => 'Nachweis zur Vereinbarung eingegangen',
                'indexationProposed' => 'Indexanpassung vorgeschlagen',
            ],
            'accounting' => [
                'recurringOverdue' => 'Wiederkehrender Vorgang überfällig',
                'filingDue' => 'Steuerliche Meldefrist fällig',
            ],
            'invoice' => [
                'recurringDraft' => 'Rechnungsentwurf aus Abrechnungsplan',
            ],
            'fleet' => [
                'licenseCheckDue' => 'Führerscheinkontrolle fällig',
            ],
            'drivingTime' => [
                'violation' => 'Lenk-/Ruhezeit-Befund',
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
                'openReminder' => 'Erinnerung: Stempelung noch offen',
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
                'assessmentReviewDue' => 'Gefährdungsbeurteilung: Wiedervorlage fällig',
                'instructionDue' => 'Wiederholungsunterweisung fällig',
                'checkupDue' => 'Arbeitsmedizinische Vorsorge fällig',
            ],
            'training' => [
                'due' => 'Pflichtschulung fällig',
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
                'intakeSubmitted' => 'Kunde hat eine Anfrage eingereicht',
                'intakeActivity' => 'Neue Aktivität an einem Kundeneingang',
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
                'retentionReleaseDue' => 'Sicherheitseinbehalt freizugeben',
                'guaranteeExpiring' => 'Bürgschaft läuft ab',
                'guaranteeReturnDue' => 'Bürgschaft zurückfordern',
                'transferFailed' => 'Fakturierungs-Übergabe fehlgeschlagen',
                'bankImportFailed' => 'Bankimport fehlgeschlagen',
                'reconciliationReview' => 'Zahlungsabgleich braucht Klärung',
            ],
            'investment' => [
                'decisionDue' => 'Investitionsentscheidung fällig',
                'proposed' => 'Investition vorgeschlagen',
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
                'queueDegraded' => 'Warteschlange gestört',
                'maintenanceScheduled' => 'Wartungsfenster angekündigt',
                'problemReportReceived' => 'Neue Fehlermeldung eingegangen',
                'cloudIntakeReauth' => 'Cloud-Eingang: neue Anmeldung nötig',
                'cloudIntakeQuarantined' => 'Cloud-Eingang: Importe abgewiesen',
            ],
            'quote' => [
                'followUpDue' => 'Angebot: Nachfassen fällig',
                'expiringWithoutReaction' => 'Angebot läuft ohne Reaktion ab',
            ],
            'weather' => [
                'warning' => 'Wetterwarnung für Einsatz',
            ],
            'warranty' => [
                'expiring' => 'Gewährleistung läuft ab',
                'subcontractorEndsFirst' => 'Sub-Frist endet vor der eigenen',
            ],
            'supplier' => ['credentialExpiring' => 'Pflichtnachweis läuft ab'],
            'security' => [
                'integrity' => 'Quelltext-Integrität',
                'threat' => 'Angriffserkennung',
                'newDevice' => 'Anmeldung von neuem Gerät',
                'lockout' => 'Konto vorübergehend gesperrt',
            ],
            'diary' => [
                'commentCreated' => 'Neuer Kommentar im Auftragsbuch',
                'problem' => 'Auftragsbuch-Eintrag mit Problem',
                'completed' => 'Auftragsbuch-Eintrag erledigt',
                'attachmentAdded' => 'Neuer Anhang im Auftragsbuch',
            ],
            'emergency' => ['assigned' => 'Notdienst zugewiesen'],
            'timesheet' => ['signed' => 'Stundenzettel signiert'],
            'chat' => [
                'message' => 'Chat-Nachricht',
                'reminder' => 'Chat-Erinnerung',
            ],
        ],
        'channel' => [
            'inApp' => 'In-App',
            'mail' => 'E-Mail',
            'push' => 'Push',
            'teams' => 'Microsoft Teams',
            'mattermost' => 'Mattermost',
            'calendar' => 'Kalender',
            'sms' => 'SMS',
        ],
        'sms_status' => [
            'sent' => 'Versendet',
            'delivered' => 'Zugestellt',
            'failed' => 'Fehlgeschlagen',
            'blocked' => 'Nicht versendet',
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

    'training' => [
        'provider-kind' => [
            'internal' => 'Intern',
            'external' => 'Extern',
        ],
        'requirement-subject' => [
            'role' => 'Rolle',
            'team' => 'Tätigkeitsbereich (Team)',
        ],
        'assignment-state' => [
            'fulfilled' => 'Erfüllt',
            'planned' => 'Geplant',
            'due' => 'Fällig',
            'overdue' => 'Überfällig',
        ],
    ],

    'safety' => [
        'assessment-status' => [
            'draft' => 'Entwurf',
            'inReview' => 'In Prüfung',
            'approved' => 'Freigegeben',
            'archived' => 'Archiviert',
        ],
        'checkup-kind' => [
            'mandatory' => 'Pflichtvorsorge',
            'offered' => 'Angebotsvorsorge',
            'requested' => 'Wunschvorsorge',
        ],
        'signature-method' => [
            'confirmed' => 'Bestätigungs-Klick',
            'drawn' => 'Unterschrift (Bild)',
        ],
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
            'patrolDeviation' => 'Aus Rundgangs-Abweichung',
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
            'general' => 'Allgemein',
            'production' => 'Fertigung',
        ],
        'direction' => [
            'inbound' => 'Eingehend',
            'outbound' => 'Ausgehend',
            'internal' => 'Intern',
        ],
        'visibility' => [
            'internal' => 'Intern',
            'customer' => 'Kunden-sichtbar',
            'private' => 'Privat (nur ich)',
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
    ],
    'fields' => [
        'type' => [
            'text' => 'Text',
            'textarea' => 'Mehrzeiliger Text',
            'number' => 'Zahl',
            'boolean' => 'Checkbox',
            'choice' => 'Auswahl',
            'multichoice' => 'Mehrfachauswahl',
            'date' => 'Datum',
            'datetime' => 'Datum und Uhrzeit',
            'scale' => 'Skala',
            'photo' => 'Foto',
            'file' => 'Datei',
            'signature' => 'Unterschrift',
            'section' => 'Abschnitt',
            'measurement' => 'Messwert',
        ],
    ],
    // Digitale Personalakte (Feature 141, MVP-708) — bewusst ohne Gesundheitskategorie.
    'hr_submission_status' => [
        'submitted' => 'Eingereicht',
        'accepted' => 'Übernommen',
        'rejected' => 'Abgelehnt',
    ],
    'hr_document_category' => [
        'contract' => 'Arbeitsvertrag',
        'amendment' => 'Vertragsänderung / Zusatzvereinbarung',
        'certificate' => 'Zeugnis / Bescheinigung',
        'training' => 'Schulung / Qualifikation',
        'warning' => 'Abmahnung',
        'idDocument' => 'Ausweis- / Nachweisdokument',
        'payrollReference' => 'Lohnbezug (Verweisdokument)',
        'other' => 'Sonstiges',
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
            // Verschachtelt, weil der Enum-Wert einen Punkt traegt und
            // Arr::get segmentweise aufloest: ein flacher Schluessel
            // 'measurement.timestamped' wird nie gefunden.
            'measurement' => ['timestamped' => 'Messreihe'],
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
        'isms_audit_program_status' => ['active' => 'aktiv', 'completed' => 'abgeschlossen', 'cancelled' => 'abgebrochen'],
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
            'orgamax' => 'orgaMAX',
            'sevdesk' => 'sevDesk',
            'easybill' => 'easybill',
            'invoiceplane' => 'InvoicePlane',
            'jtl_wawi' => 'JTL-Wawi',
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
        // Versteuerungsart (Feature 125, MVP-679).
        'taxation-method' => [
            'debit' => 'Soll-Versteuerung',
            'credit' => 'Ist-Versteuerung',
        ],
        // Steuerliche Meldepflichten (Feature 125, MVP-686).
        'filing-obligation-kind' => [
            'vat_advance' => 'Umsatzsteuer-Voranmeldung',
            'special_prepayment' => 'Sondervorauszahlung',
            'recapitulative' => 'Zusammenfassende Meldung',
            'annual_return' => 'Umsatzsteuer-Jahreserklärung',
        ],
        'filing-obligation-status' => [
            'open' => 'Offen',
            'submitted' => 'Abgegeben',
            'not_required' => 'Nicht erforderlich',
        ],
        // Voranmeldungszeitraum der Umsatzsteuer (Feature 125, MVP-684).
        'vat-filing-interval' => [
            'monthly' => 'Monatlich',
            'quarterly' => 'Vierteljährlich',
            'annual' => 'Nur Jahreserklärung',
            'none' => 'Keine Voranmeldung',
        ],
        // Zeilen der Anlage EÜR (Feature 125, MVP-680).
        'euer-category' => [
            'income' => 'Betriebseinnahmen',
            'income_vat' => 'Vereinnahmte Umsatzsteuer',
            'private_use' => 'Private Nutzung',
            'expense' => 'Betriebsausgaben',
            'depreciation' => 'Abschreibungen',
            'low_value_asset' => 'Geringwertige Wirtschaftsgüter',
            'input_tax' => 'Gezahlte Vorsteuer',
            'paid_vat' => 'Gezahlte Umsatzsteuer',
            'limited_deductible' => 'Beschränkt abziehbar',
            'not_deductible' => 'Nicht abziehbar',
        ],
        // Wiederkehrende Vorgänge (Feature 125, MVP-675).
        'recurring-template-kind' => [
            'document_expectation' => 'Belegerwartung',
            'posting_template' => 'Buchungsvorlage',
        ],
        'recurring-interval' => [
            'monthly' => 'Monatlich',
            'quarterly' => 'Vierteljährlich',
            'semi_annually' => 'Halbjährlich',
            'annually' => 'Jährlich',
        ],
        'recurring-run-status' => [
            'expected' => 'Beleg erwartet',
            'draft_created' => 'Entwurf erzeugt',
            'fulfilled' => 'Erfüllt',
            'blocked' => 'Blockiert',
            'skipped' => 'Übersprungen',
        ],
        'recurring-template-status' => [
            'active' => 'Aktiv',
            'paused' => 'Pausiert',
            'ended' => 'Beendet',
        ],
        // Offene Posten (Feature 125, MVP-674).
        'open-item-direction' => [
            'receivable' => 'Forderung',
            'payable' => 'Verbindlichkeit',
        ],
        'open-item-status' => [
            'open' => 'Offen',
            'partially_settled' => 'Teilweise ausgeglichen',
            'settled' => 'Ausgeglichen',
            'disputed' => 'Strittig',
        ],
        'settlement-kind' => [
            'payment' => 'Zahlung',
            'discount' => 'Skonto',
            'retention' => 'Einbehalt',
            'write_off' => 'Ausbuchung',
            'overpayment' => 'Überzahlung',
            'reversal' => 'Rückbuchung',
        ],
        // Quellenadapter und Buchungsregeln (Feature 125, MVP-673).
        'ebics-connection-status' => [
            'draft' => 'Entwurf',
            'keys_created' => 'Schlüssel erzeugt',
            'initialized' => 'an Bank gesendet',
            'active' => 'freigeschaltet',
            'suspended' => 'gesperrt',
        ],
        'posting-source-kind' => [
            'sales_invoice' => 'Ausgangsrechnung',
            'incoming_invoice' => 'Eingangsrechnung',
            'expense' => 'Auslage',
            'cash_entry' => 'Kassenbuch',
            'payment' => 'Zahlung',
            'depreciation' => 'Abschreibung (AfA)',
            'asset_disposal' => 'Anlagenabgang',
            'online_payment' => 'Online-Zahlung',
        ],
        'direct-booking-kind' => [
            'open_item_settlement' => 'Skonto/Ausbuchung',
            'clearing' => 'Klärungsbuchung',
            'internal_transfer' => 'Interne Umbuchung',
            'opening_balance' => 'Startsalden',
            'vat_special_prepayment' => 'Sondervorauszahlung',
            'reversal' => 'Storno',
        ],
        'posting-account-role' => [
            'receivable' => 'Forderung',
            'revenue' => 'Erlös',
            'tax_output' => 'Umsatzsteuer',
            'payable' => 'Verbindlichkeit',
            'expense' => 'Aufwand',
            'tax_input' => 'Vorsteuer',
            'cash' => 'Kasse',
            'employee_payable' => 'Verbindlichkeit Mitarbeitende',
            'bank' => 'Bank',
            'discount' => 'Skonto',
            'fixed_asset' => 'Anlagenkonto',
            'depreciation' => 'AfA-Aufwand',
            'disposal_loss' => 'Abgang Restbuchwert (Buchverlust)',
            'disposal_gain' => 'Abgang Restbuchwert (Buchgewinn)',
            'payment_transit' => 'Geldtransit (Zahlungsanbieter)',
            'payment_fees' => 'Nebenkosten des Geldverkehrs',
        ],
        // Buchungskern (Feature 125, MVP-672).
        'balance-side' => [
            'debit' => 'Soll',
            'credit' => 'Haben',
        ],
        'account-type' => [
            'asset' => 'Aktiva',
            'liability' => 'Passiva',
            'equity' => 'Eigenkapital',
            'income' => 'Erträge',
            'expense' => 'Aufwendungen',
        ],
        'bwa-group' => [
            'revenue' => 'Umsatzerlöse',
            'inventory_change' => 'Bestandsveränderung / aktivierte Eigenleistung',
            'material' => 'Materialaufwand',
            'other_operating_income' => 'Sonstige betriebliche Erlöse',
            'personnel' => 'Personalkosten',
            'premises' => 'Raumkosten',
            'operating_taxes' => 'Betriebliche Steuern',
            'insurance_fees' => 'Versicherungen / Beiträge',
            'vehicle' => 'Kfz-Kosten',
            'marketing_travel' => 'Werbe- / Reisekosten',
            'goods_dispatch' => 'Kosten der Warenabgabe',
            'depreciation' => 'Abschreibungen',
            'repairs' => 'Reparatur / Instandhaltung',
            'other_costs' => 'Sonstige Kosten',
            'interest_expense' => 'Zinsaufwand',
            'neutral_expense' => 'Sonstiger neutraler Aufwand',
            'interest_income' => 'Zinserträge',
            'neutral_income' => 'Sonstiger neutraler Ertrag',
            'income_taxes' => 'Steuern vom Einkommen und Ertrag',
        ],
        'accounting-entry-status' => [
            'draft' => 'Entwurf',
            'ready' => 'Geprüft',
            'posted' => 'Festgeschrieben',
            'reversed' => 'Storniert',
        ],
        'tax-code-direction' => [
            'output' => 'Umsatzsteuer',
            'input' => 'Vorsteuer',
            'none' => 'Ohne Steuer',
        ],
        // Lokale Buchhaltung (Feature 125, MVP-671).
        'accounting-sovereignty' => [
            'preaccounting' => 'Belegvorstufe (kein Hauptbuch)',
            'local' => 'workDiary führt',
            'external' => 'Externes System führt',
        ],
        'profit-determination' => [
            'euer' => 'Einnahmenüberschussrechnung',
            'double_entry' => 'Doppelte Buchführung',
        ],
        // Anlagenregister (Feature 133, MVP-698).
        'fixed-asset-status' => [
            'active' => 'Aktiv',
            'disposed' => 'Abgegangen',
        ],
        'fixed-asset-disposal-kind' => [
            'sale' => 'Verkauf',
            'scrap' => 'Verschrottung / Entsorgung',
        ],
        'depreciation-method' => [
            'linear' => 'Linear',
            'immediate' => 'Sofortabschreibung (GWG)',
            'pool' => 'Sammelposten',
            'declining' => 'Degressiv',
        ],
        'accounting-period-status' => [
            'open' => 'Offen',
            'soft_closed' => 'Vorläufig geschlossen',
            'closed' => 'Geschlossen',
        ],
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
        'budget-release-status' => [
            'draft' => 'Entwurf',
            'released' => 'Freigegeben',
        ],
        'liquidity-plan-recurrence' => [
            'once' => 'Einmalig',
            'monthly' => 'Monatlich',
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
        // GoBD-Z3-Lauf (Feature 063, MVP-722).
        'gobd-export-status' => [
            'queued' => 'In Warteschlange',
            'running' => 'Läuft',
            'ready' => 'Fertig',
            'failed' => 'Fehlgeschlagen',
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
        // Verfahrensdokumentation (Feature 134, MVP-699)
        'procedure-documentation-status' => [
            'in_review' => 'In Prüfung',
            'draft' => 'Entwurf',
            'published' => 'Veröffentlicht',
        ],
    ],

    // Tagesabschluss (MVP-015, WorkDiary-Architecture/tagesabschluss.md §3/§5).
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
            'webdav' => 'WebDAV (eigener Server)',
            's3' => 'S3-kompatibler Speicher',
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
    // Lernplattform (Feature 149)
    'learning' => [
        'booking-status' => [
            'requested' => 'Angefragt',
            'confirmed' => 'Zugesagt',
            'rejected' => 'Abgesagt',
            'cancelled' => 'Storniert',
        ],
        'submission-status' => [
            'draft' => 'Entwurf',
            'submitted' => 'Abgegeben',
            'returned' => 'Zurückgegeben',
            'graded' => 'Bewertet',
        ],
        'question-kind' => [
            'single' => 'Einfachauswahl',
            'multiple' => 'Mehrfachauswahl',
            'true_false' => 'Wahr/Falsch',
            'short_text' => 'Freitext kurz',
            'cloze' => 'Lückentext',
            'sort' => 'Sortieren',
            'matching' => 'Zuordnung',
            'essay' => 'Aufsatz',
            'hotspot' => 'Bildmarkierung',
            'matrix' => 'Matrix-Zuordnung',
            'assessment' => 'Selbsteinschätzung',
        ],
        'feedback-mode' => [
            'immediate' => 'Sofort',
            'end' => 'Am Ende',
            'none' => 'Keine',
        ],
        'block-kind' => [
            'heading' => 'Überschrift',
            'text' => 'Text',
            'callout' => 'Hinweis',
            'checklist' => 'Checkliste',
            'image' => 'Bild',
            'file' => 'Datei',
            'video' => 'Video',
            'embed' => 'Einbettung',
            'knowledge' => 'Wissensartikel',
            'gallery' => 'Galerie',
            'audio' => 'Audio',
            'code' => 'Code',
            'accordion' => 'Akkordeon',
            'table' => 'Tabelle',
            'procedure' => 'Prozedur',
            'question' => 'Verständnisfrage',
            'divider' => 'Trenner',
        ],
        'enrollment-status' => [
            'assigned' => 'Zugewiesen',
            'in_progress' => 'In Bearbeitung',
            'completed' => 'Abgeschlossen',
            'failed' => 'Nicht bestanden',
            'expired' => 'Abgelaufen',
            'cancelled' => 'Storniert',
        ],
        'course-kind' => [
            'course' => 'Kurs',
            'exam' => 'Prüfung ohne Kurs',
        ],
        'enrollment-source' => [
            'exam' => 'Anrechnung',
            'requirement' => 'Pflichtmatrix',
            'manual' => 'Manuell',
            'self' => 'Selbst gewählt',
            'booking' => 'Buchung',
            'rule' => 'Regel',
            'path' => 'Lernpfad',
            'import' => 'Importiert',
        ],
        'translation-status' => [
            'draft' => 'Entwurf',
            'approved' => 'Freigegeben',
        ],
        'progress-status' => [
            'open' => 'Offen',
            'started' => 'Begonnen',
            'completed' => 'Abgeschlossen',
        ],
        'course-status' => [
            'draft' => 'Entwurf',
            'review' => 'In Prüfung',
            'released' => 'Freigegeben',
            'archived' => 'Archiviert',
        ],
        'audience' => [
            'internal' => 'Intern',
            'external' => 'Externe Beteiligte',
            'customer' => 'Kunden',
            'public' => 'Öffentlich',
        ],
        'access-kind' => [
            'open' => 'Offen',
            'enrolled' => 'Eingeschrieben',
            'bookable' => 'Buchbar',
            'closed' => 'Gesperrt',
        ],
        'unit-kind' => [
            'content' => 'Inhalt',
            'quiz' => 'Prüfung',
            'assignment' => 'Aufgabe',
            'procedure' => 'Prozedur',
            'event' => 'Termin',
            'scorm' => 'SCORM-Paket',
            'cmi5' => 'cmi5-Kurs',
            'lti' => 'LTI-Inhalt',
            'survey' => 'Umfrage',
            'external' => 'Externer Inhalt',
        ],
        'time-policy' => [
            'work_time_required' => 'Nur während der Arbeitszeit',
            'always_counts' => 'Zählt immer als Arbeitszeit',
            'approval_required' => 'Außerhalb nur mit Freigabe',
            'voluntary_unpaid' => 'Freiwillig, unbezahlt',
        ],
        'instruction-suitability' => [
            'supplementary' => 'Nur ergänzend',
            'with_questions' => 'Mit Rückfragemöglichkeit',
            'with_presence' => 'Mit Präsenzteil',
        ],
    ],
    'media' => [
        'state' => [
            'pending' => 'Wartet',
            'processing' => 'Wird verarbeitet',
            'ready' => 'Bereit',
            'failed' => 'Fehlgeschlagen',
        ],
        'rendition-kind' => [
            'video' => 'Videofassung',
            'poster' => 'Vorschaubild',
            'subtitle' => 'Untertitel',
        ],
        'subtitle-source' => [
            'manual' => 'von Hand',
            'machine' => 'maschinell',
        ],
        'dictation-status' => [
            'pending' => 'Wartet',
            'done' => 'Fertig',
            'failed' => 'Fehlgeschlagen',
        ],
    ],
    // Vereinsverwaltung (Feature 159, MVP-842)
    'club' => [
        'membership-kind' => [
            'guest' => 'Gast',
            'active' => 'Aktiv',
            'passive' => 'Passiv',
            'supporting' => 'Fördermitglied',
            'paused' => 'Pausiert',
        ],
        'admission-mode' => [
            'leader' => 'Durch Leitung',
            'application' => 'Antrag mit Freigabe',
        ],
        'group-membership-status' => [
            'requested' => 'Beantragt',
            'active' => 'Aktiv',
            'ended' => 'Beendet',
            'rejected' => 'Abgelehnt',
        ],
        'proposal-status' => [
            'open' => 'Offen',
            'confirmed' => 'Bestätigt',
            'dismissed' => 'Verworfen',
        ],
        'criteria-result' => [
            'met' => 'Erfüllt',
            'age_below' => 'Unter Mindestalter',
            'age_above' => 'Über Höchstalter',
            'review_required' => 'Prüfung erforderlich',
            'grade_below' => 'Unter Mindestgrad',
            'grade_above' => 'Über Höchstgrad',
            'grade_unknown' => 'Kein gültiger Grad',
        ],
        'event-kind' => [
            'competition' => 'Wettkampf',
            'match' => 'Spieltag',
            'training' => 'Training / Probe',
            'course' => 'Lehrgang',
            'exam' => 'Prüfung',
            'meeting' => 'Versammlung',
            'other' => 'Sonstiger Termin',
        ],
        'event-visibility' => [
            'club' => 'Ganzer Verein',
            'groups' => 'Bestimmte Gruppen',
            'invited' => 'Persönliche Einladung',
        ],
        'participation-status' => [
            'invited' => 'Eingeladen',
            'registered' => 'Angemeldet',
            'waitlisted' => 'Warteliste',
            'cancelled' => 'Abgesagt',
        ],
        'participation-source' => [
            'admin' => 'Verwaltung',
            'leader' => 'Gruppenleitung',
            'guardian' => 'Vertretung',
            'self' => 'Mitglied selbst',
            'spontaneous' => 'Spontan (Leitung)',
            'invitation' => 'Einladung',
        ],
        'grading-version-status' => [
            'draft' => 'Entwurf',
            'active' => 'Aktiv',
            'superseded' => 'Abgelöst',
        ],
        'counting-basis' => [
            'since_previous_grade' => 'Seit Vorgrad',
            'since_membership' => 'Seit Eintritt',
            'window_months' => 'Festes Zeitfenster',
        ],
        'grade-source' => [
            'exam' => 'Prüfung',
            'recognized' => 'Anerkannt',
        ],
        'proof-kind' => [
            'course' => 'Lehrgangsnachweis',
            'external_training' => 'Externer Trainingsnachweis',
        ],
        'exam-candidate-status' => [
            'requested' => 'Angefragt',
            'admitted' => 'Zugelassen',
            'rejected' => 'Abgelehnt',
            'withdrawn' => 'Zurückgetreten',
            'passed' => 'Bestanden',
            'failed' => 'Nicht bestanden',
            'no_show' => 'Nicht angetreten',
        ],
        'fee-tariff-kind' => [
            'individual' => 'Einzelbeitrag',
            'family' => 'Familienbeitrag',
        ],
        'fee-proration' => [
            'full' => 'Volle Periode',
            'daily' => 'Taggenau',
        ],
        'fee-exemption-kind' => [
            'exemption' => 'Befreiung',
            'reduction' => 'Ermäßigung',
        ],
        'fee-position-kind' => [
            'entry' => 'Meldegebühr',
            'base' => 'Grundbeitrag',
            'family' => 'Familienbeitrag',
            'surcharge' => 'Abteilungszuschlag',
            'admission' => 'Aufnahmegebühr',
            'exam' => 'Prüfungsgebühr',
            'course' => 'Lehrgangsgebühr',
        ],
        'fee-run-status' => [
            'draft' => 'Entwurf',
            'released' => 'Freigegeben',
            'cancelled' => 'Verworfen',
        ],
        'fee-claim-status' => [
            'open' => 'Offen',
            'partially_paid' => 'Teilweise bezahlt',
            'paid' => 'Bezahlt',
            'cancelled' => 'Storniert',
        ],
        'fee-payment-method' => [
            'transfer' => 'Überweisung',
            'cash' => 'Bar',
            'sepa' => 'SEPA-Lastschrift',
            'other' => 'Sonstiges',
        ],
        'fee-payment-source' => [
            'manual' => 'Manuell',
            'bank' => 'Bankabgleich',
            'sepa' => 'Einzugslauf',
            'chargeback' => 'Rücklastschrift',
            'credit' => 'Guthaben',
        ],
        'sport-family' => [
            'team_ball' => 'Mannschaftsballsport',
            'racket' => 'Rückschlagsport',
            'individual' => 'Individual-/Wettkampfsport',
            'shooting' => 'Schießsport',
            'equestrian' => 'Reitsport',
            'martial_arts' => 'Kampfsport',
            'boats' => 'Boots-/Gerätesport',
            'other' => 'Sonstige',
        ],
        'result-format' => [
            'goals' => 'Tore',
            'period_points' => 'Punkte je Abschnitt',
            'sets' => 'Sätze',
            'none' => 'Kein Spielergebnis',
        ],
        'lineup-slot' => [
            'field' => 'Feld',
            'bench' => 'Bank',
            'single' => 'Einzel',
            'double' => 'Doppel',
        ],
        'availability-status' => [
            'available' => 'Zugesagt',
            'maybe' => 'Vielleicht',
            'unavailable' => 'Abgesagt',
        ],
        'lineup-status' => [
            'draft' => 'Entwurf',
            'released' => 'Freigegeben',
        ],
        'event-role-kind' => [
            'referee' => 'Schiedsrichter/in',
            'timekeeper' => 'Zeitnehmer/in',
            'jury' => 'Kampfgericht',
            'driver' => 'Fahrdienst',
            'venue_duty' => 'Platz-/Kabinendienst',
            'range_officer' => 'Standaufsicht',
            'coach' => 'Betreuung',
            'other' => 'Sonstige',
        ],
        'match-proposal-source' => [
            'csv' => 'CSV',
            'ics' => 'ICS-Kalender',
        ],
        'resource-kind' => [
            'hall' => 'Halle',
            'part' => 'Teilfläche',
            'pitch' => 'Sportplatz',
            'court' => 'Platz/Court',
            'table' => 'Tisch',
            'lane' => 'Bahn',
            'stand' => 'Stand',
            'boat' => 'Boot',
            'equipment' => 'Gerät',
            'horse' => 'Pferd',
            'other' => 'Sonstige',
        ],
        'horse-kind' => [
            'school' => 'Schulpferd',
            'private' => 'Privatpferd',
        ],
        'entry-status' => [
            'registered' => 'Gemeldet',
            'needs_review' => 'Zur Klärung',
            'withdrawn' => 'Zurückgezogen',
        ],
        'attendance-sheet-status' => [
            'open' => 'Offen',
            'confirmed' => 'Bestätigt',
        ],
        'notification-status' => [
            'sent' => 'Zugestellt',
            'failed' => 'Zustellfehler',
        ],
        'attendance-status' => [
            'present' => 'Anwesend',
            'partial' => 'Teilweise anwesend',
            'excused' => 'Entschuldigt',
            'absent' => 'Abwesend',
        ],
        'guardian-permission' => [
            'register' => 'An- und Abmelden',
            'view_attendance' => 'Anwesenheit einsehen',
            'receive_messages' => 'Nachrichten erhalten',
        ],
        'donation-kind' => [
            'donation' => 'Geldzuwendung',
            'membership_fee' => 'Mitgliedsbeitrag',
        ],
        'donation-receipt-kind' => [
            'single' => 'Einzelbestätigung',
            'collective' => 'Sammelbestätigung',
        ],
    ],
    'takeoff' => [
        'status' => [
            'draft' => 'Offen',
            'completed' => 'Abgeschlossen',
        ],
    ],
    'invoicing' => [
        'online-payment-status' => [
            'open' => 'Offen',
            'paid' => 'Bezahlt',
            'failed' => 'Fehlgeschlagen',
            'canceled' => 'Abgebrochen',
            'expired' => 'Abgelaufen',
            'refunded' => 'Erstattet',
        ],
        'incoming_e_invoice_status' => ['received' => 'Empfangen', 'approved' => 'Fachlich freigegeben', 'rejected' => 'Abgelehnt', 'question' => 'Rückfrage', 'payment_released' => 'Zahlung freigegeben'],
        'invoice_schedule_status' => ['active' => 'Aktiv', 'paused' => 'Pausiert', 'ended' => 'Beendet'],
    ],
    'agile' => [
        'agile_column_category' => ['open' => 'Offen', 'in_progress' => 'In Arbeit', 'done' => 'Erledigt'],
        'agile_item_type' => ['epic' => 'Epic', 'story' => 'Story', 'task' => 'Aufgabe', 'bug' => 'Fehler'],
        'agile_sprint_status' => ['planned' => 'geplant', 'active' => 'aktiv', 'completed' => 'abgeschlossen', 'cancelled' => 'abgebrochen'],
    ],
    'api' => [
        'api_ability' => ['diary_read' => 'Aufträge lesen', 'diary_write' => 'Aufträge anlegen/ändern', 'tasks_read' => 'Aufgaben lesen', 'tasks_write' => 'Aufgaben anlegen/ändern', 'attendance_read' => 'Anwesenheit lesen', 'attendance_write' => 'Anwesenheit stempeln', 'assets_read' => 'Assets lesen', 'hooks_manage' => 'Automatisierungs-Hooks verwalten', 'tickets_write' => 'Tickets anlegen', 'comments_write' => 'Kommentare schreiben', 'attachments_read' => 'Anhänge herunterladen', 'attachments_write' => 'Anhänge hochladen/löschen', 'tags_read' => 'Tags lesen', 'tags_write' => 'Tags anlegen/ändern', 'shifts_read' => 'Bereitschaften lesen', 'assignments_read' => 'Einsätze lesen', 'dashboard_read' => 'Dashboard lesen', 'push_write' => 'Push-Abo verwalten', 'timesheets_read' => 'Stundenzettel lesen', 'timesheets_write' => 'Stundenzettel anlegen/ändern', 'materials_read' => 'Materialien lesen', 'stopwatch_read' => 'Stoppuhr lesen', 'stopwatch_write' => 'Stoppuhr steuern', 'flex_read' => 'Arbeitszeitkonto lesen', 'location_write' => 'Standort stempeln', 'customers_read' => 'Kunden lesen', 'customers_write' => 'Kunden anlegen/ändern', 'projects_read' => 'Projekte lesen', 'projects_write' => 'Projekte anlegen/ändern', 'absences_read' => 'Abwesenheiten lesen', 'expenses_read' => 'Spesen lesen', 'invoices_read' => 'Rechnungen lesen', 'scheduled_shifts_read' => 'Schichtplan lesen', 'articles_read' => 'Artikel lesen', 'inventory_read' => 'Bestände lesen', 'purchase_orders_read' => 'Bestellungen lesen', 'suppliers_read' => 'Lieferanten lesen', 'protocols_read' => 'Protokolle lesen', 'vehicles_read' => 'Fahrzeuge lesen', 'learning_read' => 'Lernplattform lesen', 'learning_write' => 'Lernplattform: selbst einschreiben', 'mcp_read' => 'KI-Assistent (MCP): lesen', 'mcp_write' => 'KI-Assistent (MCP): Entwürfe anlegen'],
    ],
    'asset_compliance' => [
        'asset_compliance_block_mode' => ['none' => 'Keine Wirkung', 'warn' => 'Warnung', 'block_after_grace' => 'Sperre nach Nachfrist', 'block_immediately' => 'Sofortige Sperre'],
        'asset_compliance_status' => ['valid' => 'Gültig geprüft', 'due_soon' => 'Prüfung bald fällig', 'overdue' => 'Prüfung überfällig', 'restricted' => 'Eingeschränkt freigegeben', 'blocked' => 'Gesperrt', 'not_applicable' => 'Keine Prüfpflicht'],
        'asset_inspection_kind' => ['verification' => 'Eichung', 'calibration' => 'Kalibrierung', 'dguv_uvv' => 'DGUV-/UVV-Prüfung', 'hu_au' => 'HU/AU', 'electrical' => 'Elektrische Betriebsmittelprüfung', 'manufacturer_service' => 'Herstellerwartung', 'safety_check' => 'Sicherheitsprüfung', 'function_check' => 'Funktionsprüfung', 'internal_check' => 'Interne Kontrollprüfung'],
        'asset_inspection_result' => ['passed' => 'Bestanden', 'passed_with_restrictions' => 'Bestanden mit Einschränkungen', 'failed' => 'Nicht bestanden'],
        'asset_inspection_schedule_status' => ['planned' => 'Geplant', 'announced' => 'Angekündigt', 'in_progress' => 'In Durchführung', 'done' => 'Durchgeführt', 'missed' => 'Versäumt', 'cancelled' => 'Storniert'],
    ],
    'asset_finance' => [
        'asset_finance_end_kind' => ['return' => 'Rückgabe', 'purchase' => 'Kauf/Übernahme', 'extension' => 'Verlängerung', 'replacement' => 'Ersatzinvestition'],
        'asset_finance_kind' => ['operating_lease' => 'Operating-Leasing', 'finance_lease' => 'Finanzierungsleasing', 'hire_purchase' => 'Mietkauf', 'long_term_rent' => 'Langzeitmiete', 'usage_contract' => 'Nutzungsvertrag', 'service_contract' => 'Servicevertrag mit Asset-Bezug'],
        'asset_finance_status' => ['draft' => 'Entwurf', 'active' => 'Aktiv', 'ending' => 'Endphase', 'extended' => 'Verlängert', 'returned' => 'Zurückgegeben', 'purchased' => 'Übernommen (Kauf)', 'terminated' => 'Gekündigt', 'closed' => 'Abgeschlossen', 'cancelled' => 'Storniert'],
        'asset_finance_term_kind' => ['rate' => 'Rate', 'special_payment' => 'Sonderzahlung', 'residual_value' => 'Restwertannahme', 'purchase_option' => 'Kaufoption', 'service_package' => 'Servicepaket', 'insurance' => 'Versicherung', 'maintenance' => 'Wartung', 'wear' => 'Verschleiß', 'return_cost' => 'Rückgabekosten', 'fee' => 'Gebühr', 'indexation' => 'Indexierung'],
        'asset_finance_usage_limit_kind' => ['kilometers' => 'Kilometer', 'operating_hours' => 'Betriebsstunden', 'usage_days' => 'Nutzungstage'],
    ],
    'auth' => [
        'two_factor_type' => ['totp' => 'Authenticator-App', 'email' => 'E-Mail-Code', 'webauthn' => 'Sicherheitsschlüssel / Passkey'],
    ],
    'claims' => [
        'claim_action_kind' => ['rework' => 'Nacharbeit', 'repair' => 'Reparatur', 'replacement' => 'Ersatzlieferung', 'service_visit' => 'Serviceeinsatz', 'price_reduction' => 'Preisnachlass', 'refund' => 'Rückerstattung', 'supplier_recourse' => 'Lieferantenregress', 'root_cause_fix' => 'Ursachenbehebung', 'other' => 'Sonstiges'],
        'claim_action_status' => ['planned' => 'Geplant', 'in_progress' => 'In Arbeit', 'done' => 'Erledigt', 'cancelled' => 'Abgebrochen'],
        'claim_financial_kind' => ['price_reduction' => 'Minderung/Preisnachlass', 'credit_note' => 'Gutschrift', 'cancellation' => 'Storno', 'correction' => 'Rechnungskorrektur', 'replacement_invoice' => 'Ersatzrechnung', 'refund' => 'Rückerstattung'],
        'claim_financial_status' => ['proposed' => 'Vorgeschlagen', 'approved' => 'Freigegeben', 'executed' => 'Ausgeführt/übergeben', 'rejected' => 'Abgelehnt'],
        'claim_kind' => ['guarantee' => 'Garantie', 'warranty_legal' => 'Gesetzliche Gewährleistung', 'warranty_contractual' => 'Vertragliche Gewährleistung', 'goodwill' => 'Kulanz', 'transport_damage' => 'Transportschaden', 'user_error' => 'Fehlbedienung', 'internal_error' => 'Interner Fehler', 'supplier_fault' => 'Lieferantenfehler', 'unfounded' => 'Unbegründet'],
        'claim_recourse_status' => ['draft' => 'Entwurf', 'submitted' => 'Eingereicht', 'accepted' => 'Anerkannt', 'partially_accepted' => 'Teilweise anerkannt', 'rejected' => 'Abgelehnt', 'closed' => 'Geschlossen'],
        'claim_rma_disposition' => ['restock' => 'Wiedereinlagerung', 'repair' => 'Reparatur', 'return_to_supplier' => 'Rücksendung an Lieferant', 'scrap' => 'Verschrottung', 'dispose' => 'Entsorgung'],
        'claim_rma_status' => ['announced' => 'Angekündigt', 'received' => 'Wareneingang erfasst', 'inspecting' => 'In Prüfung', 'completed' => 'Abgeschlossen'],
        'claim_source' => ['portal' => 'Kundenportal', 'email' => 'E-Mail', 'phone' => 'Telefonnotiz', 'helpdesk' => 'Helpdesk', 'order' => 'Auftrag', 'protocol' => 'Abnahmeprotokoll', 'asset' => 'Asset', 'invoice' => 'Rechnung', 'api' => 'API', 'internal' => 'Interner Mangel', 'manual' => 'Manuell'],
        'claim_status' => ['received' => 'Eingegangen', 'assessing' => 'In Bewertung', 'decided' => 'Entschieden', 'in_progress' => 'In Umsetzung', 'closed' => 'Abgeschlossen', 'rejected' => 'Abgelehnt', 'withdrawn' => 'Zurückgezogen'],
        'claim_verdict' => ['justified' => 'Berechtigt', 'unclear' => 'Unklar', 'rejected' => 'Abgelehnt'],
    ],
    'contract' => [
        'contract_kind' => ['rent' => 'Miet-/Pachtvertrag', 'maintenance' => 'Wartungsvertrag', 'license' => 'Lizenz-/Abovertrag', 'service' => 'Dienstleistungsvertrag', 'insurance' => 'Versicherungsvertrag', 'supply' => 'Liefer-/Bezugsvertrag', 'framework' => 'Rahmenvertrag', 'membership' => 'Mitgliedschaft/Beitrag', 'data_processing' => 'Auftragsverarbeitungsvertrag (AVV)', 'non_disclosure' => 'Verschwiegenheitsvereinbarung (NDA)', 'rental_terms' => 'Mietbedingungen (Geräteverleih)', 'employment' => 'Arbeitsvertrag', 'other' => 'Sonstiger Vertrag'],
        'contract_obligation_status' => ['open' => 'offen', 'done' => 'erledigt', 'missed' => 'versäumt'],
        'contract_partner_type' => ['customer' => 'Kunde', 'supplier' => 'Lieferant', 'other' => 'Freitext'],
        'contract_status' => ['draft' => 'Entwurf', 'active' => 'Aktiv', 'terminated' => 'Gekündigt', 'ended' => 'Beendet', 'cancelled' => 'Storniert'],
        'contract_term_kind' => ['fixed' => 'Befristet', 'open_ended' => 'Unbefristet'],
        'indexation_method' => ['none' => 'Keine Indexierung', 'consumer_price_index' => 'Verbraucherpreisindex (VPI)', 'fixed_percent' => 'Fester Prozentsatz', 'custom' => 'Eigene Regel'],
    ],
    'customer_portal' => [
        'portal_capability' => ['diary' => 'Aufträge & Fallakte', 'time_entries' => 'Projektzeiten', 'invoices' => 'Rechnungen & Abrechnungskonto', 'documents' => 'Dokumente', 'assets' => 'Objekte', 'open_issues' => 'Offene Punkte', 'tickets' => 'Tickets, Servicekatalog & bekannte Fehler', 'claims' => 'Reklamationen', 'rentals' => 'Verleihvorgänge', 'queries' => 'Rückfragen & Kommentare', 'appointments' => 'Online-Terminbuchung', 'rental_requests' => 'Verleih-Anfrage', 'subscriptions' => 'Abos & Lizenzen'],
        'portal_time_detail' => ['none' => 'Keine Zeiten', 'summary' => 'Nur Summen', 'entries' => 'Einträge (Datum, Dauer, Projekt, Mitarbeiter)', 'entries_with_description' => 'Einträge inkl. Beschreibung (nur veröffentlichte)'],
    ],
    'disposal' => [
        'data_medium_type' => ['hdd' => 'Festplatte (HDD)', 'ssd' => 'SSD', 'usb_flash' => 'USB-Stick', 'memory_card' => 'Speicherkarte', 'mobile_device' => 'Mobilgerät', 'magnetic_tape' => 'Magnetband', 'optical' => 'Optischer Datenträger', 'other' => 'Sonstiger Datenträger'],
        'din_category' => ['p' => 'P — Papier', 'f' => 'F — Film/Folie', 'o' => 'O — Optische Datenträger', 't' => 'T — Magnetische Datenträger', 'h' => 'H — Festplatten', 'e' => 'E — Elektronische Datenträger'],
        'disposal_job_event_type' => ['created' => 'Akte angelegt', 'item_added' => 'Geräteposition erfasst', 'item_updated' => 'Geräteposition geändert', 'item_removed' => 'Geräteposition entfernt', 'treatment_added' => 'Datenträger-Behandlung dokumentiert', 'treatment_removed' => 'Datenträger-Behandlung entfernt', 'handover_added' => 'Entsorger-Übergabe erfasst', 'handover_removed' => 'Entsorger-Übergabe entfernt', 'status_changed' => 'Status geändert', 'signed' => 'Übernahme unterschrieben', 'record_rendered' => 'Kundennachweis erzeugt', 'completed' => 'Akte abgeschlossen', 'cancelled' => 'Akte storniert'],
        'disposal_job_status' => ['draft' => 'Angelegt', 'collected' => 'Abgeholt', 'in_treatment' => 'In Behandlung', 'handed_over' => 'An Entsorger übergeben', 'completed' => 'Abgeschlossen', 'cancelled' => 'Storniert'],
        'disposal_proof_type' => ['transfer_note' => 'Übernahmeschein', 'consignment_note' => 'Begleitschein', 'disposal_certificate' => 'Entsorgungsnachweis', 'eanv' => 'eANV-Registerbezug', 'disposer_certificate' => 'Entsorgerzertifikat'],
        'media_treatment_method' => ['software_wipe' => 'Software-Löschung', 'degaussing' => 'Degaussing', 'shredding' => 'Schreddern', 'removed_for_destruction' => 'Ausgebaut zur Vernichtung'],
    ],
    'document_design' => [
        'information_block' => ['sender_line' => 'Absenderzeile', 'recipient_address' => 'Empfängeranschrift', 'document_meta' => 'Dokumenttitel, Nummer, Datum & Referenzen', 'contact_person' => 'Ansprechpartner & Kontaktdaten', 'company_identity' => 'Unternehmensanschrift, Rechtsform & Register', 'tax_identity' => 'Steuer-/Umsatzsteuerangaben', 'bank_details' => 'Bankverbindung & Zahlungsinformationen', 'intro_text' => 'Einleitungstext', 'items_table' => 'Positionstabelle', 'totals' => 'Summenbereich', 'tax_breakdown' => 'Steueraufschlüsselung', 'closing_text' => 'Schlusstext', 'page_meta' => 'Seitenzahl & Dokumentkennung', 'confidentiality' => 'Vertraulichkeitskennzeichnung'],
        'information_block_state' => ['dynamic' => 'Dynamisch (WorkDiary druckt)', 'provided_by_letterhead' => 'Bereits auf dem Firmenbogen', 'not_applicable' => 'Nicht anwendbar'],
        'letterhead_asset_status' => ['review_required' => 'Prüfung erforderlich', 'ready' => 'Einsatzbereit', 'archived' => 'Archiviert'],
        'letterhead_page_role' => ['first' => 'Erste Seite', 'following' => 'Folgeseiten'],
        'page_format' => ['a4_portrait' => 'A4 Hochformat', 'a4_landscape' => 'A4 Querformat'],
        'render_document_family' => ['sales' => 'Vertrieb/Fakturierung', 'procurement' => 'Einkauf/Logistik', 'evidence' => 'Leistung/Nachweis', 'special' => 'Spezialformat'],
        'render_document_kind' => ['invoice' => 'Rechnung', 'purchase_order' => 'Bestellung', 'protocol' => 'Protokoll', 'delivery_note' => 'Lieferschein', 'manufacturing_record' => 'Fertigungsnachweis', 'timesheet' => 'Stundenzettel', 'form' => 'Formular', 'report' => 'Bericht', 'quote' => 'Angebot', 'order_confirmation' => 'Auftragsbestätigung', 'credit_note' => 'Gutschrift', 'proforma_invoice' => 'Pro-forma-Rechnung', 'dunning' => 'Mahnung', 'case_file' => 'Fallakte', 'label' => 'Etikett'],
        'render_profile_status' => ['draft' => 'Entwurf', 'active' => 'Aktiv', 'archived' => 'Archiviert'],
        'render_profile_version_status' => ['draft' => 'Entwurf', 'active' => 'Aktiv', 'superseded' => 'Abgelöst'],
        'table_style_preset' => ['clear' => 'Klar', 'compact' => 'Kompakt', 'low_line' => 'Linienarm'],
    ],
    'facility' => [
        'room_usage_type' => ['office' => 'Büro', 'server_room' => 'Serverraum', 'cleanroom' => 'Reinraum', 'kitchen' => 'Küche', 'sanitary' => 'Sanitär', 'lab' => 'Labor', 'storage' => 'Lager', 'traffic_area' => 'Verkehrsfläche', 'meeting' => 'Besprechung', 'social' => 'Sozialraum', 'technical' => 'Technikraum', 'outdoor' => 'Außenfläche', 'other' => 'Sonstiges'],
    ],
    'integration' => [
        'conflict_field_policy' => ['remote_wins' => 'Remote gewinnt', 'local_wins' => 'Lokal gewinnt', 'manual_review' => 'Manuelle Prüfung (Inbox)'],
        'data_domain' => ['tasks' => 'Aufgaben', 'tickets' => 'Tickets', 'inventory' => 'Lagerbestand', 'calendar' => 'Kalender', 'documents' => 'Dokumente', 'customers' => 'Kunden'],
        'import_match_policy' => ['auto_link_exact_only' => 'Nur eindeutige zuordnen (Rest in die Inbox)', 'auto_link_and_create' => 'Zuordnen, sonst neu anlegen', 'manual_review' => 'Alles manuell prüfen'],
        'integration_inbox_status' => ['open' => 'Offen', 'resolved_linked' => 'Zugeordnet', 'resolved_created' => 'Neu angelegt', 'resolved_local' => 'Lokal behalten', 'resolved_remote' => 'Remote übernommen', 'dismissed' => 'Verworfen'],
    ],
    'key_handover' => [
        'key_handover_direction' => ['out' => 'Ausgabe', 'in' => 'Rückgabe'],
    ],
    'licensing' => [
        'module_status' => ['not_licensed' => 'Nicht lizenziert', 'active' => 'Aktiv', 'inactive_by_customer' => 'Deaktiviert', 'blocked' => 'Gesperrt'],
    ],
    'location' => [
        'location_pending_entry_status' => ['open' => 'Offen', 'imported' => 'Übernommen', 'dismissed' => 'Verworfen'],
    ],
    'migration' => [
        'accounting_migration_status' => ['draft' => 'Entwurf', 'analyzing' => 'Analyse', 'mapping' => 'Zuordnung', 'ready' => 'Bereit', 'parallel_run' => 'Doppelbetrieb', 'cutover' => 'Umschaltung', 'verifying' => 'Prüfung', 'completed' => 'Abgeschlossen', 'blocked' => 'Blockiert', 'cancelled' => 'Abgebrochen'],
        'migration_data_area' => ['customers' => 'Kunden', 'suppliers' => 'Lieferanten', 'articles' => 'Artikel und Leistungen', 'documents' => 'Belege (Historie)'],
        'migration_provider' => ['orga_max' => 'orgaMAX Buchhaltung'],
    ],
    'modules' => [
        'module_kind' => ['platform' => 'Plattformdienst', 'core' => 'Kernmodul', 'feature' => 'Fachmodul'],
    ],
    'numbering' => [
        'number_scope' => ['service_ticket' => 'Service-Ticket', 'problem_report' => 'Fehlermeldung', 'asset' => 'Asset', 'article' => 'Artikel', 'manufacturing_order' => 'Fertigungsauftrag', 'serial' => 'Seriennummer', 'purchase_order' => 'Bestellung', 'customer' => 'Kunde', 'supplier' => 'Lieferant', 'invoice' => 'Rechnung', 'credit_note' => 'Gutschrift', 'cancellation' => 'Stornorechnung', 'quote' => 'Angebot', 'proforma' => 'Pro-forma-Rechnung', 'claim' => 'Reklamation', 'rma' => 'Rücksendung (RMA)', 'rental' => 'Verleihakte', 'asset_finance' => 'Leasingakte', 'contract' => 'Vertrag', 'privacy_incident' => 'Datenschutzvorfall', 'data_subject_request' => 'Betroffenenanfrage', 'disposal' => 'Entsorgungsakte', 'certificate' => 'Zertifikat', 'damage' => 'Schadensfall', 'recall' => 'Rückrufaktion'],
    ],
    'patrol' => [
        'patrol_run_status' => ['running' => 'Laufend', 'completed' => 'Abgeschlossen', 'aborted' => 'Abgebrochen'],
    ],
    'plugin' => [
        'plugin_health_status' => ['ok' => 'Zustand ok', 'degraded' => 'Zustand eingeschränkt', 'failing' => 'Zustand fehlerhaft'],
        'plugin_capability' => ['contact_sync' => 'Kontaktsynchronisierung', 'time_export' => 'Zeit-Export', 'time_import' => 'Zeit-Import', 'payment_sync' => 'Zahlungsabgleich', 'task_sync' => 'Aufgaben-Sync', 'calendar_publish' => 'Kalender-Veröffentlichung', 'shipping_provider' => 'Versanddienstleister', 'document_intake' => 'Dokumenteingang', 'backup_target' => 'Backupziel', 'domain_registrar' => 'Domain-Registrar', 'appointment_sync' => 'Terminsynchronisation', 'sms_gateway' => 'SMS-Gateway', 'online_payment' => 'Online-Zahlung', 'peppol_transport' => 'Peppol-Transport', 'fare_meter' => 'Taxameter-Import', 'passenger_dispatch' => 'Fahrtvermittlung', 'mobility_data' => 'Mobilitätsdaten'],
    ],
    'privacy' => [
        'agreement_status' => ['draft' => 'Entwurf', 'active' => 'Aktiv', 'terminated' => 'Gekündigt', 'expired' => 'Abgelaufen'],
        'compliance_finding_status' => ['missing' => 'Fehlt', 'expiring' => 'Läuft ab', 'required' => 'Erforderlich', 'in_review' => 'In Prüfung', 'deviation_accepted' => 'Abweichung akzeptiert', 'present' => 'Vorhanden', 'not_applicable' => 'Nicht anwendbar'],
        'controller_role' => ['controller' => 'Verantwortlicher', 'joint_controller' => 'Gemeinsam Verantwortlicher', 'processor' => 'Auftragsverarbeiter'],
        'data_subject_kind' => ['user' => 'Mitarbeiter', 'portal_user' => 'Portal-Nutzer', 'customer' => 'Kunde', 'supplier' => 'Lieferant', 'lead' => 'Lead', 'job_application' => 'Bewerber', 'club_member' => 'Vereinsmitglied'],
        'data_subject_request_status' => ['intake' => 'Eingegangen', 'identity_check' => 'Identitätsprüfung', 'in_progress' => 'In Bearbeitung', 'awaiting_info' => 'Wartet auf Information', 'completed' => 'Erledigt', 'rejected' => 'Abgelehnt', 'withdrawn' => 'Zurückgezogen'],
        'data_subject_request_type' => ['access' => 'Auskunft (Art. 15)', 'rectification' => 'Berichtigung (Art. 16)', 'erasure' => 'Löschung (Art. 17)', 'restriction' => 'Einschränkung (Art. 18)', 'portability' => 'Datenübertragbarkeit (Art. 20)', 'objection' => 'Widerspruch (Art. 21)'],
        'dpia_outcome' => ['open' => 'Offen', 'proceed' => 'Vertretbar – Durchführung', 'consult_authority' => 'Konsultation der Aufsichtsbehörde', 'abort' => 'Nicht durchführbar'],
        'dpia_step_status' => ['pending' => 'Offen', 'done' => 'Abgeschlossen'],
        'implementation_status' => ['planned' => 'Geplant', 'partial' => 'Teilweise umgesetzt', 'implemented' => 'Umgesetzt', 'not_applicable' => 'Nicht anwendbar'],
        'incident_status' => ['detected' => 'Entdeckt', 'assessing' => 'In Bewertung', 'contained' => 'Eingedämmt', 'reported' => 'Gemeldet', 'closed' => 'Abgeschlossen'],
        'incident_type' => ['loss' => 'Verlust', 'misdelivery' => 'Fehlversand', 'unauthorized_access' => 'Unberechtigter Zugriff', 'disclosure' => 'Offenlegung', 'alteration' => 'Unbefugte Veränderung', 'unavailability' => 'Nichtverfügbarkeit'],
        'measure_category' => ['physical_access' => 'Zutrittskontrolle', 'system_access' => 'Zugangskontrolle', 'data_access' => 'Zugriffskontrolle', 'transfer' => 'Weitergabekontrolle', 'input' => 'Eingabekontrolle', 'availability' => 'Verfügbarkeitskontrolle', 'recovery' => 'Wiederherstellbarkeit', 'separation' => 'Trennungskontrolle', 'management' => 'Datenschutz-Management'],
        'processing_activity_status' => ['draft' => 'Entwurf', 'in_review' => 'In Prüfung', 'approved' => 'Freigegeben', 'archived' => 'Archiviert'],
        'processor_role' => ['controller' => 'Verantwortlicher', 'joint_controller' => 'Gemeinsam Verantwortlicher', 'processor' => 'Auftragsverarbeiter', 'subprocessor' => 'Unterauftragsverarbeiter'],
        'retention_proposal_status' => ['pending' => 'offen', 'approved' => 'bestätigt', 'rejected' => 'abgelehnt', 'purged' => 'gelöscht'],
        'review_result' => ['effective' => 'Wirksam', 'deviation' => 'Abweichung', 'ineffective' => 'Unwirksam'],
    ],
    'rental' => [
        'rental_case_status' => ['draft' => 'Entwurf', 'reserved' => 'Reserviert', 'handed_over' => 'Übergeben', 'overdue' => 'Überfällig', 'returned' => 'Zurückgegeben', 'closed' => 'Abgeschlossen', 'cancelled' => 'Storniert'],
        'rental_charge_kind' => ['daily_rate' => 'Tagessatz', 'hourly_rate' => 'Stundensatz', 'flat_rate' => 'Pauschale', 'weekend_surcharge' => 'Wochenendzuschlag', 'holiday_surcharge' => 'Feiertagszuschlag', 'cleaning' => 'Reinigung', 'consumable' => 'Verbrauchsmaterial', 'delivery' => 'Lieferung/Transport', 'damage' => 'Schaden', 'loss' => 'Verlust', 'discount' => 'Minderung/Nachlass', 'other' => 'Sonstiges'],
        'rental_charge_status' => ['draft' => 'Entwurf', 'released' => 'Freigegeben', 'invoiced' => 'Abgerechnet', 'transferred' => 'Extern übergeben', 'cancelled' => 'Storniert'],
        'rental_condition' => ['new' => 'Neuwertig', 'good' => 'Gut', 'used' => 'Gebraucht', 'worn' => 'Abgenutzt', 'damaged' => 'Beschädigt'],
        'rental_deposit_status' => ['requested' => 'Angefordert', 'received' => 'Erhalten', 'refunded' => 'Erstattet', 'partially_retained' => 'Teilweise einbehalten', 'retained' => 'Einbehalten', 'waived' => 'Verzichtet'],
        'rental_rate_card_status' => ['draft' => 'Entwurf', 'active' => 'Aktiv', 'retired' => 'Abgelöst'],
        'rental_request_status' => ['requested' => 'angefragt', 'accepted' => 'angenommen', 'declined' => 'abgelehnt', 'withdrawn' => 'zurückgenommen'],
        'rental_reservation_kind' => ['soft' => 'Vormerkung', 'hard' => 'Reservierung', 'rental' => 'Verleih', 'maintenance' => 'Wartungsfenster', 'cleaning' => 'Reinigung', 'transport' => 'Transport/Rüstzeit'],
        'rental_return_follow_up' => ['none' => 'Keine Folge', 'cleaning' => 'Reinigung erforderlich', 'repair' => 'Reparatur erforderlich', 'block' => 'Sperren', 'claim' => 'Reklamation eröffnen'],
    ],
    'service_ticket' => [
        'change_status' => ['draft' => 'Entwurf', 'pending_approval' => 'Wartet auf Freigabe', 'approved' => 'Genehmigt', 'implementing' => 'In Umsetzung', 'done' => 'Abgeschlossen', 'cancelled' => 'Abgebrochen'],
        'problem_status' => ['open' => 'Offen', 'analyzing' => 'In Analyse', 'known_error' => 'Known Error', 'resolved' => 'Gelöst', 'closed' => 'Geschlossen'],
        'service_request_status' => ['draft' => 'Entwurf', 'pending_approval' => 'Wartet auf Genehmigung', 'approved' => 'Genehmigt', 'rejected' => 'Abgelehnt', 'fulfilling' => 'In Erfüllung', 'done' => 'Erledigt'],
        'service_ticket_kind' => ['incident' => 'Störung', 'service_request' => 'Service-Anfrage', 'question' => 'Frage'],
        'service_ticket_priority' => ['low' => 'Niedrig', 'normal' => 'Normal', 'high' => 'Hoch', 'urgent' => 'Dringend'],
        'service_ticket_source' => ['manual' => 'Manuell', 'maintenance_plan' => 'Wartungsplan', 'open_issue' => 'Offene Punkte', 'email' => 'E-Mail', 'customer_portal' => 'Kundenportal', 'api' => 'API'],
        'service_ticket_status' => ['reported' => 'Gemeldet', 'triaged' => 'Triagiert', 'scheduled' => 'Eingeplant', 'in_progress' => 'In Arbeit', 'done' => 'Gelöst', 'accepted' => 'Abgenommen', 'closed' => 'Geschlossen', 'rejected' => 'Abgelehnt', 'waiting_customer' => 'Wartet auf Kunde', 'waiting_external' => 'Wartet auf Dritte', 'paused' => 'Pausiert'],
        'ticket_close_code' => ['solved' => 'Gelöst', 'workaround' => 'Umgehungslösung', 'duplicate' => 'Duplikat', 'no_fault' => 'Kein Fehler', 'rejected' => 'Abgelehnt', 'other' => 'Sonstiges'],
        'ticket_message_kind' => ['public_reply' => 'Antwort', 'internal_note' => 'Interne Notiz', 'system_event' => 'Systemereignis'],
        'ticket_severity' => ['low' => 'Niedrig', 'medium' => 'Mittel', 'high' => 'Hoch'],
    ],
    'software' => [
        'software_kind' => ['operating_system' => 'Betriebssystem', 'application' => 'Anwendung', 'firmware' => 'Firmware', 'driver' => 'Treiber', 'service' => 'Dienst', 'other' => 'Sonstige'],
        'software_license_type' => ['perpetual' => 'Kauflizenz', 'subscription' => 'Abonnement', 'oem' => 'OEM', 'volume' => 'Volumenlizenz', 'free' => 'Kostenfrei', 'open_source' => 'Open Source', 'other' => 'Sonstige'],
    ],
    'survey' => [
        'survey_invitation_status' => ['created' => 'erstellt', 'sent' => 'versendet', 'responded' => 'beantwortet', 'expired' => 'abgelaufen'],
    ],
    'tenders' => [
        'tender_notice_match_state' => ['new' => 'Neu', 'muted' => 'Ausgeblendet', 'converted' => 'Übernommen'],
    ],
    'time_account' => [
        'carryover_policy' => ['carry' => 'Übertrag (kumulierend)', 'cap' => 'Kappung beim Monatsabschluss'],
        'time_account_source' => ['wage_type' => 'Lohnart (Zeitregel-Ergebnis)', 'attendance_net' => 'Anwesenheit (Netto-Minuten)', 'absence' => 'Abwesenheit (Tage)', 'shift_type_count' => 'Dienst-Zähler (Schichttyp)', 'external_item' => 'Externe Position (Menge)'],
        'time_account_unit' => ['minutes' => 'Minuten', 'days' => 'Tage', 'count' => 'Anzahl'],
    ],
    'time_approval' => [
        'month_closure_status' => ['draft' => 'Entwurf', 'submitted' => 'Eingereicht', 'approved' => 'Genehmigt', 'rejected' => 'Abgelehnt', 'reopened' => 'Wiedereröffnet', 'locked' => 'Gesperrt'],
        'overtime_request_status' => ['submitted' => 'Eingereicht', 'approved' => 'Genehmigt', 'rejected' => 'Abgelehnt', 'withdrawn' => 'Zurückgezogen'],
        'time_correction_status' => ['draft' => 'Entwurf', 'submitted' => 'Eingereicht', 'approved' => 'Genehmigt', 'rejected' => 'Abgelehnt', 'applied' => 'Angewendet', 'withdrawn' => 'Zurückgezogen'],
    ],
    'time_export' => [
        'time_export_status' => ['preparing' => 'In Vorbereitung', 'ready' => 'Bereit', 'delivered' => 'Übermittelt', 'rejected' => 'Abgelehnt', 'superseded' => 'Ersetzt'],
    ],
];

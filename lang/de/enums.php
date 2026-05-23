<?php

return [
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
    'project' => [
        'status' => [
            'active' => 'Aktiv',
            'paused' => 'Pausiert',
            'archived' => 'Archiviert',
        ],
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
        ],
        'visibility' => [
            'internal' => 'Intern',
            'customer' => 'Kunden-sichtbar',
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
    ],
];

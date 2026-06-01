<?php

/*
 * Flat slug → label map for Blade dropdowns / option lists.
 *
 * Used where a controlled vocabulary value is rendered via __("values.$x"),
 * e.g. invoice status filter, room equipment, energy-log type. For richly
 * namespaced enums prefer lang/de/enums.php; this file is the "shallow"
 * counterpart for slugs that have no dedicated enum namespace yet.
 *
 * Migrated from lang/de.json on 2026-05-27.
 */

return [
    // Invoice status
    'draft'        => 'Entwurf',
    'issued'       => 'Gestellt',
    'paid'         => 'Bezahlt',
    'cancelled'    => 'Storniert',
    'voided'       => 'Storniert',
    'accepted'     => 'Angenommen',
    'sent'         => 'Gesendet',
    'overdue'      => 'Überfällig',
    'unchecked'    => 'Ungeprüft',
    'checked'      => 'Geprüft',
    'transferred'  => 'Übertragen',
    'paidoff'      => 'Bezahlt',

    // Lexoffice voucher types
    'invoice'                => 'Rechnung',
    'salesinvoice'           => 'Rechnung',
    'purchaseinvoice'        => 'Eingangsrechnung',
    'creditnote'             => 'Gutschrift',
    'salescreditnote'        => 'Gutschrift',
    'purchasecreditnote'     => 'Eingangsgutschrift',
    'quotation'              => 'Angebot',
    'orderconfirmation'      => 'Auftragsbestätigung',
    'deliverynote'           => 'Lieferschein',
    'downpaymentinvoice'     => 'Abschlagsrechnung',
    'downpaymentdeduction'   => 'Abschlagsverrechnung',

    // ServiceTicket status
    'open'         => 'Offen',
    'closed'       => 'Geschlossen',
    'auto_closed'  => 'Automatisch geschlossen',
    'adjusted'     => 'Angepasst',

    // Activity category
    'admin'        => 'Verwaltung',
    'training'     => 'Schulung',
    'meeting'      => 'Besprechung',
    'internal'     => 'Intern',
    'travel'       => 'Reise',
    'break'        => 'Pause',
    'absence'      => 'Abwesenheit',
    'standby'      => 'Bereitschaft',
    'other'        => 'Sonstiges',

    // Mixed workflow states
    'done'         => 'Erledigt',
    'in_progress'  => 'In Bearbeitung',
    'problem'      => 'Problem',
    'published'    => 'Veröffentlicht',

    // EnergyLog type
    'fuel'         => 'Kraftstoff',
    'electric'     => 'Elektrisch',

    // Project status
    'active'       => 'Aktiv',
    'paused'       => 'Pausiert',
    'archived'     => 'Archiviert',

    // Vacation / approval workflow
    'approved'     => 'Genehmigt',
    'rejected'     => 'Abgelehnt',
    'submitted'    => 'Eingereicht',
    'pending'      => 'Ausstehend',
    'planned'      => 'Geplant',
    'scheduled'    => 'Geplant',

    // Room equipment
    'beamer'       => 'Beamer',
    'whiteboard'   => 'Whiteboard',
    'video_conf'   => 'Videokonferenz',
    'flipchart'    => 'Flipchart',
    'audio'        => 'Audioanlage',
    'wlan'         => 'WLAN',
];

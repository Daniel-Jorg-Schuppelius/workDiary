<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : disposal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Entsorgungsakte (Feature 100, MVP-469/470): Liste, Akte, Dialoge und
// Kundennachweis-PDF. Enum-Labels und Backend-Meldungen liegen inline im Code.
return [
    'eyebrow' => 'Entsorgung',

    'index' => [
        'title' => 'Entsorgungsakten',
        'subtitle' => 'Abholung, Geräteliste, Datenträger-Behandlung und Entsorger-Nachweise — prüffest bis zum Kundennachweis.',
        'empty' => 'Keine Entsorgungsakten — die erste Akte über den Dialog anlegen.',
        'kpi' => [
            'open' => 'Offene Akten',
            'hazardous_open' => 'Offen mit gefährlichem Abfall',
            'completed_year' => 'Abgeschlossen (laufendes Jahr)',
        ],
        'filter' => [
            'hazardous_only' => 'nur gefährlich',
        ],
        'col' => [
            'items' => 'Positionen',
            'picked_up' => 'Abholung',
        ],
    ],

    'field' => [
        'site' => 'Einsatzort',
        'diary_entry' => 'Auftrag',
        'picked_up_on' => 'Abholdatum',
        'total_weight' => 'Gesamtgewicht (kg)',
        'created' => 'Angelegt',
        'cancelled_at' => 'Storniert am',
        'cancel_reason' => 'Storno-Begründung',
        'completed_at' => 'Abgeschlossen am',
        'completed_by' => 'Abgeschlossen von',
    ],

    'form' => [
        'title_create' => 'Neue Entsorgungsakte',
        'title_edit' => 'Entsorgungsakte bearbeiten',
        'submit_create' => 'Akte anlegen',
        'group_assignment' => 'Kunde & Einsatz',
        'group_pickup' => 'Abholung & Details',
        'site' => 'Einsatzort (optional)',
        'site_none' => 'ohne Einsatzort',
        'diary_entry' => 'Auftrag/Fallakte (optional)',
        'diary_entry_none' => 'ohne Auftragsbezug',
    ],

    'show' => [
        'nav' => 'Entsorgungsakte',
        'title' => 'Entsorgungsakte :number',
        'section' => [
            'job' => 'Akte',
            'blockers' => 'Abschluss-Prüfung',
            'items' => 'Geräteliste',
            'handovers' => 'Entsorger-Übergaben',
            'signature' => 'Übernahme-Bestätigung',
            'record' => 'Kundennachweis',
        ],
    ],

    'badge' => [
        'hazardous' => 'gefährlich',
        'signed' => 'Übernahme unterschrieben',
    ],

    'item' => [
        'title_create' => 'Position erfassen',
        'title_edit' => 'Position bearbeiten',
        'group_device' => 'Gerät',
        'group_disposal' => 'Entsorgung & Datenträger',
        'weight' => 'Gewicht (kg)',
        'condition_note' => 'Zustandsnotiz',
        'avv_code' => 'AVV-Abfallschlüssel',
        'avv_hint' => 'Stern * = gefährlicher Abfall — die Einstufung wird automatisch abgeleitet.',
        'has_data_storage' => 'Gerät enthält Datenträger',
        'note' => 'Notiz',
        'empty' => 'Keine Gerätepositionen — Geräte über „Position erfassen" aufnehmen.',
        'col' => [
            'device' => 'Hersteller/Modell',
            'weight' => 'Gewicht (kg)',
            'avv' => 'AVV-Schlüssel',
            'data_storage' => 'Datenträger',
        ],
        'treatments_count' => '1 Behandlung|:count Behandlungen',
        'treatment_missing' => 'Behandlung fehlt',
    ],

    'treatment' => [
        'title_create' => 'Datenträger-Behandlung erfassen',
        'group_method' => 'Verfahren & Norm',
        'group_evidence' => 'Durchführung & Beleg',
        'media_type' => 'Datenträgertyp',
        'method' => 'Verfahren',
        'din_category' => 'DIN-66399-Materialkategorie',
        'security_level' => 'Sicherheitsstufe (1–7)',
        'protection_class' => 'Schutzklasse',
        'protection_class_none' => 'ohne Angabe',
        'protection_class_short' => 'Schutzklasse :class',
        'treated_at' => 'Zeitpunkt',
        'performed_by' => 'Durchführender',
        'evidence_reference' => 'Beleg-/Zertifikatsreferenz',
        'please_select' => '-- bitte wählen --',
    ],

    'handover' => [
        'title_create' => 'Entsorger-Übergabe erfassen',
        'group_proof' => 'Entsorger & Nachweis',
        'group_attachment' => 'Beleg & Notiz',
        'disposer' => 'Entsorger',
        'proof_type' => 'Nachweistyp',
        'document_number' => 'Belegnummer',
        'handed_over_on' => 'Übergabedatum',
        'certificate_reference' => 'EfbV-Zertifikat-Referenz',
        'proof_file' => 'Beleg-Datei (optional)',
        'proof_file_hint' => 'PDF, JPG oder PNG — maximal 10 MB. Der Beleg wird als DMS-Dokument abgelegt.',
        'note' => 'Notiz',
        'no_disposers' => 'Kein Entsorgungsfachbetrieb hinterlegt.',
        'create_disposer' => 'Entsorger als externen Kontakt anlegen',
        'empty' => 'Noch keine Übergabe an einen Entsorger erfasst.',
        'col' => [
            'disposer' => 'Entsorger',
            'proof_type' => 'Nachweistyp',
            'document_number' => 'Belegnummer',
            'certificate' => 'EfbV-Referenz',
            'document' => 'DMS-Beleg',
        ],
    ],

    'sign' => [
        'signer_name' => 'Name der übernehmenden Person',
        'signed_at' => 'Unterschrieben am',
        'hash' => 'Prüfsumme',
        'hint' => 'Mit „Übernahme bestätigen" wird die Unterschrift prüffest gespeichert.',
        'missing' => 'Keine Übernahme-Unterschrift vorhanden.',
    ],

    'record' => [
        'released_hint' => 'Der Kundennachweis ist im Kundenportal freigegeben.',
        'pending_hint' => 'Der Kundennachweis entsteht automatisch beim Abschluss der Akte.',
    ],

    'cancel' => [
        'title' => 'Entsorgungsakte stornieren',
        'intro' => 'Die Stornierung ist endgültig und wird mit Begründung in der Nachweiskette protokolliert.',
        'reason' => 'Begründung',
    ],

    'action' => [
        'create' => 'Neue Entsorgungsakte',
        'collect' => 'Abholung erfassen',
        'start_treatment' => 'Behandlung starten',
        'hand_over' => 'An Entsorger übergeben',
        'pdf_preview' => 'Nachweis-PDF (Vorschau)',
        'add_item' => 'Position erfassen',
        'add_treatment' => 'Behandlung erfassen',
        'add_handover' => 'Übergabe erfassen',
        'sign' => 'Übernahme bestätigen',
    ],

    'confirm' => [
        'complete' => 'Akte abschließen? Der Kundennachweis wird erzeugt, freigegeben und verknüpfte Assets werden ausgemustert.',
        'delete_item' => 'Geräteposition wirklich entfernen?',
        'delete_treatment' => 'Datenträger-Behandlung wirklich entfernen?',
        'delete_handover' => 'Entsorger-Übergabe wirklich entfernen?',
    ],

    'pdf' => [
        'title' => 'Übernahme- und Entsorgungsnachweis',
        'number' => 'Aktennummer',
        'customer' => 'Kunde',
        'picked_up_on' => 'Abholdatum',
        'responsible' => 'Verantwortlich',
        'status' => 'Status',
        'total_weight' => 'Gesamtgewicht',
        'items' => 'Geräteliste',
        'treatments' => 'Datenschutz- und Datenträgernachweis (DIN 66399)',
        'handovers' => 'Entsorgungs- und Verbleibnachweis',
        'confirmation' => 'Bestätigung',
        'customer_signature' => 'Übernahme durch den Kunden',
        'not_signed' => 'Nicht unterschrieben.',
        'provider' => 'Dienstleister',
        'completed_at' => 'Abgeschlossen am',
        'hazardous_suffix' => '(gefährlich)',
        'col' => [
            'category' => 'Kategorie',
            'device' => 'Hersteller/Modell',
            'serial' => 'Seriennummer',
            'quantity' => 'Menge',
            'weight' => 'Gewicht (kg)',
            'avv' => 'AVV-Schlüssel',
            'media_type' => 'Datenträgertyp',
            'method' => 'Verfahren',
            'din' => 'DIN 66399',
            'protection_class' => 'Schutzklasse',
            'treated_at' => 'Zeitpunkt',
            'performed_by' => 'Durchführender',
            'evidence' => 'Beleg-/Zertifikatsnr.',
            'disposer' => 'Entsorger',
            'proof_type' => 'Nachweistyp',
            'document_number' => 'Belegnummer',
            'handed_over_on' => 'Datum',
            'certificate' => 'EfbV-Zertifikat',
        ],
        'footer' => [
            'hash' => 'Prüfsumme',
            'generated' => 'Erzeugt am :at',
        ],
    ],
];

<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : permit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Genehmigungen',
    'subtitle' => 'Behördliche Genehmigungen für Veranstaltungen – Status, Fristen und Nachweise.',
    'label' => 'Genehmigung',
    'create' => 'Genehmigung anlegen',
    'edit' => 'Genehmigung bearbeiten',
    'delete_confirm' => 'Genehmigung wirklich löschen?',

    'sections' => [
        'base' => 'Stammdaten',
        'dates' => 'Fristen',
    ],

    'fields' => [
        'title' => 'Bezeichnung',
        'status' => 'Status',
        'event' => 'Veranstaltung',
        'event_none' => '— ohne —',
        'permit_type' => 'Genehmigungsart',
        'authority' => 'Behörde',
        'reference_no' => 'Aktenzeichen',
        'applied_at' => 'Beantragt am',
        'valid_from' => 'Gültig ab',
        'valid_until' => 'Gültig bis / Frist',
        'notes' => 'Notizen',
        'evidence' => 'Nachweis-Dokument',
    ],

    'filter' => [
        'all_status' => 'Alle Status',
    ],

    'status' => [
        'required' => 'Erforderlich',
        'applied' => 'Beantragt',
        'granted' => 'Erteilt',
        'rejected' => 'Abgelehnt',
        'expired' => 'Abgelaufen',
    ],

    'messages' => [
        'created' => 'Genehmigung angelegt.',
        'updated' => 'Genehmigung aktualisiert.',
        'deleted' => 'Genehmigung gelöscht.',
    ],

    'evidence' => [
        'upload' => 'Nachweis hochladen',
        'replace' => 'Nachweis ersetzen',
        'replace_hint' => 'Ein neuer Upload ersetzt den vorhandenen Nachweis.',
        'hint' => 'Erlaubt: PDF, JPG, PNG, DOCX (max. :mb MB).',
        'remove' => 'Nachweis entfernen',
        'remove_confirm' => 'Nachweis-Dokument wirklich entfernen?',
        'too_large' => 'Die Datei ist zu groß (max. :mb MB).',
        'invalid_type' => 'Dateityp nicht erlaubt (PDF, JPG, PNG, DOCX).',
    ],
];

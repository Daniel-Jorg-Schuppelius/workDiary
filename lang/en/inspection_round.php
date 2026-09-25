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
    'nav' => 'Inspection rounds',
    'title' => 'Inspection rounds',
    'subtitle' => 'Target list of due inspections for a location or group — scan the object, record the result.',
    'open' => 'Create round',
    'show' => 'Open round',
    'name' => 'Name',
    'due_until' => 'Due until',
    'progress' => 'Done',
    'status' => 'Status',
    'none_title' => 'No inspection rounds yet',
    'none' => 'Create a round for a location or group.',
    'location' => 'Location',
    'category' => 'Group (category)',
    'profile' => 'Inspection profile',
    'customer' => 'Customer',
    'any' => 'All',
    'form_hint' => 'The round takes all active inspection duties due by the date. Duties falling due later are not added.',
    'empty' => 'No inspection is due by the date for this selection.',
    'opened' => 'Round created with :count inspections.',
    'scan' => 'Scan object',
    'scan_submit' => 'Open',
    'scan_unknown' => 'Unknown object code.',
    'scan_not_in_round' => '“:asset” is not part of this round.',
    'scan_done' => 'All inspections of this object are done in the round.',
    'scan_several' => 'Several inspections are open for this object:',
    'kpi_done' => 'Done',
    'kpi_missing' => 'Missing',
    'kpi_overdue' => 'Overdue',
    'asset' => 'Object',
    'due_on' => 'Due on',
    'overdue' => 'Overdue',
    'pending' => 'Open',
    'capture' => 'Record inspection',
    'capture_submit' => 'Save inspection',
    'result' => 'Result',
    'note' => 'Remark',
    'signature_name' => 'Signature (name)',
    'certificate_hint' => 'This inspection profile requires a certificate for “passed”. In that case, record the inspection in the inspection calendar.',
    'recorded' => 'Inspection documented.',
    'close' => 'Close round',
    'confirm_close' => 'Close round? :missing inspections are still open and remain as missing.',
    'closed' => 'The round is closed.',
    'closed_flash' => 'Round closed.',
    'already_done' => 'This inspection has already been recorded in the round.',
];

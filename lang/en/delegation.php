<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : delegation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vertretung bei Abwesenheit (Feature 202, MVP-1647).
return [
    'title' => 'Deputyships',
    'subtitle' => 'Who covers for whom during an absence. The deputy sees the assigned work in the work queue and receives its notifications — but gets no additional rights.',
    'create' => 'Create deputyship',
    'from_absence' => 'Create deputyship',
    'filter' => [
        'state' => 'Period',
        'current' => 'Running and planned',
        'past' => 'Ended and expired',
        'all' => 'Whole organisation',
    ],
    'empty' => [
        'title' => 'No deputyships',
        'message' => 'Deputyships you are involved in appear here.',
    ],
    'column' => [
        'represented' => 'Person covered',
        'deputy' => 'Deputy',
        'period' => 'Period',
        'state' => 'Status',
        'work' => 'Takes over',
    ],
    'open_end' => 'until further notice',
    'state' => [
        'active' => 'Running',
        'upcoming' => 'Planned',
        'past' => 'Expired',
        'ended' => 'Ended',
    ],
    'ended_by' => 'Ended by :name on :date',
    'end' => 'End deputyship',
    'end_confirm' => 'End the deputyship now? The work will then again lie only with the person covered.',
    'dialog' => [
        'title' => 'Create deputyship',
        'submit' => 'Create deputyship',
        'no_rights_hint' => 'The deputy sees and handles the assigned work and receives its notifications. They get no additional rights and no insight into times, absences or the personnel file.',
        'people' => 'People',
        'deputy_hint' => 'Prefilled with the person’s named deputy.',
        'choose_deputy' => 'Please choose',
        'from' => 'From',
        'until' => 'Until',
        'until_hint' => 'Without an end date, the deputyship applies until someone ends it.',
        'kind' => 'Reason',
        'note' => 'Note',
        'work_heading' => 'The deputy takes over from :name',
        'work_none' => 'Currently no open assigned work',
        'work_hint' => 'The person covered remains responsible; nothing needs to be reassigned afterwards.',
    ],
    'banner' => [
        'represents' => 'You are covering for :names.',
        'represented_by' => 'Covered by :names.',
        'person_until' => ':name (until :date)',
        'work_queue' => 'Go to work queue',
        'manage' => 'Deputyships',
    ],
    'notification' => [
        'on_behalf_of' => 'Covering for :name:',
        'created' => 'You are covering for :name from :from to :until.',
        'created_open' => 'You are covering for :name from :from until further notice.',
    ],
    'flash' => [
        'created' => 'Deputyship by :name created.',
        'ended' => 'Deputyship ended.',
    ],
    'error' => [
        'past_start' => 'A deputyship starts today at the earliest.',
        'end_before_start' => 'The end is before the start.',
        'absence_mismatch' => 'The absence does not belong to this person or is not approved.',
        'overlap' => ':name already has a deputyship in this period.',
        'deputy_absent' => ':name is covered by someone else in this period and cannot cover.',
        'represented_is_deputy' => ':name is covering for someone in this period; chained deputyships are not possible.',
        'already_ended' => 'This deputyship has already ended.',
        'foreign_organization' => 'Deputyships are only possible within your own organisation.',
        'self' => 'A person cannot cover for themselves.',
        'deactivated' => 'Deactivated accounts can neither cover nor be covered.',
    ],
    // Permanent handover without leaving and multi-select in the order list (MVP-1648).
    'handover' => [
        'action' => 'Hand over responsibilities',
        // Personenwahl auf der Seite „Vertretungen“ (Einstieg ohne Mitgliederliste).
        'choose_title' => 'Whose responsibilities do you want to hand over?',
        'choose_hint' => 'Choose the person whose work passes permanently to successors. You then choose the successor for each area.',
        'choose_empty' => 'There is no person whose work you may hand over.',
        'title' => 'Hand over responsibilities of :name',
        'intro' => 'Permanent handover without leaving: the selected areas pass to the successor for good; the person remains a member. For a temporary absence, a deputyship is the right tool.',
        'areas' => 'Areas and successor',
        'hint' => 'One successor per area, or none. Only what is open and assigned to the person when saving is transferred; the number per area is logged.',
        'submit' => 'Hand over permanently',
        'empty' => 'Nothing that can be handed over is currently assigned to this person.',
        'single_records' => 'Hand over individual orders in the order list via the “Change responsibility” selection.',
        'help' => 'Help',
        'flash' => '{0} Nothing handed over.|{1} One item of :name handed over.|[2,*] :count items of :name handed over.',
        'area' => [
            'assigned_orders' => '{1} One assigned order|[2,*] :count assigned orders',
            'assigned_tasks' => '{1} One open task|[2,*] :count open tasks',
            'assigned_service_tickets' => '{1} One assigned service ticket|[2,*] :count assigned service tickets',
            'assigned_procedure_runs' => '{1} One running procedure run|[2,*] :count running procedure runs',
        ],
        'error' => [
            'inactive' => 'Only the work of active members can be handed over; for leavers, use the offboarding dialog.',
            'successor' => 'The successor must be another active member of the same organisation.',
            'none' => 'Please choose a successor for at least one area.',
        ],
    ],
    'reassign' => [
        'selected' => ':n orders selected',
        'choose' => 'Choose new responsible person…',
        'submit' => 'Change responsibility',
        'select_entry' => 'Select order “:title”',
        'unknown_person' => 'This person does not exist in your organisation or is deactivated.',
        'done' => '{0} No responsibility changed.|{1} One order is now with :name.|[2,*] :count orders are now with :name.',
        'skipped' => '{1} One order was skipped (no edit right, already responsible or qualification missing).|[2,*] :count orders were skipped (no edit right, already responsible or qualification missing).',
    ],
    'demo' => [
        'note' => 'Example: deputyship during a training course.',
    ],
];

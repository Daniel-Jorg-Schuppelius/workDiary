<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : protocol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Protocols',
        'show' => 'Protocol #:id',
        'create' => 'Create protocol',
        'edit' => 'Edit protocol',
    ],
    'field' => [
        'type' => 'Type',
        'title' => 'Title',
        'description' => 'Description',
        'state_initial' => 'State before',
        'stateInitial' => 'State before',
        'state_final' => 'State after',
        'stateFinal' => 'State after',
        'occurred_at' => 'Date / time',
        'occurredAt' => 'Date / time',
        'createdBy' => 'Created by',
        'visibility' => 'Visibility',
        'status' => 'Status',
        'revision' => 'Revision',
        'subject' => 'Reference',
    ],
    'action' => [
        'create' => 'Create',
        'update' => 'Save',
        'requestReview' => 'Submit for review',
        'returnToDraft' => 'Return to draft',
        'sign' => 'Finalize / sign',
        'archive' => 'Archive',
        'supersede' => 'Create correction revision',
        'addItem' => 'Add item',
        'fillItem' => 'Fill in item',
        'removeItem' => 'Remove item',
        'delete' => 'Delete',
    ],
    'flash' => [
        'created' => 'Protocol created.',
        'updated' => 'Protocol updated.',
        'deleted' => 'Protocol deleted.',
        'transition' => [
            'requestReview' => 'Protocol submitted for review.',
            'returnToDraft' => 'Protocol returned to draft.',
            'sign' => 'Protocol signed and finalized.',
            'archive' => 'Protocol archived.',
            'supersede' => 'Correction revision created.',
        ],
        'item' => [
            'added' => 'Item added.',
            'filled' => 'Item filled in.',
            'removed' => 'Item removed.',
        ],
        'photo' => [
            'uploaded' => 'Photo added.',
            'removed' => 'Photo removed.',
            'captionUpdated' => 'Caption updated.',
        ],
    ],
    'validation' => [
        'required' => 'Item ":label" is required.',
        'criticalDefectMissingOpenIssue' => 'Critical defect ":label" requires an open issue.',
        'text' => [
            'minLength' => 'Text is too short (min. :min characters).',
            'maxLength' => 'Text is too long (max. :max characters).',
        ],
        'boolean' => [
            'invalid' => 'A boolean value is required.',
        ],
        'choice' => [
            'invalid' => 'A selection is required.',
            'notInOptions' => 'Selection is not contained in the option list.',
        ],
        'multichoice' => [
            'invalid' => 'At least one selection is required.',
            'notInOptions' => 'Selection is not contained in the option list.',
        ],
        'number' => [
            'invalid' => 'Numeric value required.',
            'min' => 'Value is below the minimum (:bound).',
            'max' => 'Value exceeds the maximum (:bound).',
        ],
        'date' => [
            'invalid' => 'Invalid date.',
        ],
        'attachments' => [
            'required' => 'At least one attachment is required.',
            'min' => 'At least :min attachments are required.',
            'max' => 'At most :max attachments are allowed.',
        ],
        'defect' => [
            'severity' => 'Severity must be low/medium/high/critical.',
            'description' => 'Defect description is required.',
        ],
        'measurement' => [
            'empty' => 'At least one measurement is required.',
            'invalidSample' => 'Every measurement needs "at" and "value".',
        ],
        'signature' => [
            'missing' => 'Signature is not yet attached.',
        ],
        'photo' => [
            'missingPhase' => 'Photo item ":label": phase ":phase" requires at least :need photo(s) (present: :have).',
        ],
    ],
    'pdf' => [
        'title' => 'Protocol – :title',
        'state' => 'State',
        'items' => 'Protocol items',
        'signatures' => 'Signatures',
        'col' => [
            'label' => 'Item',
            'type' => 'Type',
            'value' => 'Value',
            'result' => 'Result',
            'note' => 'Note',
        ],
        'footer' => [
            'hash' => 'Checksum',
            'generated' => 'Generated on :at',
        ],
    ],
    'signature' => [
        'tokenIssued' => 'Signature link has been created.',
        'tokenExpired' => 'The signature link has expired or has already been used.',
        'tokenUnknown' => 'Signature link unknown.',
        'redeemed' => 'Signature has been saved.',
    ],
];

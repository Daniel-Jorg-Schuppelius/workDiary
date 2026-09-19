<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValueObjectSchemas.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * JSON-Form der Toolkit-Wertobjekte (jsonSerialize). Die API gibt Beträge,
 * Mengen und Prozentsätze einheitlich so aus (Entscheid 2026-09-19); nicht
 * gesetzte Werte sind null. Dezimalwerte stehen als String, damit keine
 * Nachkommastelle durch float verloren geht.
 */
#[OA\Schema(
    schema: 'Money',
    description: 'Geldbetrag.',
    required: ['amount', 'currency'],
    properties: [
        new OA\Property(property: 'amount', type: 'string', pattern: '^-?\d+(\.\d+)?$', example: '85.00'),
        new OA\Property(property: 'currency', type: 'string', description: 'ISO 4217', example: 'EUR'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Quantity',
    description: 'Menge mit Einheit.',
    required: ['value', 'scale', 'unit'],
    properties: [
        new OA\Property(property: 'value', type: 'string', pattern: '^-?\d+(\.\d+)?$', example: '2.500'),
        new OA\Property(property: 'scale', type: 'integer', description: 'Nachkommastellen', example: 3),
        new OA\Property(property: 'unit', type: 'string', example: 'Stk'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Percentage',
    description: 'Prozentsatz (19 % = value "19.00").',
    required: ['value', 'scale'],
    properties: [
        new OA\Property(property: 'value', type: 'string', pattern: '^-?\d+(\.\d+)?$', example: '19.00'),
        new OA\Property(property: 'scale', type: 'integer', description: 'Nachkommastellen', example: 2),
    ],
    type: 'object',
)]
final class ValueObjectSchemas {}

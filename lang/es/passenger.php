<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : passenger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Transporte de pasajeros (MVP-456, perfil taxi/alquiler con conductor).
return [
    'entry_title' => 'Orden de viaje (:mode)',
    'entry_content' => 'Transporte de pasajeros — canal de pedido: :channel. Detalles en el expediente del viaje.',

    'error' => [
        'destination_required' => 'Indique un destino o confirme explícitamente el destino abierto.',
        'receipt_channel_invalid' => 'El alquiler con conductor / transporte a demanda exige una recepción de pedido demostrable en la sede — parar a mano no está permitido (§ 49 (4) PBefG).',
        'pickup_required' => 'El lugar de recogida es obligatorio.',
        'not_assigned' => 'El viaje solo puede comenzar tras la asignación (conductor, vehículo, concesión).',
        'tariff_required' => 'El servicio de taxi aplica la tarifa regulada — seleccione una tarifa.',
        'fixed_price_outside_corridor' => 'El precio fijo está fuera del corredor oficialmente permitido.',
        'meter_value_required' => 'El valor del taxímetro/dispositivo es obligatorio al cerrar.',
        'tax_decision_required' => 'La decisión fiscal (tipo) es obligatoria al cerrar.',
        'payment_required' => 'El método de pago es obligatorio al cerrar.',
        'invalid_transition' => 'Cambio de estado no permitido.',
        'invalid_transition_detail' => 'Cambio de estado no permitido: :from → :to.',
        'return_not_applicable' => 'La prueba de regreso solo aplica al alquiler con conductor.',
    ],

    'issue' => [
        'driver_unqualified' => 'Licencia de transporte de pasajeros ausente o caducada.',
        'concession_missing' => 'Sin concesión válida para este modo de explotación.',
        'vehicle_profile_missing' => 'El vehículo no tiene perfil de transporte de pasajeros.',
        'vehicle_mode_unsupported' => 'El vehículo no está autorizado para este modo.',
        'vehicle_proofs_expired' => 'Certificados del vehículo caducados (calibración/BOKraft/ITV).',
        'vehicle_not_barrier_free' => 'El viaje requiere accesibilidad — el vehículo no la ofrece.',
        'vehicle_no_wheelchair_place' => 'El viaje requiere plaza de silla de ruedas — el vehículo no la tiene.',
        'vehicle_too_small' => 'El número de pasajeros supera las plazas del vehículo.',
    ],

    'proof' => [
        'meter_calibration' => 'Calibración de taxímetro/cuentakilómetros',
        'bokraft' => 'Inspección BOKraft',
        'hu' => 'Inspección técnica',
    ],
];

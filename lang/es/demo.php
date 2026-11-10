<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : demo.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Contenido de los bloques de demostración (MVP-1532/1533). Se guarda en el idioma de quien lanza el seed.
return [
    'schedule' => [
        'shift_type' => [
            'early' => 'Turno de mañana',
            'late' => 'Turno de tarde',
            'office' => 'Turno de oficina',
        ],
        'plan_current' => 'Cuadrante de esta semana y la siguiente (demo)',
        'plan_next' => 'Cuadrante de dentro de dos semanas (demo)',
        'coverage_note' => 'En el turno de mañana se planifican al menos dos personas los días laborables.',
        'exchange_reason' => 'Cita privada por la tarde, cambio acordado con el turno de mañana.',
    ],
    'forms' => [
        'report' => [
            'name' => 'Informe de intervención (demo)',
            'description' => 'Breve informe tras cada intervención en el cliente.',
            'work_done' => 'Trabajos realizados',
            'work_done_value' => 'Mantenimiento realizado según la lista de control, sin defectos.',
            'hours_on_site' => 'Tiempo in situ',
            'result' => 'Resultado',
            'result_done' => 'Terminado',
            'result_follow_up' => 'Se necesita otra intervención',
            'result_material' => 'Falta material',
            'briefed' => 'Cliente instruido',
            'satisfaction' => 'Satisfacción del cliente',
        ],
        'safety' => [
            'name' => 'Evaluación de riesgos antes de empezar (demo)',
            'description' => 'Comprobación rápida in situ antes de empezar el trabajo.',
            'ppe_complete' => 'Equipo de protección completo',
            'area_secured' => 'Zona de trabajo asegurada',
            'hazards' => 'Riesgos especiales',
            'hazards_value' => 'Actividad del cliente en curso, zona de trabajo acordonada con cinta de balizamiento.',
        ],
        'handover' => [
            'name' => 'Traspaso antes de las vacaciones (demo)',
            'description' => 'Borrador: tareas pendientes y contactos para la sustitución.',
            'open_tasks' => 'Tareas pendientes',
            'contact' => 'Persona de contacto en el cliente',
        ],
    ],
    'knowledge' => [
        'guide_title' => 'Guía: :subject',
        'guide_solution' => 'Procedimiento de recepción, repasar punto por punto con el cliente:',
        'issue_solution' => 'Acordar una cita con el cliente, realizar la comprobación y documentar el resultado en la orden. Después, cerrar el punto pendiente.',
        'onboarding_title' => 'Primera semana para nuevos compañeros',
        'onboarding_problem' => 'El personal nuevo no sabe dónde están las órdenes, los tiempos y los documentos.',
        'onboarding_solution' => 'Borrador: recorrido por órdenes, registro de tiempos y base de conocimientos; una persona de contacto fija durante las dos primeras semanas.',
    ],
    'inventory' => [
        'warehouse_main' => 'Almacén principal (demo)',
        'warehouse_main_note' => 'Filas de estanterías A–C en la planta baja',
        'warehouse_vehicle' => 'Vehículo de servicio 1 (demo)',
    ],
    'travel' => [
        'vendor' => [
            'parking' => 'Aparcamiento del centro',
            'lodging' => 'Hotel del Mercado',
            'supplies' => 'Ferretería de ejemplo',
            'hospitality' => 'Restaurante de ejemplo',
        ],
        'expense' => [
            'parking' => 'Aparcamiento para la cita con el cliente en :city',
            'hotel' => 'Pernoctación para la intervención en :city',
            'material' => 'Material de fijación para la intervención',
            'hospitality' => 'Comida de trabajo con el cliente',
        ],
        'from_address' => 'Sede de la empresa',
        'trip_purpose' => 'Cita con el cliente :customer',
    ],
    'location' => [
        'zone_label' => ':customer (lugar de intervención demo)',
    ],
    'contracts' => [
        'maintenance_title' => 'Contrato de mantenimiento :customer (demo)',
        'maintenance_notes' => 'Tarifa plana mensual, prórroga de doce meses salvo rescisión.',
        'review_title' => 'Revisión anual del alcance de los servicios',
        'license_title' => 'Licencia de software de oficina (demo)',
        'license_partner' => 'Software Ejemplo S.L.',
        'payment_title' => 'Revisar la factura anual de la licencia',
        'rent_title' => 'Contrato de alquiler de superficie de almacén (demo)',
        'rent_partner' => 'Parque Empresarial Ejemplo S.L.',
    ],
    'facility' => [
        'site_name' => ':customer – sede principal',
        'street' => 'Calle Ejemplo 1',
        'building' => 'Edificio principal',
        'floor' => [
            'ground' => 'Planta baja',
            'first' => 'Primera planta',
        ],
        'room' => [
            'reception' => 'Recepción',
            'technical' => 'Sala técnica',
            'storage' => 'Almacén',
            'office' => 'Oficina',
            'meeting' => 'Sala de reuniones',
            'social' => 'Sala de descanso',
        ],
        'access_note' => 'Acceso solo con la llave de recepción.',
    ],
    'bau' => [
        'boq_name' => 'Presupuesto de obra :project (demo)',
        'section_supply' => 'Suministro',
        'section_labour' => 'Montaje y mano de obra',
        'item_assembly' => 'Montaje y puesta en marcha',
        'item_assembly_long' => 'Montaje de todas las partidas de suministro, incluida la prueba de funcionamiento y la instrucción.',
        'item_hourly' => 'Trabajos por administración según justificante',
        'progress_note' => 'Primera medición tras la entrega',
    ],
    'team' => [
        'description' => 'Trabajo en el proyecto :project',
    ],
];

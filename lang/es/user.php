<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : user.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'employees' => 'Empleados',

    // Tarifa de facturación y de coste por persona (MVP-1565), visible solo con user.payroll.manage.
    'rates' => [
        'legend' => 'Tarifas para facturación y cálculo posterior',
        'hint' => 'Confidencial: visible y modificable solo con el permiso «Gestionar los datos de personal y nómina». Los cambios se aplican a los tiempos que se registren o recalculen después y quedan registrados.',
        'hourly_rate' => 'Tarifa por hora (facturación)',
        'hourly_rate_hint' => 'Tiene prioridad sobre la tarifa de la tarea, el proyecto y el cliente; solo las condiciones especiales y una tarifa en la entrada de tiempo van por delante. Déjelo vacío si debe aplicarse la tarifa del proyecto o del cliente.',
        'internal_rate' => 'Tarifa de coste interna',
        'internal_rate_hint' => 'Coste por hora de esta persona para el cálculo posterior; tiene prioridad sobre la tarifa de coste de la tarea, el proyecto y el cliente.',
    ],

    'role' => [
        'admin' => 'Administrador',
        'meldestelle' => 'Oficina de denuncias',
        'datenschutz' => 'Protección de datos',
        'geschaeftsfuehrung' => 'Dirección',
        'personalverwaltung' => 'Administración de personal',
        'teamleitung' => 'Jefe de equipo',
        'buchhaltung' => 'Contabilidad',
        'user' => 'Empleado',
        'aussendienst' => 'Servicio externo',
        'callcenter' => 'Centro de llamadas',
        'support' => 'Soporte',
        'training_manager' => 'Responsable de formación',
        'kunde' => 'Cliente',
    ],
    'employment_type' => [
        'vollzeit' => 'Tiempo completo',
        'teilzeit' => 'Tiempo parcial',
        'minijob' => 'Mini-job (marginal)',
        'midijob' => 'Midi-job (zona de transición)',
        'kurzfristig' => 'Empleo de corta duración',
        'werkstudent' => 'Estudiante en prácticas',
        'azubi' => 'Aprendiz',
    ],
    'compensation_model' => [
        'payroll' => 'Interno (nómina)',
        'pauschal' => 'Tarifa plana',
        'nach_zeitaufwand' => 'Por tiempo',
    ],
    'flat_interval' => [
        'monatlich' => 'Mensual',
        'pro_einsatz' => 'Por intervención',
        'einmalig' => 'Único',
    ],
];

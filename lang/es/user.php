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

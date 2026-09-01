<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : help.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Centro de ayuda (funcionalidad 039, MVP-752): secciones de la página de resumen.
return [
    // Artikelschema der Pilotartikel (MVP-756) — Reihenfolge wie
    // config('help-center.article_schema').
    'schema' => [
        'zweck' => 'Objetivo y contexto',
        'voraussetzungen' => 'Requisitos',
        'ablauf' => 'Procedimiento recomendado',
        'beispiel' => 'Ejemplo práctico',
        'fehler' => 'Errores habituales',
        'naechste-schritte' => 'Efectos y próximos pasos',
    ],
    'sections' => [
        'erste-schritte' => [
            'title' => 'Primeros pasos',
            'description' => 'Inicio de sesión, panel, navegación, ajustes personales y los primeros pasos más importantes.',
        ],
        'kunden-vertrieb' => [
            'title' => 'Clientes & ventas',
            'description' => 'Datos maestros de clientes, expediente, proyectos, portal de clientes, citas y temas comerciales.',
        ],
        'zeit-personal' => [
            'title' => 'Tiempo & personal',
            'description' => 'Fichaje, registros de tiempo, ausencias, planificación de turnos, cuentas de horas y exportación de nóminas.',
        ],
        'auftraege-service' => [
            'title' => 'Pedidos & servicio',
            'description' => 'Libro de órdenes, actas, procedimientos, formularios, helpdesk y temas de obra.',
        ],
        'material-lager' => [
            'title' => 'Artículos & almacén',
            'description' => 'Maestro de artículos, catálogos, existencias, aprovisionamiento, precios y números de serie.',
        ],
        'geraete-fuhrpark' => [
            'title' => 'Equipos & flota',
            'description' => 'Expediente de equipos, inspecciones, vehículos, entregas de llaves, garantías y software.',
        ],
        'faktura' => [
            'title' => 'Facturas & facturación',
            'description' => 'Presupuestos, facturas, factura electrónica, contratos, flujo documental y comisiones.',
        ],
        'buchhaltung' => [
            'title' => 'Contabilidad & finanzas',
            'description' => 'Diario, plan contable, cierre, cuentas bancarias, exportación DATEV y de tiempos.',
        ],
        'auswertungen' => [
            'title' => 'Informes',
            'description' => 'Informes, análisis detallados, exportaciones y lectura correcta de los indicadores.',
        ],
        'sicherheit-compliance' => [
            'title' => 'Seguridad & cumplimiento',
            'description' => 'SGSI, protección de datos, canal de denuncias, seguridad laboral, auditoría y archivo.',
        ],
        'administration' => [
            'title' => 'Administración',
            'description' => 'Organización, roles y permisos, importación, copias de seguridad, licencia e integraciones.',
        ],
        'weitere' => [
            'title' => 'Otros temas',
            'description' => 'Todo lo que no pertenece a una de las áreas principales.',
        ],
    ],
];

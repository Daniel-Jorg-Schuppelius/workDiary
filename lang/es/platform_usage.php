<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : platform_usage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Nutzung je Mandant und Branchenvergleich (MVP-951/949).
return [
    'title' => 'Uso por cliente',
    'subtitle' => 'Usuarios, almacenamiento, módulos y última actividad por organización — solo para la operación de la plataforma.',
    'back' => 'Organizaciones',
    'empty' => 'No hay organizaciones.',
    'field' => [
        'organization' => 'Organización',
        'status' => 'Estado',
        'users' => 'Usuarios',
        'active_users' => 'Activos (30 días)',
        'storage' => 'Almacenamiento',
        'modules' => 'Módulos',
        'last_activity' => 'Última actividad',
    ],
    'benchmark' => [
        'link' => 'Comparación sectorial',
        'subtitle' => 'Emisiones anuales por perfil sectorial principal de todos los clientes sin demos, solo a partir de tres organizaciones por sector.',
        'title' => 'Emisiones por sector :year (anónimo)',
        'branch' => 'Sector',
        'organizations' => 'Organizaciones',
        'mean' => 'Media',
        'median' => 'Mediana',
        'empty' => 'Ningún sector con al menos :min organizaciones y emisiones registradas.',
    ],
    // Nutzungsabrechnung, Abrechnungsdaten und Tarifanfragen (MVP-956/957).
    'billing' => [
        'title' => 'Facturación por uso',
        'subtitle' => 'Uso mensual por organización, valorado con los precios unitarios de la configuración del sistema (platform_billing.*). No se crea ninguna factura.',
        'month' => 'Mes',
        'plan' => 'Plan',
        'amount' => 'Importe',
        'empty' => 'Aún no hay uso mensual. Se registra el primer día de cada mes (platform:usage-snapshot).',
        'note' => 'Importe = cuota base + usuarios + usuarios activos + GB de almacenamiento iniciados, cada uno por el precio unitario.',
    ],
    'plan' => [
        'free' => 'Free',
        'pro' => 'Pro',
        'enterprise' => 'Enterprise',
    ],
    'billing_profile' => [
        'title' => 'Datos de facturación',
        'subtitle' => 'Destinatario de las facturas por el uso del software y cambios de plan.',
        'contact' => 'Destinatario de la factura',
        'save' => 'Guardar',
        'invalid_vat' => 'El NIF-IVA no es válido.',
        'field' => [
            'name' => 'Nombre / empresa',
            'email' => 'Correo para facturas',
            'street' => 'Calle',
            'zip' => 'Código postal',
            'city' => 'Ciudad',
            'country' => 'País (ISO)',
            'vat_id' => 'NIF-IVA',
            'reference' => 'Referencia de pedido',
        ],
        'hint' => [
            'reference' => 'Aparece en las facturas del operador.',
        ],
        'flash' => [
            'saved' => 'Datos de facturación guardados.',
        ],
    ],
    'plan_request' => [
        'title' => 'Solicitar cambio de plan',
        'open_title' => 'Solicitudes de plan abiertas',
        'history' => 'Solicitudes',
        'current' => 'Plan actual: :plan',
        'send' => 'Enviar solicitud',
        'withdraw' => 'Retirar',
        'done' => 'Completada',
        'decline' => 'Rechazar',
        'empty' => 'Sin solicitudes.',
        'already_open' => 'Ya hay una solicitud abierta.',
        'field' => [
            'plan' => 'Plan deseado',
            'addons' => 'Módulos adicionales',
            'note' => 'Observación',
            'requester' => 'Solicitado por',
            'created_at' => 'Fecha',
        ],
        'hint' => [
            'addons' => 'Códigos de módulo separados por comas, p. ej. module.rental',
        ],
        'status' => [
            'open' => 'Abierta',
            'done' => 'Completada',
            'declined' => 'Rechazada',
            'withdrawn' => 'Retirada',
        ],
        'flash' => [
            'sent' => 'Solicitud enviada. El operador se pondrá en contacto con usted.',
            'withdrawn' => 'Solicitud retirada.',
            'decided' => 'Solicitud cerrada.',
        ],
    ],
];

<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : guarantee.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Bürgschaftsregister (Feature 114, MVP-603).
return [
    'title' => 'Avales',
    'subtitle' => 'Avales prestados y recibidos con vencimiento y justificante de devolución',
    'empty' => 'Aún no hay ningún aval registrado.',
    'unlimited' => 'sin vencimiento',
    'created' => 'Aval registrado.',
    'updated' => 'Aval actualizado.',
    'returned' => 'Devolución registrada.',
    'drawn' => 'Ejecución registrada.',
    'secured' => 'Retención de garantía sustituida por el aval.',
    'not_active' => 'Este aval ya no está activo.',
    'retention_not_open' => 'Esta retención ya no está abierta.',
    'foreign_organization' => 'El aval y la retención pertenecen a organizaciones distintas.',
    'amount_too_low' => 'El aval no cubre la retención — un aval menor no la sustituye.',
    'issuer_hint' => 'Banco o aseguradora según el documento; si no, elija un proveedor.',
    'issuer_supplier' => 'Avalista de los datos maestros',
    'action' => [
        'create' => 'Registrar aval',
        'edit' => 'Editar aval',
        'returned' => 'Documento devuelto',
    ],
    'kpi' => [
        'issued' => 'Prestados (activos)',
        'issued_hint' => 'Mientras no vuelva, la comisión de aval sigue corriendo.',
        'received' => 'Recibidos (activos)',
        'received_hint' => 'Si vence sin que nadie lo note, la garantía se pierde.',
        'expiring' => 'Vence en 90 días',
        'return_due' => 'Devolución pendiente',
        'return_due_hint' => 'La retención sustituida está liberada — el documento debe volver.',
    ],
    'column' => [
        'reference' => 'N.º de aval',
        'direction' => 'Sentido',
        'kind' => 'Tipo',
        'issuer' => 'Avalista',
        'party' => 'Contraparte',
        'amount' => 'Importe',
        'issued_on' => 'Emitido el',
        'expires_on' => 'Vence el',
        'status' => 'Estado',
        'customer' => 'Cliente',
        'supplier' => 'Proveedor',
        'project' => 'Proyecto',
        'responsible' => 'Responsable',
        'note' => 'Nota',
    ],
    'filter' => [
        'direction' => 'Sentido',
        'status' => 'Estado',
    ],
];

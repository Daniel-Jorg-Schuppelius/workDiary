<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : time-correction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Auftrag einer Zeitbuchung per Korrekturantrag ändern (MVP-1552).
    'order' => [
        'label' => 'Cambiar la orden',
        'unchanged' => '— sin cambios —',
        'none' => '— sin orden —',
        'current' => 'actual',
    ],
    // Eigene Stempelung korrigieren, auch offline (MVP-1595).
    'attendance' => [
        'title' => 'Corregir fichaje',
        'action' => 'Corregir fichaje',
        'submit' => 'Enviar corrección',
        'intro' => 'La corrección se tramita como solicitud de aprobación; si su organización permite la autocorrección, se aplica de inmediato. Sin conexión, se guarda en el dispositivo y se transmite en el siguiente contacto.',
        'span' => 'Inicio y fin',
        'reason' => 'Motivo',
        'reason_placeholder' => 'Al menos :min caracteres, p. ej. «olvidé fichar la salida»',
        'changed' => 'El fichaje se ha modificado entretanto: vuelva a abrir el diálogo.',
        'applied' => 'Fichaje corregido.',
        'submitted' => 'Corrección enviada para su aprobación.',
    ],
];

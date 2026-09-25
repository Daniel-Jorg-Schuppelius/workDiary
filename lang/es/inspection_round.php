<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_round.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfmittelrunden (Feature 075, MVP-899).
return [
    'nav' => 'Rondas de inspección',
    'title' => 'Rondas de inspección',
    'subtitle' => 'Lista de inspecciones pendientes de una ubicación o un grupo — escanee el objeto y registre el resultado.',
    'open' => 'Crear ronda',
    'show' => 'Abrir ronda',
    'name' => 'Denominación',
    'due_until' => 'Vencimiento hasta',
    'progress' => 'Realizadas',
    'status' => 'Estado',
    'none_title' => 'Aún no hay rondas de inspección',
    'none' => 'Cree una ronda para una ubicación o un grupo.',
    'location' => 'Ubicación',
    'category' => 'Grupo (categoría)',
    'profile' => 'Perfil de inspección',
    'customer' => 'Cliente',
    'any' => 'Todos',
    'form_hint' => 'La ronda incluye todas las obligaciones de inspección activas que vencen hasta la fecha. Las que venzan después no se añaden.',
    'empty' => 'Para esta selección no vence ninguna inspección hasta la fecha.',
    'opened' => 'Ronda creada con :count inspecciones.',
    'scan' => 'Escanear objeto',
    'scan_submit' => 'Abrir',
    'scan_unknown' => 'Código de objeto desconocido.',
    'scan_not_in_round' => '«:asset» no forma parte de esta ronda.',
    'scan_done' => 'Todas las inspecciones de este objeto están realizadas en la ronda.',
    'scan_several' => 'Hay varias inspecciones abiertas para este objeto:',
    'kpi_done' => 'Realizadas',
    'kpi_missing' => 'Faltan',
    'kpi_overdue' => 'Vencidas',
    'asset' => 'Objeto',
    'due_on' => 'Vence el',
    'overdue' => 'Vencida',
    'pending' => 'Abierta',
    'capture' => 'Registrar inspección',
    'capture_submit' => 'Guardar inspección',
    'result' => 'Resultado',
    'note' => 'Observación',
    'signature_name' => 'Firma (nombre)',
    'certificate_hint' => 'Este perfil exige un certificado para «aprobado». En ese caso, registre la inspección en el calendario de inspecciones.',
    'recorded' => 'Inspección documentada.',
    'close' => 'Cerrar ronda',
    'confirm_close' => '¿Cerrar la ronda? :missing inspecciones siguen abiertas y quedan como faltantes.',
    'closed' => 'La ronda está cerrada.',
    'closed_flash' => 'Ronda cerrada.',
    'already_done' => 'Esta inspección ya está registrada en la ronda.',
];

<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : uploads.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Datei-Uploads mit Zweck (MVP-1074): Hinweise, Fortschritt und Fehler je Datei.
 */

return [
    'label' => 'Archivos',
    'hint' => 'Permitido: :formats · hasta :size por archivo · como máximo :count archivos, :total en total.',
    'progress' => 'Subiendo … :percent %',
    'error' => [
        'type' => ':name: tipo de archivo no permitido.',
        'too_large' => ':name supera :size.',
        'count' => 'Seleccione como máximo :count archivos a la vez.',
        'total' => 'Los archivos son demasiado grandes en total (como máximo :size por envío).',
        'failed' => 'La subida ha fallado. Sus datos no se han guardado; inténtelo de nuevo.',
    ],
];

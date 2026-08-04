<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : textcorrections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Diccionario',
        'subtitle' => 'Correcciones ortográficas (incorrecto → correcto) aplicadas automáticamente a los textos de posición generados — los registros de tiempo permanecen sin cambios.',
    ],

    'notice' => 'Las entradas se aplican automáticamente al construir los textos de posición de traspasos y borradores de factura (palabra completa, se conserva el uso de mayúsculas). Los textos originales de los registros de tiempo nunca se modifican.',
    'search_placeholder' => 'Buscar (incorrecto/correcto) …',
    'legend' => 'Entrada del diccionario',
    'empty' => 'No hay entradas en el diccionario',
    'delete_confirm' => '¿Eliminar esta entrada del diccionario? La corrección dejará de aplicarse.',
    'wrong_placeholder' => 'p. ej. mantenimeinto',
    'wrong_help' => 'Palabra o frase mal escrita — solo coincide como palabra completa, sin distinguir mayúsculas.',
    'correct_placeholder' => 'p. ej. mantenimiento',
    'correct_help' => 'Grafía correcta — sustituye el error en todos los textos de posición generados.',

    'field' => [
        'wrong' => 'Incorrecto',
        'correct' => 'Correcto',
        'origin' => 'Origen',
        'origin_manual' => 'Manual',
        'origin_learned' => 'Aprendido',
        'usage' => 'Usado',
        'active' => 'Activo',
        'enabled_yes' => 'Sí',
        'enabled_no' => 'No',
    ],

    'action' => [
        'new' => 'Crear entrada',
        'edit' => 'Editar entrada',
        'submit' => 'Guardar',
        'activate' => 'Activar',
        'deactivate' => 'Desactivar',
        'delete' => 'Eliminar',
    ],

    'flash' => [
        'saved' => 'Entrada del diccionario creada.',
        'updated' => 'Entrada del diccionario actualizada.',
        'deleted' => 'Entrada del diccionario eliminada.',
        'activated' => 'Entrada del diccionario activada.',
        'deactivated' => 'Entrada del diccionario desactivada.',
        'learned' => 'Corrección añadida al diccionario.',
        'duplicate_updated' => 'La entrada ya existía y se ha actualizado.',
        'invalid' => 'Incorrecto y correcto no pueden ser idénticos.',
    ],

    'validation' => [
        'duplicate' => 'Ya existe una entrada para este error.',
    ],

    'learn' => [
        'title' => '¿Recordar la corrección?',
        'question' => 'Se detectaron correcciones de palabras en su edición. ¿Añadirlas al diccionario para que se apliquen automáticamente en el futuro?',
        'confirm' => 'Recordar',
        'dismiss' => 'No recordar',
    ],
];

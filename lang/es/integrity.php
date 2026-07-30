<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : integrity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Señales secundarias de integridad y bloqueo (funcionalidad 097, MVP-447/448).
return [
    'anchor' => [
        'unavailable' => 'Ancla de integridad externa no legible (¿destino de copia de seguridad accesible?) — señal secundaria omitida.',
        'root_mismatch' => 'El ancla externa difiere: raíz del ancla :remote, local :local.',
        'history_mismatch' => 'El historial de comprobaciones difiere del ancla externa — el historial local podría haber sido sustituido.',
    ],
    'env' => [
        'missing' => '.env ausente o no legible (la línea base contiene una huella).',
        'values_changed' => '.env modificado (mismo conjunto de claves, valores distintos).',
        'keys_changed' => '.env modificado (conjunto de claves distinto: :before → :after claves).',
    ],
    'git' => [
        'head_mismatch' => 'El HEAD de Git :head no coincide con la compilación de la línea base :expected (AVISO).',
        'dirty' => 'Árbol de trabajo de Git no limpio en el ámbito de análisis: :count ruta(s) — :paths (AVISO).',
    ],
    'lockdown' => [
        'crisis_title' => 'Bloqueo de integridad: código fuente manipulado',
        'crisis_description' => 'Una línea base de versión firmada muestra desviaciones en varias comprobaciones consecutivas (:modified modificados, :added nuevos, :deleted eliminados). La instalación está en modo mantenimiento.',
    ],
];

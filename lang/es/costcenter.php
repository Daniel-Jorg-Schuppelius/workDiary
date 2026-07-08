<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : costcenter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'rules' => "Reglas de centros de coste",
        'rules_subtitle' => "Centros de coste para la exportación de tiempos verificada: por usuario, por equipo o como valor predeterminado de la organización.",
        'rules_help' => "¿Cómo funcionan las reglas de centros de coste?",
        'rules_help_text' => "Durante la exportación de tiempos cada línea recibe el centro de coste del empleado: primero gana una regla de usuario, luego la regla de equipo con mayor prioridad y por último el valor predeterminado de la organización. En la interfaz de revisión se puede sobrescribir el centro de coste por línea.",
        'create_rule' => "Crear regla de centro de coste",
        'edit_rule' => "Editar regla de centro de coste",
        'empty' => "No hay reglas de centros de coste",
    ],

    'field' => [
        'basics' => "Regla",
        'source' => "Origen",
        'source_help' => "Las reglas de usuario ganan a las reglas de equipo; sin coincidencia se aplica el valor predeterminado de la organización.",
        'source_default' => "Predeterminado de la organización",
        'source_user' => "Usuario",
        'source_team' => "Equipo",
        'user' => "Usuario",
        'team' => "Equipo",
        'choose' => "– elegir –",
        'cost_center' => "Centro de coste",
        'priority' => "Prioridad",
        'priority_help' => "Desempate entre varias reglas de equipo: gana la prioridad más alta.",
    ],

    'action' => [
        'create' => "Crear",
        'edit' => "Editar",
        'save' => "Guardar",
        'delete' => "Eliminar",
        'delete_confirm' => "¿Eliminar realmente esta regla de centro de coste? Las exportaciones existentes no cambian.",
    ],

    'flash' => [
        'created' => "Regla de centro de coste creada.",
        'updated' => "Regla de centro de coste actualizada.",
        'deleted' => "Regla de centro de coste eliminada.",
    ],
];

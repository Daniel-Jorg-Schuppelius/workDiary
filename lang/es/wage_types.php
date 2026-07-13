<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : wage_types.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => "Conceptos salariales & entrega de exportaciones",
        'index_subtitle' => "Asignar los conceptos salariales internos a los números de concepto del programa de nóminas de destino y configurar la entrega automática por perfil de exportación.",
        'mappings_help' => "¿Cómo funciona la asignación de conceptos salariales?",
        'mappings_help_text' => "Durante la exportación de tiempos, el concepto de cada línea se resuelve primero mediante esta asignación y después mediante el concepto de la regla de recargo; las horas normales sin asignación conservan el concepto predeterminado del perfil. Si una línea de recargo o ausencia carece de toda asignación, la exportación se interrumpe con un mensaje de error en lugar de generar un archivo erróneo.",
        'create' => "Crear asignación de concepto",
        'edit' => "Editar asignación de concepto",
        'empty' => "Sin asignaciones de conceptos — siguen vigentes los conceptos predeterminados de los perfiles.",
        'delivery' => "Entrega automática",
        'delivery_help_text' => "Las exportaciones terminadas se entregan automáticamente por perfil mediante correo electrónico y/o SFTP a la gestoría de nóminas; el comprobante (cuándo/dónde) queda registrado en la exportación.",
        'delivery_edit' => "Configurar entrega — :profile",
    ],

    'field' => [
        'basics' => "Asignación",
        'profile' => "Perfil de exportación",
        'wage_type' => "Concepto salarial interno",
        'wage_type_help' => "Conceptos estándar de la exportación de tiempos más los tipos de recargo de su organización.",
        'external_code' => "Concepto de destino (externo)",
        'external_code_help' => "Número de concepto en el programa de nóminas de destino — numérico de hasta 4 dígitos para DATEV/Lexware.",
        'standard_types' => "Conceptos estándar",
        'surcharge_types' => "Tipos de recargo (organización)",
        'choose' => "– seleccione –",
        'mail' => "Envío por correo",
        'mail_toggle' => "Enviar el archivo de exportación por correo al finalizar",
        'mail_recipients' => "Destinatarios",
        'mail_recipients_help' => "Separe varias direcciones con coma, punto y coma o salto de línea.",
        'sftp' => "Subida SFTP",
        'sftp_toggle' => "Subir el archivo de exportación por SFTP al finalizar",
        'sftp_host' => "Host",
        'sftp_port' => "Puerto",
        'sftp_username' => "Usuario",
        'sftp_password' => "Contraseña",
        'sftp_password_help' => "Dejar vacío para conservar la contraseña guardada.",
        'sftp_root' => "Directorio de destino",
        'sftp_root_help' => "Vacío = directorio personal del usuario SFTP.",
        'enabled' => "Activo",
        'disabled' => "Desactivado",
    ],

    'action' => [
        'create' => "Crear",
        'edit' => "Editar",
        'save' => "Guardar",
        'delete' => "Eliminar",
        'delete_confirm' => "¿Eliminar realmente esta asignación de concepto? Las exportaciones existentes no cambian; las futuras vuelven al concepto predeterminado.",
        'configure' => "Configurar",
    ],

    'flash' => [
        'created' => "Asignación de concepto creada.",
        'updated' => "Asignación de concepto actualizada.",
        'deleted' => "Asignación de concepto eliminada.",
        'delivery_saved' => "Configuración de entrega guardada.",
    ],

    'validation' => [
        'external_code_format' => "El concepto de destino no tiene un formato válido para el perfil de exportación elegido (DATEV/Lexware: numérico, 1–4 dígitos).",
        'wage_type_unique' => "Ya existe una asignación para este concepto en este perfil.",
        'recipients_required' => "El envío por correo requiere al menos una dirección de destinatario.",
        'password_required' => "La subida SFTP requiere una contraseña.",
    ],

    'error' => [
        'missing_mappings' => "Exportación cancelada: los siguientes conceptos salariales no tienen concepto de destino en el programa de nóminas: :types. Cree una asignación en «Conceptos salariales & entrega de exportaciones» o defina el concepto en la regla de recargo.",
    ],

    'delivery' => [
        'title_evidence' => "Entrega automática",
        'evidence_mail' => "Correo a :to",
        'evidence_sftp' => "SFTP a :target",
        'note_auto' => "Entregado automáticamente (:channels).",
        'file_missing' => "Archivo de exportación no encontrado — entrega omitida.",
        'abandoned' => "La entrega automática falló definitivamente tras varios intentos.",
    ],

    'mail' => [
        'subject' => "Exportación de tiempos :profile :period",
        'heading' => "Exportación de tiempos para nóminas",
        'body' => "Adjunto encontrará la exportación de tiempos del perfil :profile para el período :period.",
        'meta' => ":rows líneas · SHA-256 :hash",
    ],
];
